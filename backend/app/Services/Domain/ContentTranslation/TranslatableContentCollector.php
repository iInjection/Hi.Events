<?php

namespace HiEvents\Services\Domain\ContentTranslation;

use HiEvents\DomainObjects\AbstractDomainObject;
use HiEvents\DomainObjects\Enums\TranslatableContentType;
use HiEvents\DomainObjects\Enums\TranslatableFieldFormat;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Domain\ContentTranslation\DTO\TranslatableContentItemDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TranslatableContentCollector
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly QuestionRepositoryInterface $questionRepository,
    ) {}

    /**
     * @return Collection<int, TranslatableContentItemDTO>
     */
    public function collect(int $eventId): Collection
    {
        $event = $this->eventRepository
            ->loadRelation(new Relationship(ProductCategoryDomainObject::class, [
                new Relationship(
                    ProductDomainObject::class,
                    nested: [new Relationship(ProductPriceDomainObject::class)],
                    orderAndDirections: [new OrderAndDirection('order', 'asc')],
                ),
            ]))
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->findById($eventId);

        $items = collect();

        $this->addItems($items, $event, TranslatableContentType::EVENT);
        $this->addItems($items, $event->getEventSettings(), TranslatableContentType::EVENT_SETTING);

        $event->getProductCategories()?->each(function (ProductCategoryDomainObject $category) use ($items) {
            $this->addItems($items, $category, TranslatableContentType::PRODUCT_CATEGORY, $category->getName());

            $category->getProducts()?->each(function (ProductDomainObject $product) use ($items) {
                $this->addItems($items, $product, TranslatableContentType::PRODUCT, $product->getTitle());

                $product->getProductPrices()?->each(
                    fn (ProductPriceDomainObject $price) => $this->addItems($items, $price, TranslatableContentType::PRODUCT_PRICE, $product->getTitle()),
                );
            });
        });

        $this->questionRepository
            ->findWhere(
                where: [QuestionDomainObjectAbstract::EVENT_ID => $eventId],
                orderAndDirections: [new OrderAndDirection(QuestionDomainObjectAbstract::ORDER, 'asc')],
            )
            ->each(fn (QuestionDomainObject $question) => $this->addItems($items, $question, TranslatableContentType::QUESTION, $question->getTitle()));

        return $items;
    }

    /**
     * @param  Collection<int, TranslatableContentItemDTO>  $items
     */
    private function addItems(
        Collection $items,
        ?AbstractDomainObject $object,
        TranslatableContentType $type,
        ?string $context = null,
    ): void {
        if ($object === null) {
            return;
        }

        foreach ($type->fields() as $field => $format) {
            $source = $object->{'get'.Str::studly($field)}();

            if ($format === TranslatableFieldFormat::LIST) {
                $source = is_array($source) ? array_values($source) : [];
                if ($source === []) {
                    continue;
                }
            } elseif (! is_string($source) || trim(strip_tags($source)) === '') {
                continue;
            }

            $items->push(new TranslatableContentItemDTO(
                type: $type,
                id: $object->getId(),
                field: $field,
                format: $format,
                source: $source,
                context: $context,
            ));
        }
    }
}
