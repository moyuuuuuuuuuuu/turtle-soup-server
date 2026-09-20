<?php

declare(strict_types=1);

namespace App\Question\Business;

use App\Common\Enums\ErrorCode;
use App\Question\Models\Question;
use Illuminate\Database\Eloquent\Builder;

final class PublicQuestionBusiness
{
    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function page(array $filters, int $page, int $size): array
    {
        $query = $this->query($filters);
        $result = $query->paginate($size, ['*'], 'page', $page);

        return [
            'items' => array_map([$this, 'format'], $result->items()),
            'pagination' => [
                'page' => $page,
                'page_size' => $size,
                'total' => $result->total(),
                'total_pages' => (int) ceil($result->total() / $size),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function detail(string $publicId, string $language): array
    {
        $question = $this->query(['language' => $language])->where('public_id', $publicId)->first();
        if (!$question instanceof Question) {
            ErrorCode::QUESTION_NOT_FOUND->throw();
        }

        return $this->format($question, $language);
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function random(array $filters): array
    {
        $question = $this->query($filters)->inRandomOrder()->first();
        if (!$question instanceof Question) {
            ErrorCode::QUESTION_NOT_FOUND->throw();
        }

        return $this->format($question, (string) ($filters['language'] ?? 'zh-CN'));
    }

    /** @param array<string, mixed> $filters @return Builder<Question> */
    private function query(array $filters): Builder
    {
        /** @var Builder<Question> $query */
        $query = Question::query()->with(['translations', 'tags'])->withCount('games')->where('status', 'published')->whereIn('risk_level', ['safe', 'caution']);
        if (($filters['difficulty'] ?? '') !== '') {
            $query->where('difficulty', (int) $filters['difficulty']);
        }
        if (($filters['tag_id'] ?? '') !== '') {
            $query->whereHas('tags', fn ($item) => $item->where('turtle_tags.id', (int) $filters['tag_id']));
        }
        if (in_array($filters['risk_level'] ?? null, ['safe', 'caution'], true)) {
            $query->where('risk_level', (string) $filters['risk_level']);
        }
        if (($filters['keyword'] ?? '') !== '') {
            $keyword = '%' . $filters['keyword'] . '%';
            $language = (string) ($filters['language'] ?? 'zh-CN');
            $query->where(static function (Builder $builder) use ($keyword, $language): void {
                $builder
                    ->whereHas('translations', fn ($item) => $item->where('language', $language)->where(fn ($text) => $text->whereLike('title', $keyword)->orWhereLike('surface', $keyword)))
                    ->orWhereHas('tags', fn ($tags) => $tags->whereLike('name', $keyword));
            });
        }

        if (filter_var($filters['featured'] ?? false, FILTER_VALIDATE_BOOL)) {
            $now = date('Y-m-d H:i:s');
            $query->where('is_featured', true)
                ->whereRaw('(featured_starts_at IS NULL OR featured_starts_at <= ?)', [$now])
                ->whereRaw('(featured_ends_at IS NULL OR featured_ends_at > ?)', [$now])
                ->orderBy('featured_sort');
        }

        return match ((string) ($filters['sort'] ?? '')) {
            'popular' => $query->orderByDesc('games_count')->orderByDesc('published_at'),
            'difficulty_asc' => $query->orderBy('difficulty')->orderByDesc('published_at'),
            'difficulty_desc' => $query->orderByDesc('difficulty')->orderByDesc('published_at'),
            'latest' => $query->orderByDesc('published_at'),
            default => $query->orderByDesc('published_at'),
        };
    }

    /** @return array<string, mixed> */
    private function format(Question $question, string $language = 'zh-CN'): array
    {
        $translations = $question->getRelation('translations');
        $tags = $question->getRelation('tags');
        $translation = $translations->firstWhere('language', $language) ?? $translations->firstWhere('language', 'zh-CN');

        $riskNote = trim((string) $question->getAttribute('risk_note')) ?: null;

        return ['id' => $question->getAttribute('public_id'), 'title' => $translation?->title, 'surface' => $translation?->surface, 'difficulty' => (int) $question->getAttribute('difficulty'), 'language' => $translation?->language, 'risk_level' => $question->getAttribute('risk_level'), 'risk_types' => array_values((array) $question->getAttribute('risk_types')), 'risk_note' => $riskNote, 'risk_warning' => $riskNote, 'tags' => $tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name])->values()->all(), 'play_count' => (int) $question->getAttribute('games_count')];
    }
}
