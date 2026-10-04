<?php

namespace HiEvents\Services\Application\Handlers\Question;

use HiEvents\Services\Application\Handlers\Question\DTO\SaveBuyerEditableAnswerDTO;
use HiEvents\Services\Domain\Question\BuyerEditableQuestionAnswerService;
use HiEvents\Services\Domain\Question\Exception\InvalidAnswerException;

class SaveBuyerEditableAnswerHandler
{
    public function __construct(
        private readonly GetBuyerEditableAnswersHandler $getBuyerEditableAnswersHandler,
        private readonly BuyerEditableQuestionAnswerService $buyerEditableQuestionAnswerService,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws InvalidAnswerException
     */
    public function handle(SaveBuyerEditableAnswerDTO $dto): array
    {
        $this->buyerEditableQuestionAnswerService->saveAnswer(
            order: $this->getBuyerEditableAnswersHandler->findOrder($dto->event_id, $dto->order_short_id),
            questionId: $dto->question_id,
            attendeeId: $dto->attendee_id,
            productId: $dto->product_id,
            answer: $dto->answer,
            locale: $dto->locale,
        );

        return $this->getBuyerEditableAnswersHandler->handle($dto->event_id, $dto->order_short_id, $dto->locale);
    }
}
