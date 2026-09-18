<?php

declare(strict_types=1);

namespace App\FriendLink\Formats;

use App\FriendLink\Models\FriendLink;
use Illuminate\Database\Eloquent\Collection;

final class FriendLinkFormat
{
    /** @param Collection<int, FriendLink> $links @return array<int, array<string, mixed>> */
    public static function publicList(Collection $links): array
    {
        return $links->map(static fn (FriendLink $link): array => [
            'id' => (string) $link->public_id,
            'name' => (string) $link->name,
            'url' => (string) $link->url,
            'logo_url' => $link->logo_url !== null && $link->logo_url !== '' ? (string) $link->logo_url : null,
            'description' => $link->description !== null && $link->description !== '' ? (string) $link->description : null,
        ])->all();
    }

    /** @param Collection<int, FriendLink> $links @return array<int, array<string, mixed>> */
    public static function adminItems(Collection $links): array
    {
        return $links->map(static fn (FriendLink $link): array => self::adminItem($link))->all();
    }

    /** @return array<string, mixed> */
    public static function adminItem(FriendLink $link): array
    {
        return [
            'id' => (int) $link->id,
            'public_id' => (string) $link->public_id,
            'name' => (string) $link->name,
            'url' => (string) $link->url,
            'logo_url' => $link->logo_url,
            'description' => $link->description,
            'reciprocal_url' => $link->reciprocal_url,
            'contact_email' => $link->contact_email,
            'status' => (bool) $link->status,
            'sort' => (int) $link->sort,
            'create_time' => (string) ($link->create_time?->format('Y-m-d H:i:s') ?? ''),
            'update_time' => (string) ($link->update_time?->format('Y-m-d H:i:s') ?? ''),
        ];
    }
}
