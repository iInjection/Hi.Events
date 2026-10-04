<?php

namespace HiEvents\Services\Application\Handlers\ContentTranslation\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertContentTranslationsDTO extends BaseDataObject
{
    /**
     * @param  array<int, array{type: string, id: int, field: string, value: string|array|null}>  $translations
     */
    public function __construct(
        public readonly int $event_id,
        public readonly string $locale,
        public readonly array $translations,
    ) {}
}
