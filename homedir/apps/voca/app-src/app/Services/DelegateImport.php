<?php

namespace App\Services;

use App\Models\MovementDelegate;
use App\Models\MovementRoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DelegateImport
{
    // Only contact fields are read. Workbook notes and other cells are never executed.
    public function read(string $path, bool $xlsx): array
    {
        $rows = [];
        if ($xlsx) {
            $zip = new \ZipArchive;
            if ($zip->open($path) !== true) $this->fail('Cannot open the workbook.');
            try {
                $total = 0;
                if ($zip->numFiles > 1000) $this->fail('Workbook contains too many files.');
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $size = $zip->statIndex($index)['size'];
                    $total += $size;
                    if ($size > 10 * 1024 * 1024 || $total > 25 * 1024 * 1024) $this->fail('Workbook is too large when expanded. Export a smaller CSV.');
                }
                $strings = [];
                $xml = $zip->getFromName('xl/sharedStrings.xml');
                if ($xml !== false) {
                    foreach ($this->xml($xml)->si as $item) {
                        $item->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                        $strings[] = implode('', array_map('strval', $item->xpath('.//s:t') ?: []));
                    }
                }
                $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
                if ($sheet === false) $this->fail('The workbook must contain a first worksheet.');
                foreach ($this->xml($sheet)->sheetData->row as $row) {
                    $values = [];
                    foreach ($row->c as $cell) {
                        $column = preg_replace('/[0-9]/', '', (string) $cell['r']);
                        $index = 0;
                        foreach (str_split($column) as $letter) $index = $index * 26 + ord($letter) - 64;
                        $values[$index - 1] = (string) $cell['t'] === 's'
                            ? ($strings[(int) $cell->v] ?? '')
                            : ((string) $cell['t'] === 'inlineStr' ? (string) $cell->is->t : (string) $cell->v);
                    }
                    $rows[] = $values;
                    if (count($rows) > 2001) $this->fail('Import up to 2,000 delegates at a time.');
                }
            } finally { $zip->close(); }
        } else {
            $handle = fopen($path, 'r');
            try {
                while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                    $rows[] = $row;
                    if (count($rows) > 2001) $this->fail('Import up to 2,000 delegates at a time.');
                }
            } finally { fclose($handle); }
        }
        $headers = array_map(fn ($value) => strtolower(trim(ltrim($value, "\xEF\xBB\xBF"))), array_shift($rows) ?? []);
        $columns = [];
        foreach (['name' => ['name', 'full name'], 'email' => ['email', 'e-mail 1 - value'], 'phone' => ['phone', 'phone 1 - value'], 'group' => ['group', 'group membership']] as $field => $aliases) {
            foreach ($headers as $index => $header) if (in_array($header, $aliases, true)) $columns[$field] = $index;
        }
        if (! isset($columns['name'])) $this->fail('Include a Name column in the first row.');
        $delegates = [];
        $emails = [];
        foreach ($rows as $row) {
            $entry = [];
            foreach ($columns as $field => $index) $entry[$field] = trim($row[$index] ?? '');
            if (! ($entry['name'] ?? '')) continue;
            $entry['email'] = Str::lower($entry['email'] ?? '') ?: null;
            validator($entry, [
                'name' => 'required|string|max:120', 'email' => 'nullable|email:rfc|max:190',
                'phone' => 'nullable|string|max:60', 'group' => 'nullable|string|max:120',
            ])->validate();
            if ($entry['email'] && isset($emails[$entry['email']])) $this->fail('Duplicate email in the file. Check the delegate list before importing.');
            if ($entry['email']) $emails[$entry['email']] = true;
            $delegates[] = $entry;
        }
        if (! count($delegates)) $this->fail('No named delegates were found.');
        return $delegates;
    }

    public function import(MovementRoster $roster, array $delegates): int
    {
        return DB::transaction(function () use ($roster, $delegates) {
            $count = 0;
            foreach ($delegates as $entry) {
                if (! empty($entry['email']) && MovementDelegate::where('roster_id', $roster->id)->where('email', $entry['email'])->exists()) continue;
                MovementDelegate::create([...$entry, 'organisation_id' => $roster->organisation_id,
                    'roster_id' => $roster->id, 'reference' => 'VOCA-'.Str::upper(Str::random(24))]);
                $count++;
            }
            return $count;
        });
    }

    private function xml(string $xml): \SimpleXMLElement
    {
        if (strlen($xml) > 10 * 1024 * 1024 || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) $this->fail('Unsupported workbook XML.');
        $document = @simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        if ($document === false) $this->fail('Invalid workbook XML.');
        $document->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        return $document;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
