<?php

namespace Tests\Unit\Services\Domain\ContentTranslation;

use HiEvents\DomainObjects\ContentTranslationDomainObject;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\EventTranslationSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Interfaces\ContentTranslationRepositoryInterface;
use HiEvents\Repository\Interfaces\EventTranslationSettingRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\ContentTranslation\ContentTranslationService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ContentTranslationServiceTest extends TestCase
{
    private EventTranslationSettingRepositoryInterface|MockInterface $settingRepository;

    private ContentTranslationRepositoryInterface|MockInterface $translationRepository;

    private ProductRepositoryInterface|MockInterface $productRepository;

    private ContentTranslationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingRepository = Mockery::mock(EventTranslationSettingRepositoryInterface::class);
        $this->translationRepository = Mockery::mock(ContentTranslationRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);

        $this->service = new ContentTranslationService(
            $this->settingRepository,
            $this->translationRepository,
            $this->productRepository,
        );
    }

    public function test_locale_chain_uses_requested_locale_then_fallback(): void
    {
        $settings = $this->settings(source: 'de', fallback: 'en');

        $this->assertSame([], $this->service->getLocaleChain($settings, 'de'));
        $this->assertSame(['nl', 'en'], $this->service->getLocaleChain($settings, 'nl'));
        $this->assertSame(['en'], $this->service->getLocaleChain($settings, 'en'));
        $this->assertSame(['fr'], $this->service->getLocaleChain($this->settings(source: 'de', fallback: null), 'fr'));
    }

    public function test_translates_event_products_and_settings_with_fallback(): void
    {
        $this->mockSettings($this->settings(source: 'de', fallback: 'en'));
        $this->mockTranslations([
            $this->translation('event', 1, 'title', 'en', 'Dog Show'),
            $this->translation('event', 1, 'title', 'nl', 'Hondenshow'),
            $this->translation('event_setting', 7, 'continue_button_text', 'en', 'Continue'),
            $this->translation('product_category', 3, 'name', 'en', 'Tickets'),
            $this->translation('product', 10, 'title', 'en', 'Adult'),
            $this->translation('product_price', 100, 'label', 'en', 'Early bird'),
        ]);

        $price = (new ProductPriceDomainObject)->setId(100)->setLabel('Frühbucher');
        $product = (new ProductDomainObject)->setId(10)->setTitle('Erwachsene')->setDescription('Beschreibung')
            ->setProductPrices(collect([$price]));
        $category = (new ProductCategoryDomainObject)->setId(3)->setName('Karten');
        $category->setProducts(collect([$product]));
        $settings = (new EventSettingDomainObject)->setId(7)->setContinueButtonText('Weiter');
        $event = (new EventDomainObject)->setId(1)->setTitle('Hundeschau')->setDescription('Text')
            ->setEventSettings($settings)
            ->setProductCategories(collect([$category]));

        $this->service->translateEvent($event, 'nl');

        $this->assertSame('Hondenshow', $event->getTitle());
        $this->assertSame('hundeschau', $event->getSlug());
        $this->assertSame('Text', $event->getDescription());
        $this->assertSame('Continue', $settings->getContinueButtonText());
        $this->assertSame('Tickets', $category->getName());
        $this->assertSame('Adult', $product->getTitle());
        $this->assertSame('Beschreibung', $product->getDescription());
        $this->assertSame('Early bird', $price->getLabel());
    }

    public function test_does_not_translate_when_event_has_no_translation_settings(): void
    {
        $this->settingRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $this->translationRepository->shouldNotReceive('findWhere');

        $event = (new EventDomainObject)->setId(1)->setTitle('Hundeschau');

        $this->service->translateEvent($event, 'en');

        $this->assertSame('Hundeschau', $event->getTitle());
    }

    public function test_does_not_load_translations_for_the_source_locale(): void
    {
        $this->mockSettings($this->settings(source: 'de', fallback: 'en'));
        $this->translationRepository->shouldNotReceive('findWhere');

        $event = (new EventDomainObject)->setId(1)->setTitle('Hundeschau');

        $this->service->translateEvent($event, 'de');

        $this->assertSame('Hundeschau', $event->getTitle());
    }

    public function test_translates_question_option_labels_and_keeps_option_values(): void
    {
        $this->mockSettings($this->settings(source: 'de', fallback: null));
        $this->mockTranslations([
            $this->translation('question', 5, 'title', 'en', 'Dog size'),
            $this->translation('question', 5, 'options', 'en', json_encode(['Small', ''])),
            $this->translation('question', 6, 'options', 'en', json_encode(['Only one'])),
        ]);

        $question = (new QuestionDomainObject)->setId(5)->setTitle('Hundegröße')->setOptions(['Klein', 'Groß']);
        $mismatchedQuestion = (new QuestionDomainObject)->setId(6)->setTitle('Futter')->setOptions(['Trocken', 'Nass']);

        $this->service->translateQuestions(1, collect([$question, $mismatchedQuestion]), 'en');

        $this->assertSame('Dog size', $question->getTitle());
        $this->assertSame(['Klein', 'Groß'], array_values($question->getOptions()));
        $this->assertSame(['Small', 'Groß'], $question->getOptionLabels());
        $this->assertNull($mismatchedQuestion->getOptionLabels());
    }

    public function test_rebuilds_order_item_names_from_translated_products(): void
    {
        $this->mockSettings($this->settings(source: 'de', fallback: null));
        $this->mockTranslations([
            $this->translation('product', 10, 'title', 'en', 'Adult'),
            $this->translation('product_price', 100, 'label', 'en', 'Early bird'),
        ]);

        $product = (new ProductDomainObject)->setId(10)->setTitle('Erwachsene')->setType(ProductPriceType::TIERED->name)
            ->setProductPrices(collect([(new ProductPriceDomainObject)->setId(100)->setLabel('Frühbucher')]));

        $this->productRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->productRepository->shouldReceive('findWhereIn')->with('id', [10])->andReturn(collect([$product]));

        $translatedItem = (new OrderItemDomainObject)->setProductId(10)->setProductPriceId(100)->setItemName('Erwachsene - Frühbucher');
        $untranslatedItem = (new OrderItemDomainObject)->setProductId(11)->setProductPriceId(110)->setItemName('Hundenapf');
        $order = (new OrderDomainObject)->setEventId(1)->setOrderItems(collect([$translatedItem, $untranslatedItem]));

        $this->service->translateOrder($order, 'en');

        $this->assertSame('Adult - Early bird', $translatedItem->getItemName());
        $this->assertSame('Hundenapf', $untranslatedItem->getItemName());
    }

    private function settings(string $source, ?string $fallback): EventTranslationSettingDomainObject
    {
        return (new EventTranslationSettingDomainObject)
            ->setEventId(1)
            ->setSourceLocale($source)
            ->setFallbackLocale($fallback)
            ->setLocales(['en', 'nl']);
    }

    private function mockSettings(EventTranslationSettingDomainObject $settings): void
    {
        $this->settingRepository->shouldReceive('findFirstWhere')->andReturn($settings);
    }

    private function mockTranslations(array $translations): void
    {
        $this->translationRepository->shouldReceive('findWhere')->andReturn(collect($translations));
    }

    private function translation(string $type, int $id, string $field, string $locale, string $value): ContentTranslationDomainObject
    {
        return (new ContentTranslationDomainObject)
            ->setEventId(1)
            ->setTranslatableType($type)
            ->setTranslatableId($id)
            ->setField($field)
            ->setLocale($locale)
            ->setValue($value);
    }
}
