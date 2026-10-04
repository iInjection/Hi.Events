<?php

namespace HiEvents\Services\Application\Handlers\ContentTranslation\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpdateEventTranslationSettingsDTO extends BaseDataObject
{
    /**
     * @param  string[]  $locales
     */
    public function __construct(
        public readonly int $event_id,
        public readonly string $source_locale,
        public readonly ?string $fallback_locale,
        public readonly array $locales,
    ) {}
}
