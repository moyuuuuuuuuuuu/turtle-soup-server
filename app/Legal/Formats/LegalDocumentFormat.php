<?php

declare(strict_types=1);

namespace App\Legal\Formats;

final class LegalDocumentFormat
{
    /**
     * @param array{service_terms:string,privacy_policy:string} $documents
     * @return array{service_terms:string,privacy_policy:string}
     */
    public static function publicDocuments(array $documents): array
    {
        return [
            'service_terms' => $documents['service_terms'],
            'privacy_policy' => $documents['privacy_policy'],
        ];
    }
}
