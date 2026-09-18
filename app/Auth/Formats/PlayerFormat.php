<?php

declare(strict_types=1);

namespace App\Auth\Formats;

use App\Auth\Enums\IdentityProvider;
use App\Auth\Models\User;
use App\Auth\Models\UserIdentity;

final class PlayerFormat
{
    public static function user(User $user): array
    {
        return ['id' => $user->public_id, 'username' => $user->username, 'email' => $user->email, 'avatar_url' => $user->avatar_url, 'bio' => $user->bio, 'status' => $user->status, 'email_verified_at' => $user->email_verified_at, 'username_changed_at' => $user->username_changed_at, 'create_time' => $user->create_time];
    }

    /** Never expose provider_subject (openid/union raw id) to clients. */
    public static function identity(UserIdentity $identity): array
    {
        $provider = (string) $identity->provider;
        $label = IdentityProvider::tryFrom($provider)?->label() ?? $provider;

        return [
            'provider' => $provider,
            'provider_label' => $label,
            'bound_at' => $identity->create_time,
        ];
    }

    /** @param iterable<UserIdentity> $identities */
    public static function identities(iterable $identities): array
    {
        $items = [];
        foreach ($identities as $identity) {
            $items[] = self::identity($identity);
        }
        return $items;
    }
}
