<?php

namespace App\Services;

use App\Models\Organisation;
use App\Models\User;
use App\Notifications\AccountInvitationNotification;
use Illuminate\Support\Facades\Password;

class AccountInvitation
{
    public function send(User $user, Organisation $organisation): string
    {
        if (! $user->canAccessOrganisation($organisation->id)) {
            return 'access.denied';
        }

        return Password::sendResetLink(['email' => $user->email], function ($user, $token) use ($organisation) {
            $host = $organisation->domains()->whereNotNull('verified_at')->orderByDesc('is_primary')->value('host');
            $base = $host ? 'https://'.$host : 'https://'.$organisation->slug.'.'.config('tenancy.base_domain');
            if ($organisation->slug === config('tenancy.default_slug')) {
                $base = rtrim(config('app.url'), '/');
            }
            $url = $base.'/reset-password/'.$token.'?'.http_build_query(['email' => $user->email]);
            $user->notify(new AccountInvitationNotification($url, $organisation->name));
        });
    }
}
