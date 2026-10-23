<?php

namespace App\Models\Concerns;

use App\Models\Organisation;
use App\Models\Scopes\OrganisationScope;
use App\Tenancy\CurrentOrganisation;

trait BelongsToOrganisation
{
    protected static function bootBelongsToOrganisation(): void
    {
        static::addGlobalScope(new OrganisationScope);
        static::creating(function ($model) {
            if (! $model->organisation_id) {
                $model->organisation_id = app(CurrentOrganisation::class)->id()
                    ?? Organisation::query()->where('slug', config('tenancy.default_slug', 'voca'))->value('id');
            }
        });
    }

    public function organisation() { return $this->belongsTo(Organisation::class); }
}
