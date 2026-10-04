<?php

namespace HiEvents\Services\Application\Handlers\Question\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SaveBuyerEditableAnswerDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $event_id,
        public readonly string $order_short_id,
        public readonly int $question_id,
        public readonly ?int $attendee_id,
        public readonly ?int $product_id,
        public readonly array|string|null $answer,
        public readonly string $locale,
    ) {}
}
