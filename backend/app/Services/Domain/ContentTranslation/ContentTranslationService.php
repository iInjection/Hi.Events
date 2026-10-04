<?php

namespace HiEvents\Services\Domain\ContentTranslation;

use HiEvents\DomainObjects\AbstractDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\TranslatableContentType;
use HiEvents\DomainObjects\Enums\TranslatableFieldFormat;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventTranslationSettingDomainObject;
use HiEvents\DomainObjects\Generated\ContentTranslationDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventTranslationSettingDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Interfaces\ContentTranslationRepositoryInterface;
use HiEvents\Repository\Interfaces\EventTranslationSettingRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ContentTranslationService
{
    public function __construct(
        private readonly EventTranslationSettingRepositoryInterface $settingRepository,
        private readonly ContentTranslationRepositoryInterface $translationRepository,
        private readonly ProductRepositoryInterface $productRepository,
    ) {}

    public function translateEvent(EventDomainObject $event, string $locale): void
    {
        $translations = $this->resolveTranslations($event->getId(), $locale);

        if ($translations !== null) {
            $this->applyToEvent($event, $translations);
        }
    }

    /**
     * @param  Collection<int, QuestionDomainObject>  $questions
     */
    public function translateQuestions(int $eventId, Collection $questions, string $locale): void
    {
        $translations = $this->resolveTranslations($eventId, $locale);

        if ($translations !== null) {
            $questions->each(fn (QuestionDomainObject $question) => $this->applyToQuestion($question, $translations));
        }
    }

    public function translateOrder(OrderDomainObject $order, string $locale): void
    {
        $translations = $this->resolveTranslations($order->getEventId(), $locale);

        if ($translations === null) {
            return;
        }

        if ($order->getEvent() !== null) {
            $this->applyToEvent($order->getEvent(), $translations);
        }

        $order->getAttendees()?->each(
            fn (AttendeeDomainObject $attendee) => $this->applyToProduct($attendee->getProduct(), $translations),
        );

        $this->translateOrderItemNames($order, $translations);
    }

    /**
     * @return string[] Locales to look up, most preferred first
     */
    public function getLocaleChain(EventTranslationSettingDomainObject $settings, string $locale): array
    {
        if ($locale === $settings->getSourceLocale()) {
            return [];
        }

        return array_values(array_unique(array_filter(
            [$locale, $settings->getFallbackLocale()],
            fn (?string $candidate) => $candidate !== null && $candidate !== $settings->getSourceLocale(),
        )));
    }

    private function resolveTranslations(int $eventId, string $locale): ?ResolvedContentTranslations
    {
        $settings = $this->settingRepository->findFirstWhere([
            EventTranslationSettingDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($settings === null) {
            return null;
        }

        $localeChain = $this->getLocaleChain($settings, $locale);

        if ($localeChain === []) {
            return null;
        }

        return ResolvedContentTranslations::fromRows(
            $this->translationRepository->findWhere([
                ContentTranslationDomainObjectAbstract::EVENT_ID => $eventId,
                [ContentTranslationDomainObjectAbstract::LOCALE, 'in', $localeChain],
            ]),
            $localeChain,
        );
    }

    private function applyToEvent(EventDomainObject $event, ResolvedContentTranslations $translations): void
    {
        $event->setSlug($event->getSlug());
        $this->applyFields($event, TranslatableContentType::EVENT, $translations);
        $this->applyFields($event->getEventSettings(), TranslatableContentType::EVENT_SETTING, $translations);

        $event->getProductCategories()?->each(function (ProductCategoryDomainObject $category) use ($translations) {
            $this->applyFields($category, TranslatableContentType::PRODUCT_CATEGORY, $translations);
            $category->getProducts()?->each(fn (ProductDomainObject $product) => $this->applyToProduct($product, $translations));
        });

        $event->getProducts()?->each(fn (ProductDomainObject $product) => $this->applyToProduct($product, $translations));
    }

    private function applyToProduct(?ProductDomainObject $product, ResolvedContentTranslations $translations): void
    {
        if ($product === null) {
            return;
        }

        $this->applyFields($product, TranslatableContentType::PRODUCT, $translations);

        $product->getProductPrices()?->each(
            fn (ProductPriceDomainObject $price) => $this->applyFields($price, TranslatableContentType::PRODUCT_PRICE, $translations),
        );

        $product->getAddons()?->each(fn (ProductDomainObject $addon) => $this->applyToProduct($addon, $translations));
    }

    private function applyToQuestion(QuestionDomainObject $question, ResolvedContentTranslations $translations): void
    {
        $this->applyFields($question, TranslatableContentType::QUESTION, $translations);

        $options = $question->getOptions();
        $translatedOptions = json_decode(
            $translations->get(TranslatableContentType::QUESTION, $question->getId(), 'options') ?? 'null',
            true,
        );

        if (! is_array($options) || ! is_array($translatedOptions)) {
            return;
        }

        $options = array_values($options);

        if (count($options) !== count($translatedOptions)) {
            return;
        }

        $question->setOptionLabels(array_map(
            fn (?string $label, string $option) => $label !== null && trim($label) !== '' ? $label : $option,
            array_values($translatedOptions),
            $options,
        ));
    }

    private function applyFields(
        ?AbstractDomainObject $object,
        TranslatableContentType $type,
        ResolvedContentTranslations $translations,
    ): void {
        if ($object === null) {
            return;
        }

        foreach ($type->fields() as $field => $format) {
            if ($format === TranslatableFieldFormat::LIST) {
                continue;
            }

            $value = $translations->get($type, $object->getId(), $field);

            if ($value !== null) {
                $object->{'set'.Str::studly($field)}($value);
            }
        }
    }

    private function translateOrderItemNames(OrderDomainObject $order, ResolvedContentTranslations $translations): void
    {
        $translatedItems = $order->getOrderItems()?->filter(
            fn (OrderItemDomainObject $item) => $translations->has(TranslatableContentType::PRODUCT, $item->getProductId(), 'title')
                || $translations->has(TranslatableContentType::PRODUCT_PRICE, $item->getProductPriceId(), 'label'),
        );

        if ($translatedItems === null || $translatedItems->isEmpty()) {
            return;
        }

        $products = $this->productRepository
            ->loadRelation(ProductPriceDomainObject::class)
            ->findWhereIn('id', $translatedItems->map(fn (OrderItemDomainObject $item) => $item->getProductId())->unique()->values()->all())
            ->keyBy(fn (ProductDomainObject $product) => $product->getId());

        $translatedItems->each(function (OrderItemDomainObject $item) use ($products, $translations) {
            /** @var ProductDomainObject|null $product */
            $product = $products->get($item->getProductId());

            if ($product === null) {
                return;
            }

            $this->applyToProduct($product, $translations);

            $priceLabel = $product->getProductPrices()
                ?->first(fn (ProductPriceDomainObject $price) => $price->getId() === $item->getProductPriceId())
                ?->getLabel();

            $item->setItemName($product->isTieredType() && $priceLabel
                ? $product->getTitle().' - '.$priceLabel
                : $product->getTitle());
        });
    }
}
