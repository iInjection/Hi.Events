<?php

namespace HiEvents\Services\Application\Handlers\ContentTranslation\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class EventContentTranslationsDTO extends BaseDataObject
{
    /**
     * @param  array{source_locale: string, fallback_locale: ?string, locales: string[]}|null  $settings
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        public readonly ?array $settings,
        public readonly array $items,
    ) {}
}
