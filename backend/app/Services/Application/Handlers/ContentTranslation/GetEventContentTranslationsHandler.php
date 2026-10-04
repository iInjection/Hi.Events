<?php

namespace HiEvents\Services\Application\Handlers\ContentTranslation;

use HiEvents\DomainObjects\ContentTranslationDomainObject;
use HiEvents\DomainObjects\Enums\TranslatableFieldFormat;
use HiEvents\DomainObjects\Generated\ContentTranslationDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventTranslationSettingDomainObjectAbstract;
use HiEvents\Repository\Interfaces\ContentTranslationRepositoryInterface;
use HiEvents\Repository\Interfaces\EventTranslationSettingRepositoryInterface;
use HiEvents\Services\Application\Handlers\ContentTranslation\DTO\EventContentTranslationsDTO;
use HiEvents\Services\Domain\ContentTranslation\DTO\TranslatableContentItemDTO;
use HiEvents\Services\Domain\ContentTranslation\TranslatableContentCollector;

class GetEventContentTranslationsHandler
{
    public function __construct(
        private readonly EventTranslationSettingRepositoryInterface $settingRepository,
        private readonly ContentTranslationRepositoryInterface $translationRepository,
        private readonly TranslatableContentCollector $collector,
    ) {}

    public function handle(int $eventId): EventContentTranslationsDTO
    {
        $settings = $this->settingRepository->findFirstWhere([
            EventTranslationSettingDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        $translationsByKey = $this->translationRepository
            ->findWhere([ContentTranslationDomainObjectAbstract::EVENT_ID => $eventId])
            ->groupBy(fn (ContentTranslationDomainObject $translation) => TranslatableContentItemDTO::buildKey(
                $translation->getTranslatableType(),
                $translation->getTranslatableId(),
                $translation->getField(),
            ));

        $items = $this->collector->collect($eventId)->map(fn (TranslatableContentItemDTO $item) => [
            'type' => $item->type->value,
            'id' => $item->id,
            'field' => $item->field,
            'format' => $item->format->value,
            'source' => $item->source,
            'context' => $item->context,
            'translations' => (object) $translationsByKey
                ->get($item->key(), collect())
                ->mapWithKeys(fn (ContentTranslationDomainObject $translation) => [
                    $translation->getLocale() => [
                        'value' => $item->format === TranslatableFieldFormat::LIST
                            ? json_decode($translation->getValue(), true)
                            : $translation->getValue(),
                        'is_outdated' => $translation->getSourceHash() !== $item->sourceHash(),
                    ],
                ])
                ->all(),
        ]);

        return new EventContentTranslationsDTO(
            settings: $settings === null ? null : [
                'source_locale' => $settings->getSourceLocale(),
                'fallback_locale' => $settings->getFallbackLocale(),
                'locales' => array_values((array) $settings->getLocales()),
            ],
            items: $items->values()->all(),
        );
    }
}
