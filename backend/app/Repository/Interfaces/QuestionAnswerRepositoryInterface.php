<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\QuestionAnswerDomainObject;

/**
 * @extends RepositoryInterface<QuestionAnswerDomainObject>
 */
interface QuestionAnswerRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  int[]|null  $answerIds  Restrict to these answer rows; null means every unanswered row of the question
     */
    public function forceDeleteUnansweredForQuestion(int $questionId, ?array $answerIds = null): int;
}
