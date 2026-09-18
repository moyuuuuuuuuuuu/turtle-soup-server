<?php

declare(strict_types=1);

namespace App\FriendLink\Models;

use App\Common\Models\PersistenceModel;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $url
 * @property string|null $logo_url
 * @property string|null $description
 * @property string|null $reciprocal_url
 * @property string|null $contact_email
 * @property bool $status
 * @property int $sort
 */

final class FriendLink extends PersistenceModel
{
    protected $table = 'turtle_friend_links';

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['status' => 'boolean']);
    }
}
