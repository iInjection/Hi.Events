<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventTranslationSettingDomainObject;
use HiEvents\Models\EventTranslationSetting;
use HiEvents\Repository\Interfaces\EventTranslationSettingRepositoryInterface;

/**
 * @extends BaseRepository<EventTranslationSettingDomainObject>
 */
class EventTranslationSettingRepository extends BaseRepository implements EventTranslationSettingRepositoryInterface
{
    protected function getModel(): string
    {
        return EventTranslationSetting::class;
    }

    public function getDomainObject(): string
    {
        return EventTranslationSettingDomainObject::class;
    }
}
