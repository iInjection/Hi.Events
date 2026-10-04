<?php

namespace HiEvents\Services\Application\Handlers\ContentTranslation;

use HiEvents\DomainObjects\Enums\TranslatableFieldFormat;
use HiEvents\DomainObjects\Generated\ContentTranslationDomainObjectAbstract;
use HiEvents\Exceptions\InvalidContentTranslationException;
use HiEvents\Repository\Interfaces\ContentTranslationRepositoryInterface;
use HiEvents\Services\Application\Handlers\ContentTranslation\DTO\UpsertContentTranslationsDTO;
use HiEvents\Services\Domain\ContentTranslation\DTO\TranslatableContentItemDTO;
use HiEvents\Services\Domain\ContentTranslation\TranslatableContentCollector;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

class UpsertContentTranslationsHandler
{
    public function __construct(
        private readonly ContentTranslationRepositoryInterface $translationRepository,
        private readonly TranslatableContentCollector $collector,
        private readonly HtmlPurifierService $purifier,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws InvalidContentTranslationException
     */
    public function handle(UpsertContentTranslationsDTO $dto): void
    {
        /** @var Collection<string, TranslatableContentItemDTO> $itemsByKey */
        $itemsByKey = $this->collector
            ->collect($dto->event_id)
            ->keyBy(fn (TranslatableContentItemDTO $item) => $item->key());

        $this->databaseManager->transaction(function () use ($dto, $itemsByKey) {
            foreach ($dto->translations as $translation) {
                $item = $itemsByKey->get(TranslatableContentItemDTO::buildKey(
                    $translation['type'],
                    (int) $translation['id'],
                    $translation['field'],
                ));

                if ($item === null) {
                    throw new InvalidContentTranslationException(__('This text cannot be translated for this event'));
                }

                $this->saveTranslation($dto->event_id, $dto->locale, $item, $translation['value'] ?? null);
            }
        });
    }

    private function saveTranslation(int $eventId, string $locale, TranslatableContentItemDTO $item, string|array|null $value): void
    {
        $where = [
            ContentTranslationDomainObjectAbstract::EVENT_ID => $eventId,
            ContentTranslationDomainObjectAbstract::TRANSLATABLE_TYPE => $item->type->value,
            ContentTranslationDomainObjectAbstract::TRANSLATABLE_ID => $item->id,
            ContentTranslationDomainObjectAbstract::FIELD => $item->field,
            ContentTranslationDomainObjectAbstract::LOCALE => $locale,
        ];

        $normalizedValue = $this->normalizeValue($item, $value);

        if ($normalizedValue === null) {
            $this->translationRepository->deleteWhere($where);

            return;
        }

        $attributes = [
            ...$where,
            ContentTranslationDomainObjectAbstract::VALUE => $normalizedValue,
            ContentTranslationDomainObjectAbstract::SOURCE_HASH => $item->sourceHash(),
        ];

        $existing = $this->translationRepository->findFirstWhere($where);

        if ($existing === null) {
            $this->translationRepository->create($attributes);
        } else {
            $this->translationRepository->updateFromArray($existing->getId(), $attributes);
        }
    }

    /**
     * @throws InvalidContentTranslationException
     */
    private function normalizeValue(TranslatableContentItemDTO $item, string|array|null $value): ?string
    {
        if ($item->format === TranslatableFieldFormat::LIST) {
            return $this->normalizeList($item, $value);
        }

        if (is_array($value)) {
            throw new InvalidContentTranslationException(__('This text cannot be translated for this event'));
        }

        $value = trim((string) $value);

        if ($item->format === TranslatableFieldFormat::TEXT) {
            return $value === '' ? null : $value;
        }

        $value = (string) $this->purifier->purify($value);

        return trim(strip_tags($value)) === '' ? null : $value;
    }

    /**
     * @throws InvalidContentTranslationException
     */
    private function normalizeList(TranslatableContentItemDTO $item, string|array|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value) || count($value) !== count((array) $item->source)) {
            throw new InvalidContentTranslationException(__('Every answer option needs its own translation field'));
        }

        $labels = array_map(fn ($label) => trim((string) $label), array_values($value));

        return implode('', $labels) === '' ? null : json_encode($labels, JSON_UNESCAPED_UNICODE);
    }
}
