<?php

namespace App\Tenancy;

use App\Models\Organisation;

class CurrentOrganisation
{
    private ?Organisation $organisation = null;

    public function set(?Organisation $organisation): void { $this->organisation = $organisation; }
    public function get(): ?Organisation { return $this->organisation; }
    public function id(): ?int { return $this->organisation?->id; }
    public function has(): bool { return $this->organisation !== null; }
}
