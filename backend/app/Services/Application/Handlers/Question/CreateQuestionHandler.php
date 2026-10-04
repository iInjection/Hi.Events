<?php

namespace HiEvents\Services\Application\Handlers\Question;

use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Services\Application\Handlers\Question\DTO\UpsertQuestionDTO;
use HiEvents\Services\Domain\Question\CreateQuestionService;
use HiEvents\Services\Domain\Question\QuestionAnswerSyncService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Throwable;

class CreateQuestionHandler
{
    public function __construct(
        private readonly CreateQuestionService $createQuestionService,
        private readonly HtmlPurifierService $purifier,
        private readonly QuestionAnswerSyncService $questionAnswerSyncService,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(UpsertQuestionDTO $createQuestionDTO): QuestionDomainObject
    {
        $question = (new QuestionDomainObject)
            ->setTitle($createQuestionDTO->title)
            ->setEventId($createQuestionDTO->event_id)
            ->setBelongsTo($createQuestionDTO->belongs_to->name)
            ->setType($createQuestionDTO->type->name)
            ->setRequired($createQuestionDTO->required)
            ->setOptions($createQuestionDTO->options)
            ->setIsHidden($createQuestionDTO->is_hidden)
            ->setDescription($this->purifier->purify($createQuestionDTO->description));

        $createdQuestion = $this->createQuestionService->createQuestion(
            $question,
            $createQuestionDTO->product_ids,
        );

        $this->questionAnswerSyncService->syncWithExistingOrders($createdQuestion, $createQuestionDTO->product_ids);

        return $createdQuestion;
    }
}
