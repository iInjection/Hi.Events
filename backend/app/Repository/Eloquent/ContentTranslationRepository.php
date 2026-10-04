<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\ContentTranslationDomainObject;
use HiEvents\Models\ContentTranslation;
use HiEvents\Repository\Interfaces\ContentTranslationRepositoryInterface;

/**
 * @extends BaseRepository<ContentTranslationDomainObject>
 */
class ContentTranslationRepository extends BaseRepository implements ContentTranslationRepositoryInterface
{
    protected function getModel(): string
    {
        return ContentTranslation::class;
    }

    public function getDomainObject(): string
    {
        return ContentTranslationDomainObject::class;
    }
}
