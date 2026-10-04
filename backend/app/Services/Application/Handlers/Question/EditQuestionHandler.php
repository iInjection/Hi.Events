<?php

namespace HiEvents\Services\Application\Handlers\Question;

use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Services\Application\Handlers\Question\DTO\UpsertQuestionDTO;
use HiEvents\Services\Domain\Question\EditQuestionService;
use HiEvents\Services\Domain\Question\QuestionAnswerSyncService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Throwable;

class EditQuestionHandler
{
    public function __construct(
        private readonly EditQuestionService $editQuestionService,
        private readonly HtmlPurifierService $purifier,
        private readonly QuestionAnswerSyncService $questionAnswerSyncService,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(int $questionId, UpsertQuestionDTO $createQuestionDTO): QuestionDomainObject
    {
        $question = (new QuestionDomainObject)
            ->setId($questionId)
            ->setTitle($createQuestionDTO->title)
            ->setEventId($createQuestionDTO->event_id)
            ->setBelongsTo($createQuestionDTO->belongs_to->name)
            ->setType($createQuestionDTO->type->name)
            ->setRequired($createQuestionDTO->required)
            ->setOptions($createQuestionDTO->options)
            ->setIsHidden($createQuestionDTO->is_hidden)
            ->setIsBuyerEditable($createQuestionDTO->is_buyer_editable)
            ->setDescription($this->purifier->purify($createQuestionDTO->description));

        $editedQuestion = $this->editQuestionService->editQuestion(
            question: $question,
            productIds: $createQuestionDTO->product_ids,
        );

        $this->questionAnswerSyncService->syncWithExistingOrders($editedQuestion, $createQuestionDTO->product_ids);

        return $editedQuestion;
    }
}
