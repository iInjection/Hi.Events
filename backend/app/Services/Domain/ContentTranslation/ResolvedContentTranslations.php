<?php

namespace HiEvents\Services\Domain\ContentTranslation;

use HiEvents\DomainObjects\ContentTranslationDomainObject;
use HiEvents\DomainObjects\Enums\TranslatableContentType;
use Illuminate\Support\Collection;

class ResolvedContentTranslations
{
    /**
     * @param  array<string, string>  $values
     */
    private function __construct(private readonly array $values) {}

    /**
     * @param  Collection<int, ContentTranslationDomainObject>  $rows
     * @param  string[]  $localeChain  Most preferred locale first
     */
    public static function fromRows(Collection $rows, array $localeChain): self
    {
        $priorityByLocale = array_flip(array_reverse($localeChain));
        $values = [];

        $rows
            ->sortBy(fn (ContentTranslationDomainObject $row) => $priorityByLocale[$row->getLocale()] ?? -1)
            ->each(function (ContentTranslationDomainObject $row) use (&$values) {
                $values[self::key($row->getTranslatableType(), $row->getTranslatableId(), $row->getField())] = $row->getValue();
            });

        return new self($values);
    }

    public function get(TranslatableContentType $type, int $id, string $field): ?string
    {
        return $this->values[self::key($type->value, $id, $field)] ?? null;
    }

    public function has(TranslatableContentType $type, int $id, string $field): bool
    {
        return $this->get($type, $id, $field) !== null;
    }

    private static function key(string $type, int $id, string $field): string
    {
        return $type.':'.$id.':'.$field;
    }
}
