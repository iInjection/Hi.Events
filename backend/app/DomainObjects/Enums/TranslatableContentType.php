<?php

namespace HiEvents\DomainObjects\Enums;

enum TranslatableContentType: string
{
    use BaseEnum;

    case EVENT = 'event';
    case EVENT_SETTING = 'event_setting';
    case PRODUCT_CATEGORY = 'product_category';
    case PRODUCT = 'product';
    case PRODUCT_PRICE = 'product_price';
    case QUESTION = 'question';

    /**
     * @return array<string, TranslatableFieldFormat>
     */
    public function fields(): array
    {
        return match ($this) {
            self::EVENT => [
                'title' => TranslatableFieldFormat::TEXT,
                'description' => TranslatableFieldFormat::HTML,
            ],
            self::EVENT_SETTING => [
                'pre_checkout_message' => TranslatableFieldFormat::HTML,
                'post_checkout_message' => TranslatableFieldFormat::HTML,
                'product_page_message' => TranslatableFieldFormat::MULTILINE,
                'continue_button_text' => TranslatableFieldFormat::TEXT,
                'get_tickets_button_text' => TranslatableFieldFormat::TEXT,
                'offline_payment_instructions' => TranslatableFieldFormat::HTML,
                'online_event_connection_details' => TranslatableFieldFormat::HTML,
            ],
            self::PRODUCT_CATEGORY => [
                'name' => TranslatableFieldFormat::TEXT,
                'description' => TranslatableFieldFormat::HTML,
                'no_products_message' => TranslatableFieldFormat::TEXT,
            ],
            self::PRODUCT => [
                'title' => TranslatableFieldFormat::TEXT,
                'description' => TranslatableFieldFormat::HTML,
                'highlight_message' => TranslatableFieldFormat::TEXT,
            ],
            self::PRODUCT_PRICE => [
                'label' => TranslatableFieldFormat::TEXT,
            ],
            self::QUESTION => [
                'title' => TranslatableFieldFormat::TEXT,
                'description' => TranslatableFieldFormat::HTML,
                'options' => TranslatableFieldFormat::LIST,
            ],
        };
    }
}
