<?php

namespace HiEvents\Services\Domain\ContentTranslation\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\TranslatableContentType;
use HiEvents\DomainObjects\Enums\TranslatableFieldFormat;

class TranslatableContentItemDTO extends BaseDataObject
{
    public function __construct(
        public readonly TranslatableContentType $type,
        public readonly int $id,
        public readonly string $field,
        public readonly TranslatableFieldFormat $format,
        public readonly string|array $source,
        public readonly ?string $context = null,
    ) {}

    public function key(): string
    {
        return self::buildKey($this->type->value, $this->id, $this->field);
    }

    public function sourceHash(): string
    {
        return sha1(is_array($this->source) ? json_encode(array_values($this->source)) : $this->source);
    }

    public static function buildKey(string $type, int $id, string $field): string
    {
        return $type.':'.$id.':'.$field;
    }
}
