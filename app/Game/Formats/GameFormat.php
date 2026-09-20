<?php

declare(strict_types=1);

namespace App\Game\Formats;

use App\Game\Models\Game;

final class GameFormat
{
    public static function snapshot(Game $game): array
    {
        $finished = in_array($game->status, ['solved', 'finished', 'abandoned'], true);
        $snapshot = (array) $game->question_snapshot;
        $question = $game->relationLoaded('question') ? $game->question : null;
        $questionTags = $question?->tags->map(static fn ($tag): array => [
            'id' => (int) $tag->id,
            'name' => (string) $tag->name,
        ])->values()->all() ?? [];

        return [
            'id' => $game->public_id,
            'question_id' => $question?->public_id,
            'mode' => $game->room_id ? 'multiplayer' : 'single',
            'room_id' => $game->relationLoaded('room') ? $game->room?->public_id : null,
            'status' => $game->status,
            'difficulty' => (int) $game->difficulty,
            'question_limit' => (int) $game->question_limit,
            'question_count' => (int) $game->question_count,
            'remaining_questions' => max(0, (int) $game->question_limit - (int) $game->question_count),
            'hint_count' => (int) $game->hint_count,
            'title' => $snapshot['title'] ?? '',
            'surface' => $snapshot['surface'] ?? '',
            'risk_level' => $snapshot['risk_level'] ?? 'safe',
            'risk_types' => array_values((array) ($snapshot['risk_types'] ?? ($question ? $question->risk_types : []))),
            'risk_note' => $snapshot['risk_note'] ?? $question?->risk_note,
            'tags' => array_values((array) ($snapshot['tags'] ?? $questionTags)),
            'messages' => $game->messages->map(static fn ($message): array => [
                'sequence' => (int) $message->sequence,
                'user_id' => $message->user_id ? (int) $message->user_id : null,
                'username' => $message->user?->username,
                'avatar_url' => $message->user?->avatar_url,
                'role' => $message->role,
                'type' => $message->type,
                'content' => $message->content,
                'metadata' => $message->metadata,
            ])->all(),
            'used_hints' => $game->hints->pluck('level')->map(static fn ($value): int => (int) $value)->all(),
            'discovered_points' => $game->points->pluck('point_key')->all(),
            'bottom' => $finished ? ($snapshot['bottom'] ?? null) : null,
            'points' => $finished ? ($snapshot['points'] ?? []) : null,
            'guess' => $finished && $game->guess ? [
                'content' => $game->guess->content,
                'is_solved' => (bool) $game->guess->is_solved,
                'summary' => $game->guess->summary,
            ] : null,
        ];
    }

    /** 历史列表项：不含汤底与推理点，避免答案泄漏 */
    public static function historyItem(Game $game): array
    {
        $snapshot = (array) $game->question_snapshot;
        $finished = in_array((string) $game->status, ['solved', 'finished', 'abandoned'], true);
        $startedAt = $game->started_at ? strtotime((string) $game->started_at) : null;
        $finishedAt = $game->finished_at ? strtotime((string) $game->finished_at) : null;
        $updatedAt = $finishedAt ?: (int) strtotime((string) ($game->update_time ?? $game->create_time));
        $duration = 0;
        if ($startedAt !== null && $startedAt > 0 && $updatedAt >= $startedAt) {
            $duration = $updatedAt - $startedAt;
        }

        $tags = array_values(array_map(static fn ($tag): array => [
            'id' => (int) ($tag['id'] ?? 0),
            'name' => (string) ($tag['name'] ?? ''),
        ], (array) ($snapshot['tags'] ?? [])));

        return [
            'id' => (string) $game->public_id,
            'question_id' => $game->relationLoaded('question') ? $game->question?->public_id : null,
            'status' => (string) $game->status,
            'title' => (string) ($snapshot['title'] ?? ''),
            'surface' => (string) ($snapshot['surface'] ?? ''),
            'difficulty' => (int) $game->difficulty,
            'tags' => $tags,
            'question_count' => (int) $game->question_count,
            'create_time' => (string) $game->create_time,
            'update_time' => (string) ($game->update_time ?? $game->create_time),
            'duration_seconds' => max(0, $duration),
            'guess' => $finished && $game->guess ? [
                'content' => (string) $game->guess->content,
                'is_solved' => (bool) $game->guess->is_solved,
                'summary' => (string) $game->guess->summary,
            ] : null,
        ];
    }
}
