<?php

namespace HiEvents\Services\Application\Handlers\ContentTranslation;

use HiEvents\DomainObjects\EventTranslationSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventTranslationSettingDomainObjectAbstract;
use HiEvents\Repository\Interfaces\EventTranslationSettingRepositoryInterface;
use HiEvents\Services\Application\Handlers\ContentTranslation\DTO\UpdateEventTranslationSettingsDTO;

class UpdateEventTranslationSettingsHandler
{
    public function __construct(
        private readonly EventTranslationSettingRepositoryInterface $settingRepository,
    ) {}

    public function handle(UpdateEventTranslationSettingsDTO $dto): EventTranslationSettingDomainObject
    {
        $attributes = [
            EventTranslationSettingDomainObjectAbstract::EVENT_ID => $dto->event_id,
            EventTranslationSettingDomainObjectAbstract::SOURCE_LOCALE => $dto->source_locale,
            EventTranslationSettingDomainObjectAbstract::FALLBACK_LOCALE => $dto->fallback_locale === $dto->source_locale
                ? null
                : $dto->fallback_locale,
            EventTranslationSettingDomainObjectAbstract::LOCALES => array_values(array_unique(array_filter(
                $dto->locales,
                fn (string $locale) => $locale !== $dto->source_locale,
            ))),
        ];

        $existing = $this->settingRepository->findFirstWhere([
            EventTranslationSettingDomainObjectAbstract::EVENT_ID => $dto->event_id,
        ]);

        if ($existing === null) {
            return $this->settingRepository->create($attributes);
        }

        return $this->settingRepository->updateFromArray($existing->getId(), $attributes);
    }
}
