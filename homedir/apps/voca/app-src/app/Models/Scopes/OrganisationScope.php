<?php

namespace App\Models\Scopes;

use App\Tenancy\CurrentOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganisationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($id = app(CurrentOrganisation::class)->id()) {
            $builder->where($model->qualifyColumn('organisation_id'), $id);
        }
    }
}
