<?php

declare(strict_types=1);

namespace App\Question\Formats;

use App\Question\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

final class TagFormat
{
    /**
     * @param Collection<int, Tag> $tags
     * @return array<int, array{id: int, name: string}>
     */
    public static function publicList(Collection $tags): array
    {
        return $tags->map(static fn (Tag $tag): array => [
            'id' => (int) $tag->getKey(),
            'name' => (string) $tag->getAttribute('name'),
        ])->values()->all();
    }
}
