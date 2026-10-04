<?php

namespace HiEvents\Services\Application\Handlers\Question;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Question\BuyerEditableQuestionAnswerService;
use HiEvents\Services\Domain\Question\DTO\BuyerEditableAnswerDTO;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetBuyerEditableAnswersHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly BuyerEditableQuestionAnswerService $buyerEditableQuestionAnswerService,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(int $eventId, string $orderShortId, string $locale): array
    {
        return $this->buyerEditableQuestionAnswerService
            ->getEditableAnswers($this->findOrder($eventId, $orderShortId), $locale)
            ->map(fn (BuyerEditableAnswerDTO $answer) => $answer->toResponseArray())
            ->all();
    }

    public function findOrder(int $eventId, string $orderShortId): OrderDomainObject
    {
        $order = $this->orderRepository->findFirstWhere([
            OrderDomainObjectAbstract::EVENT_ID => $eventId,
            OrderDomainObjectAbstract::SHORT_ID => $orderShortId,
        ]);

        if ($order === null) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        return $order;
    }
}
