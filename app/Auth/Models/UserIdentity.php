<?php

declare(strict_types=1);

namespace App\Auth\Models;

use App\Common\Models\PersistenceModel;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $create_time
 */
final class UserIdentity extends PersistenceModel
{
    protected $table = 'turtle_user_identities';

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['metadata' => 'array']);
    }
}
