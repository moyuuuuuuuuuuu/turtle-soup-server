<?php

declare(strict_types=1);

namespace App\Legal\Repositories;

use plugin\saiadmin\app\cache\ConfigCache;

final class LegalDocumentRepository
{
    /** @return array{service_terms:string,privacy_policy:string} */
    public function documents(): array
    {
        $documents = ['service_terms' => '', 'privacy_policy' => ''];

        foreach (ConfigCache::getConfig('legal_config') as $config) {
            $key = (string) ($config['key'] ?? '');
            if (array_key_exists($key, $documents)) {
                $documents[$key] = (string) ($config['value'] ?? '');
            }
        }

        return $documents;
    }
}
