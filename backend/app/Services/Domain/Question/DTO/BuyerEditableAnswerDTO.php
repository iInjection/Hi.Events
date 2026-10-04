<?php

namespace HiEvents\Services\Domain\Question\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\QuestionDomainObject;

class BuyerEditableAnswerDTO extends BaseDataObject
{
    public function __construct(
        public readonly QuestionDomainObject $question,
        public readonly ?int $attendeeId,
        public readonly ?string $attendeeName,
        public readonly ?int $productId,
        public readonly ?string $productTitle,
        public readonly array|string|null $answer,
    ) {}

    public function matches(int $questionId, ?int $attendeeId, ?int $productId): bool
    {
        return $this->question->getId() === $questionId
            && $this->attendeeId === $attendeeId
            && $this->productId === $productId;
    }

    public function toResponseArray(): array
    {
        return [
            'question_id' => $this->question->getId(),
            'title' => $this->question->getTitle(),
            'description' => $this->question->getDescription(),
            'type' => $this->question->getType(),
            'required' => $this->question->getRequired(),
            'options' => is_array($this->question->getOptions()) ? array_values($this->question->getOptions()) : [],
            'option_labels' => $this->question->getOptionLabels(),
            'attendee_id' => $this->attendeeId,
            'attendee_name' => $this->attendeeName,
            'product_id' => $this->productId,
            'product_title' => $this->productTitle,
            'answer' => $this->answer,
        ];
    }
}
