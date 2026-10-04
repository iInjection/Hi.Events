<?php

namespace HiEvents\Services\Domain\Question;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderItemDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\QuestionAnswerDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionAnswerDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;

class QuestionAnswerSyncService
{
    private const SYNCED_ORDER_STATUSES = [
        OrderStatus::COMPLETED->name,
        OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
    ];

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly QuestionAnswerRepositoryInterface $questionAnswerRepository,
    ) {}

    /**
     * @param  int[]  $productIds
     */
    public function syncWithExistingOrders(QuestionDomainObject $question, array $productIds): void
    {
        $orderIds = $this->orderRepository
            ->findWhere([
                OrderDomainObjectAbstract::EVENT_ID => $question->getEventId(),
                [OrderDomainObjectAbstract::STATUS, 'in', self::SYNCED_ORDER_STATUSES],
            ])
            ->map(fn (OrderDomainObject $order) => $order->getId())
            ->all();

        $targets = $question->getBelongsTo() === QuestionBelongsTo::ORDER->name
            ? $this->getOrderTargets($orderIds)
            : $this->getProductTargets($orderIds, $productIds);

        $existingAnswers = $this->questionAnswerRepository->findWhere([
            QuestionAnswerDomainObjectAbstract::QUESTION_ID => $question->getId(),
        ]);

        $existingKeys = $existingAnswers
            ->map(fn (QuestionAnswerDomainObject $answer) => self::key(
                $answer->getOrderId(),
                $answer->getAttendeeId(),
                $answer->getProductId(),
            ))
            ->flip();

        $missingTargets = array_values(array_diff_key($targets, $existingKeys->all()));

        if ($missingTargets !== []) {
            $this->questionAnswerRepository->insert(array_map(fn (array $target) => [
                ...$target,
                QuestionAnswerDomainObjectAbstract::QUESTION_ID => $question->getId(),
                QuestionAnswerDomainObjectAbstract::ANSWER => null,
            ], $missingTargets));
        }

        $obsoleteAnswerIds = $existingAnswers
            ->filter(fn (QuestionAnswerDomainObject $answer) => $answer->getAnswer() === null
                && ! array_key_exists(self::key($answer->getOrderId(), $answer->getAttendeeId(), $answer->getProductId()), $targets))
            ->map(fn (QuestionAnswerDomainObject $answer) => $answer->getId())
            ->values()
            ->all();

        if ($obsoleteAnswerIds !== []) {
            $this->questionAnswerRepository->forceDeleteUnansweredForQuestion($question->getId(), $obsoleteAnswerIds);
        }
    }

    /**
     * @param  int[]  $orderIds
     * @return array<string, array{order_id: int, attendee_id: null, product_id: null}>
     */
    private function getOrderTargets(array $orderIds): array
    {
        $targets = [];

        foreach ($orderIds as $orderId) {
            $targets[self::key($orderId, null, null)] = [
                QuestionAnswerDomainObjectAbstract::ORDER_ID => $orderId,
                QuestionAnswerDomainObjectAbstract::ATTENDEE_ID => null,
                QuestionAnswerDomainObjectAbstract::PRODUCT_ID => null,
            ];
        }

        return $targets;
    }

    /**
     * @param  int[]  $orderIds
     * @param  int[]  $productIds
     * @return array<string, array{order_id: int, attendee_id: ?int, product_id: int}>
     */
    private function getProductTargets(array $orderIds, array $productIds): array
    {
        if ($orderIds === [] || $productIds === []) {
            return [];
        }

        $productsByType = $this->productRepository
            ->findWhereIn('id', $productIds)
            ->groupBy(fn (ProductDomainObject $product) => $product->getProductType());

        $ticketIds = $productsByType->get(ProductType::TICKET->name, collect())
            ->map(fn (ProductDomainObject $product) => $product->getId())
            ->all();

        $generalProductIds = $productsByType->get(ProductType::GENERAL->name, collect())
            ->map(fn (ProductDomainObject $product) => $product->getId())
            ->all();

        $targets = [];

        if ($ticketIds !== []) {
            $this->attendeeRepository
                ->findWhere([
                    [AttendeeDomainObjectAbstract::ORDER_ID, 'in', $orderIds],
                    [AttendeeDomainObjectAbstract::PRODUCT_ID, 'in', $ticketIds],
                    [AttendeeDomainObjectAbstract::STATUS, '!=', AttendeeStatus::CANCELLED->name],
                ])
                ->each(function (AttendeeDomainObject $attendee) use (&$targets) {
                    $targets[self::key($attendee->getOrderId(), $attendee->getId(), $attendee->getProductId())] = [
                        QuestionAnswerDomainObjectAbstract::ORDER_ID => $attendee->getOrderId(),
                        QuestionAnswerDomainObjectAbstract::ATTENDEE_ID => $attendee->getId(),
                        QuestionAnswerDomainObjectAbstract::PRODUCT_ID => $attendee->getProductId(),
                    ];
                });
        }

        if ($generalProductIds !== []) {
            $this->orderItemRepository
                ->findWhere([
                    [OrderItemDomainObjectAbstract::ORDER_ID, 'in', $orderIds],
                    [OrderItemDomainObjectAbstract::PRODUCT_ID, 'in', $generalProductIds],
                ])
                ->each(function (OrderItemDomainObject $orderItem) use (&$targets) {
                    $targets[self::key($orderItem->getOrderId(), null, $orderItem->getProductId())] = [
                        QuestionAnswerDomainObjectAbstract::ORDER_ID => $orderItem->getOrderId(),
                        QuestionAnswerDomainObjectAbstract::ATTENDEE_ID => null,
                        QuestionAnswerDomainObjectAbstract::PRODUCT_ID => $orderItem->getProductId(),
                    ];
                });
        }

        return $targets;
    }

    private static function key(int $orderId, ?int $attendeeId, ?int $productId): string
    {
        return $orderId.':'.($attendeeId ?? '').':'.($productId ?? '');
    }
}
