<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PublicBusinessDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::query()->where('listed_in_directory', true)->where('publication_status', 'approved');
        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%"));
        }
        foreach (['category', 'country', 'city'] as $field) {
            if ($value = trim((string) $request->query($field))) $query->where($field, $value);
        }
        $businesses = $query->orderByDesc('featured')->latest('published_at')->paginate(18);
        $businesses->getCollection()->transform(fn (Company $company) => $this->publicBusiness($company));
        return response()->json($businesses->toArray() + ['filters' => [
            'categories' => Company::where('listed_in_directory', true)->where('publication_status', 'approved')->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'countries' => Company::where('listed_in_directory', true)->where('publication_status', 'approved')->whereNotNull('country')->distinct()->orderBy('country')->pluck('country'),
            'cities' => Company::where('listed_in_directory', true)->where('publication_status', 'approved')->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
        ]])->header('Cache-Control', 'public, max-age=60');
    }

    public function show(string $slug)
    {
        $company = Company::where('slug', $slug)->where('listed_in_directory', true)->where('publication_status', 'approved')->firstOrFail();
        return response()->json(['data' => $this->publicBusiness($company, true)])->header('Cache-Control', 'public, max-age=60');
    }

    private function publicBusiness(Company $company, bool $detail = false): array
    {
        $data = [
            'id' => $company->id, 'slug' => $company->slug, 'name' => $company->name,
            'category' => $company->category, 'business_type' => $company->business_type,
            'description' => $company->description, 'country' => $company->country,
            'city' => $company->city, 'suburb' => $company->suburb,
            'logo_url' => $company->logo_path ? URL::route('directory.logo', ['company' => $company->slug]) : null,
        ];
        if ($detail) $data += [
            'address' => $company->address, 'phone' => $company->phone,
            'public_email' => $company->public_email, 'website' => $company->website,
            'business_hours' => $company->business_hours,
        ];
        return $data;
    }
}
