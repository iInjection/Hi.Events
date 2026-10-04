<?php

namespace HiEvents\Services\Domain\Question;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderItemDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\QuestionAnswerDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionAnswerDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Domain\ContentTranslation\ContentTranslationService;
use HiEvents\Services\Domain\Question\DTO\BuyerEditableAnswerDTO;
use HiEvents\Services\Domain\Question\Exception\InvalidAnswerException;
use Illuminate\Support\Collection;

class BuyerEditableQuestionAnswerService
{
    private const EDITABLE_ORDER_STATUSES = [
        OrderStatus::COMPLETED->name,
        OrderStatus::AWAITING_OFFLINE_PAYMENT->name,
    ];

    public function __construct(
        private readonly QuestionRepositoryInterface $questionRepository,
        private readonly QuestionAnswerRepositoryInterface $questionAnswerRepository,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly OrderItemRepositoryInterface $orderItemRepository,
        private readonly ContentTranslationService $contentTranslationService,
    ) {}

    /**
     * @return Collection<int, BuyerEditableAnswerDTO>
     */
    public function getEditableAnswers(OrderDomainObject $order, string $locale): Collection
    {
        if (! in_array($order->getStatus(), self::EDITABLE_ORDER_STATUSES, true)) {
            return collect();
        }

        $questions = $this->questionRepository
            ->loadRelation(ProductDomainObject::class)
            ->findWhere(
                where: [
                    QuestionDomainObjectAbstract::EVENT_ID => $order->getEventId(),
                    QuestionDomainObjectAbstract::IS_BUYER_EDITABLE => true,
                    QuestionDomainObjectAbstract::IS_HIDDEN => false,
                ],
                orderAndDirections: [new OrderAndDirection(QuestionDomainObjectAbstract::ORDER, 'asc')],
            );

        if ($questions->isEmpty()) {
            return collect();
        }

        $this->contentTranslationService->translateQuestions($order->getEventId(), $questions, $locale);

        $answersByKey = $this->questionAnswerRepository
            ->findWhere([
                QuestionAnswerDomainObjectAbstract::ORDER_ID => $order->getId(),
                [QuestionAnswerDomainObjectAbstract::QUESTION_ID, 'in', $questions->map(fn (QuestionDomainObject $question) => $question->getId())->all()],
            ])
            ->keyBy(fn (QuestionAnswerDomainObject $answer) => self::key($answer->getQuestionId(), $answer->getAttendeeId(), $answer->getProductId()));

        $attendees = $this->attendeeRepository->findWhere([
            AttendeeDomainObjectAbstract::ORDER_ID => $order->getId(),
            [AttendeeDomainObjectAbstract::STATUS, '!=', AttendeeStatus::CANCELLED->name],
        ]);

        $orderItems = $this->orderItemRepository->findWhere([
            OrderItemDomainObjectAbstract::ORDER_ID => $order->getId(),
        ]);

        $this->contentTranslationService->translateOrder($order->setOrderItems($orderItems), $locale);

        return $questions
            ->flatMap(fn (QuestionDomainObject $question) => $this->buildTargets($question, $attendees, $orderItems))
            ->map(fn (array $target) => new BuyerEditableAnswerDTO(
                question: $target['question'],
                attendeeId: $target['attendee_id'],
                attendeeName: $target['attendee_name'],
                productId: $target['product_id'],
                productTitle: $target['product_title'],
                answer: $answersByKey->get(self::key($target['question']->getId(), $target['attendee_id'], $target['product_id']))?->getAnswer(),
            ))
            ->values();
    }

