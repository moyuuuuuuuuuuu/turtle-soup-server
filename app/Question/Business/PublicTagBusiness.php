<?php

declare(strict_types=1);

namespace App\Question\Business;

use App\Question\Formats\TagFormat;
use App\Question\Models\Tag;
use Illuminate\Database\Eloquent\Builder;

final class PublicTagBusiness
{
    /** @return array{items: array<int, array{id: int, name: string}>} */
    public function list(): array
    {
        return [
            'items' => TagFormat::publicList($this->publicTagsQuery()->get()),
        ];
    }

    /** @return Builder<Tag> */
    private function publicTagsQuery(): Builder
    {
        /** @var Builder<Tag> $query */
        $query = Tag::query()
            ->whereHas(
                'questions',
                fn ($item) => $item->where('status', 'published')->whereIn('risk_level', ['safe', 'caution']),
            )
            ->withCount([
                'questions' => fn ($item) => $item->where('status', 'published')->whereIn('risk_level', ['safe', 'caution']),
            ])
            ->orderByDesc('questions_count')
            ->orderBy('name');

        return $query;
    }
}
