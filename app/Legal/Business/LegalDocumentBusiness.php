<?php

declare(strict_types=1);

namespace App\Legal\Business;

use App\Legal\Repositories\LegalDocumentRepository;

final class LegalDocumentBusiness
{
    public function __construct(private readonly LegalDocumentRepository $repository = new LegalDocumentRepository())
    {
    }

    /** @return array{service_terms:string,privacy_policy:string} */
    public function documents(): array
    {
        return $this->repository->documents();
    }
}
