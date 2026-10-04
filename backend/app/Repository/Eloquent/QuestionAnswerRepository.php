<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\Generated\QuestionAnswerDomainObjectAbstract;
use HiEvents\DomainObjects\QuestionAnswerDomainObject;
use HiEvents\Models\QuestionAnswer;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;

/**
 * @extends BaseRepository<QuestionAnswerDomainObject>
 */
class QuestionAnswerRepository extends BaseRepository implements QuestionAnswerRepositoryInterface
{
    protected function getModel(): string
    {
        return QuestionAnswer::class;
    }

    public function getDomainObject(): string
    {
        return QuestionAnswerDomainObject::class;
    }

    public function forceDeleteUnansweredForQuestion(int $questionId, ?array $answerIds = null): int
    {
        return $this->runQuery(function () use ($questionId, $answerIds) {
            $query = $this->model
                ->withTrashed()
                ->where(QuestionAnswerDomainObjectAbstract::QUESTION_ID, $questionId)
                ->whereNull(QuestionAnswerDomainObjectAbstract::ANSWER);

            if ($answerIds !== null) {
                $query->whereIn(QuestionAnswerDomainObjectAbstract::ID, $answerIds);
            }

            return $query->forceDelete();
        });
    }
}