    /**
     * @throws InvalidAnswerException
     */
    public function saveAnswer(
        OrderDomainObject $order,
        int $questionId,
        ?int $attendeeId,
        ?int $productId,
        array|string|null $answer,
        string $locale,
    ): void {
        $target = $this->getEditableAnswers($order, $locale)
            ->first(fn (BuyerEditableAnswerDTO $editable) => $editable->matches($questionId, $attendeeId, $productId));

        if ($target === null) {
            throw new InvalidAnswerException(__('This question cannot be answered for this order'));
        }

        $answer = self::isEmptyAnswer($answer) ? null : $answer;

        if ($answer === null && $target->question->getRequired()) {
            throw new InvalidAnswerException(__('This question is required'));
        }

        if ($answer !== null && ! $target->question->isAnswerValid($answer)) {
            throw new InvalidAnswerException(__('Please provide a valid answer'));
        }

        $where = [
            QuestionAnswerDomainObjectAbstract::QUESTION_ID => $questionId,
            QuestionAnswerDomainObjectAbstract::ORDER_ID => $order->getId(),
            $attendeeId === null
                ? [QuestionAnswerDomainObjectAbstract::ATTENDEE_ID, 'null', null]
                : [QuestionAnswerDomainObjectAbstract::ATTENDEE_ID, '=', $attendeeId],
            $productId === null
                ? [QuestionAnswerDomainObjectAbstract::PRODUCT_ID, 'null', null]
                : [QuestionAnswerDomainObjectAbstract::PRODUCT_ID, '=', $productId],
        ];

        $existing = $this->questionAnswerRepository->findFirstWhere($where);

        if ($existing === null) {
            $this->questionAnswerRepository->create([
                QuestionAnswerDomainObjectAbstract::QUESTION_ID => $questionId,
                QuestionAnswerDomainObjectAbstract::ORDER_ID => $order->getId(),
                QuestionAnswerDomainObjectAbstract::ATTENDEE_ID => $attendeeId,
                QuestionAnswerDomainObjectAbstract::PRODUCT_ID => $productId,
                QuestionAnswerDomainObjectAbstract::ANSWER => $answer,
            ]);

            return;
        }

        $this->questionAnswerRepository->updateWhere(
            attributes: [
                QuestionAnswerDomainObjectAbstract::ANSWER => $answer === null ? null : json_encode($answer, JSON_THROW_ON_ERROR),
            ],
            where: [QuestionAnswerDomainObjectAbstract::ID => $existing->getId()],
        );
    }

    /**
     * @param  Collection<int, AttendeeDomainObject>  $attendees
     * @param  Collection<int, OrderItemDomainObject>  $orderItems
     * @return array<int, array{question: QuestionDomainObject, attendee_id: ?int, attendee_name: ?string, product_id: ?int, product_title: ?string}>
     */
    private function buildTargets(QuestionDomainObject $question, Collection $attendees, Collection $orderItems): array
    {
        if ($question->getBelongsTo() === QuestionBelongsTo::ORDER->name) {
            return [[
                'question' => $question,
                'attendee_id' => null,
                'attendee_name' => null,
                'product_id' => null,
                'product_title' => null,
            ]];
        }

        $productIds = $question->getProducts()?->map(fn (ProductDomainObject $product) => $product->getId())->all() ?? [];
        $targets = [];

        $orderItems
            ->filter(fn (OrderItemDomainObject $item) => in_array($item->getProductId(), $productIds, true))
            ->unique(fn (OrderItemDomainObject $item) => $item->getProductId())
            ->each(function (OrderItemDomainObject $item) use ($question, $attendees, &$targets) {
                $productAttendees = $attendees->filter(
                    fn (AttendeeDomainObject $attendee) => $attendee->getProductId() === $item->getProductId(),
                );

                if ($productAttendees->isEmpty()) {
                    $targets[] = [
                        'question' => $question,
                        'attendee_id' => null,
                        'attendee_name' => null,
                        'product_id' => $item->getProductId(),
                        'product_title' => $item->getItemName(),
                    ];

                    return;
                }

                $productAttendees->each(function (AttendeeDomainObject $attendee) use ($question, $item, &$targets) {
                    $targets[] = [
                        'question' => $question,
                        'attendee_id' => $attendee->getId(),
                        'attendee_name' => trim($attendee->getFirstName().' '.$attendee->getLastName()),
                        'product_id' => $item->getProductId(),
                        'product_title' => $item->getItemName(),
                    ];
                });
            });

        return $targets;
    }

    private static function isEmptyAnswer(array|string|null $answer): bool
    {
        if (is_array($answer)) {
            return collect($answer)->flatten()->filter(fn ($value) => trim((string) $value) !== '')->isEmpty();
        }

        return $answer === null || trim($answer) === '';
    }

    private static function key(int $questionId, ?int $attendeeId, ?int $productId): string
    {
        return $questionId.':'.($attendeeId ?? '').':'.($productId ?? '');
    }
}
