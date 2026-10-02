<?php

namespace App\Services\Api\Languages;

use App\Models\Admin\Language;
use App\Repositories\Admin\LanguageRepository;
use Illuminate\Database\Eloquent\Collection;

class LanguageService
{
    public function __construct(
        protected LanguageRepository $languageRepository,
    ) {}

    /**
     * Public list of the languages an admin has left active.
     *
     * @return Collection<int, Language>
     */
    public function list(): Collection
    {
        return $this->languageRepository->getActive();
    }
}
