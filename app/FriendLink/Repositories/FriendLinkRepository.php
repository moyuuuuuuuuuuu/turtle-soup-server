<?php

declare(strict_types=1);

namespace App\FriendLink\Repositories;

use App\FriendLink\Models\FriendLink;
use Illuminate\Database\Eloquent\Collection;

final class FriendLinkRepository
{
    /** @return Collection<int, FriendLink> */
    public function enabledLinks(): Collection
    {
        return FriendLink::query()
            ->where('status', true)
            ->orderByDesc('sort')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, FriendLink> */
    public function page(array $filters, int $page, int $size): Collection
    {
        return $this->pageQuery($filters)
            ->forPage($page, $size)
            ->get();
    }

    public function count(array $filters): int
    {
        return $this->pageQuery($filters)->count();
    }

    public function find(int $id): ?FriendLink
    {
        return FriendLink::query()->find($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): FriendLink
    {
        return FriendLink::create($data);
    }

    /** @param array<int, int|string> $ids */
    public function deleteByIds(array $ids): void
    {
        FriendLink::query()->whereIn('id', $ids)->delete();
    }

    /** @return \Illuminate\Database\Eloquent\Builder<FriendLink> */
    private function pageQuery(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = FriendLink::query()->orderByDesc('sort')->orderByDesc('id');
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(static function ($builder) use ($keyword): void {
                $builder->where('name', 'like', "%{$keyword}%")
                    ->orWhere('url', 'like', "%{$keyword}%");
            });
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (bool) $filters['status']);
        }

        return $query;
    }
}
