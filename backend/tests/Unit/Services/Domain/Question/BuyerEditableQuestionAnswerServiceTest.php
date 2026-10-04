<?php

namespace Tests\Unit\Services\Domain\Question;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionAnswerDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Domain\ContentTranslation\ContentTranslationService;
use HiEvents\Services\Domain\Question\BuyerEditableQuestionAnswerService;
use HiEvents\Services\Domain\Question\DTO\BuyerEditableAnswerDTO;
use HiEvents\Services\Domain\Question\Exception\InvalidAnswerException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BuyerEditableQuestionAnswerServiceTest extends TestCase
{
    private QuestionRepositoryInterface|MockInterface $questionRepository;

    private QuestionAnswerRepositoryInterface|MockInterface $questionAnswerRepository;

    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private OrderItemRepositoryInterface|MockInterface $orderItemRepository;

    private BuyerEditableQuestionAnswerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->questionRepository = Mockery::mock(QuestionRepositoryInterface::class);
        $this->questionAnswerRepository = Mockery::mock(QuestionAnswerRepositoryInterface::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->orderItemRepository = Mockery::mock(OrderItemRepositoryInterface::class);
        $translationService = Mockery::mock(ContentTranslationService::class);
        $translationService->shouldReceive('translateQuestions');
        $translationService->shouldReceive('translateOrder');

        $this->service = new BuyerEditableQuestionAnswerService(
            $this->questionRepository,
            $this->questionAnswerRepository,
            $this->attendeeRepository,
            $this->orderItemRepository,
            $translationService,
        );
    }

    public function test_lists_order_questions_once_and_product_questions_per_attendee_or_product(): void
    {
        $this->mockQuestions([
            $this->question(1, QuestionBelongsTo::ORDER),
            $this->question(2, QuestionBelongsTo::PRODUCT, productIds: [10, 20]),
        ]);
        $this->mockOrderContents(
            attendees: [
                (new AttendeeDomainObject)->setId(100)->setProductId(10)->setFirstName('Lina')->setLastName('Test'),
                (new AttendeeDomainObject)->setId(101)->setProductId(10)->setFirstName('Max')->setLastName('Test'),
            ],
            orderItems: [
                (new OrderItemDomainObject)->setProductId(10)->setItemName('Ticket'),
                (new OrderItemDomainObject)->setProductId(20)->setItemName('Dog bowl'),
            ],
        );
        $this->questionAnswerRepository->shouldReceive('findWhere')->andReturn(collect([
            (new QuestionAnswerDomainObject)->setQuestionId(2)->setOrderId(5)->setAttendeeId(101)->setProductId(10)->setAnswer('Bello'),
        ]));

        $answers = $this->service->getEditableAnswers($this->order(), 'en');

        $this->assertSame([
            [1, null, null, null],
            [2, 100, 10, null],
            [2, 101, 10, 'Bello'],
            [2, null, 20, null],
        ], $answers->map(fn (BuyerEditableAnswerDTO $answer) => [
            $answer->question->getId(),
            $answer->attendeeId,
            $answer->productId,
            $answer->answer,
        ])->all());
        $this->assertSame('Max Test', $answers[2]->attendeeName);
    }

    public function test_returns_nothing_for_orders_that_are_not_completed(): void
    {
        $this->questionRepository->shouldNotReceive('findWhere');

        $answers = $this->service->getEditableAnswers($this->order(OrderStatus::CANCELLED), 'en');

        $this->assertTrue($answers->isEmpty());
    }

    public function test_rejects_clearing_a_required_question(): void
    {
        $this->mockQuestions([$this->question(1, QuestionBelongsTo::ORDER, required: true)]);
        $this->mockOrderContents([], []);
        $this->questionAnswerRepository->shouldReceive('findWhere')->andReturn(collect());

        $this->expectException(InvalidAnswerException::class);

        $this->service->saveAnswer($this->order(), 1, null, null, '  ', 'en');
    }

    public function test_rejects_answers_for_targets_outside_the_order(): void
    {
        $this->mockQuestions([$this->question(2, QuestionBelongsTo::PRODUCT, productIds: [10])]);
        $this->mockOrderContents(
            attendees: [(new AttendeeDomainObject)->setId(100)->setProductId(10)],
            orderItems: [(new OrderItemDomainObject)->setProductId(10)],
        );
        $this->questionAnswerRepository->shouldReceive('findWhere')->andReturn(collect());
        $this->questionAnswerRepository->shouldNotReceive('create');

        $this->expectException(InvalidAnswerException::class);

        $this->service->saveAnswer($this->order(), 2, 999, 10, 'Rex', 'en');
    }

    public function test_creates_an_answer_when_none_exists(): void
    {
        $this->mockQuestions([$this->question(2, QuestionBelongsTo::PRODUCT, productIds: [10])]);
        $this->mockOrderContents(
            attendees: [(new AttendeeDomainObject)->setId(100)->setProductId(10)],
            orderItems: [(new OrderItemDomainObject)->setProductId(10)],
        );
        $this->questionAnswerRepository->shouldReceive('findWhere')->andReturn(collect());
        $this->questionAnswerRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $this->questionAnswerRepository->shouldReceive('create')->once()->with([
            'question_id' => 2,
            'order_id' => 5,
            'attendee_id' => 100,
            'product_id' => 10,
            'answer' => 'Luna',
        ])->andReturn(new QuestionAnswerDomainObject);

        $this->service->saveAnswer($this->order(), 2, 100, 10, 'Luna', 'en');

        $this->assertTrue(true);
    }

    private function order(OrderStatus $status = OrderStatus::COMPLETED): OrderDomainObject
    {
        return (new OrderDomainObject)->setId(5)->setEventId(1)->setStatus($status->name);
    }

    private function question(int $id, QuestionBelongsTo $belongsTo, array $productIds = [], bool $required = false): QuestionDomainObject
    {
        return (new QuestionDomainObject)
            ->setId($id)
            ->setTitle('Question '.$id)
            ->setType(QuestionTypeEnum::SINGLE_LINE_TEXT->name)
            ->setRequired($required)
            ->setBelongsTo($belongsTo->name)
            ->setProducts(collect(array_map(fn (int $productId) => (new ProductDomainObject)->setId($productId), $productIds)));
    }

    private function mockQuestions(array $questions): void
    {
        $this->questionRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->questionRepository->shouldReceive('findWhere')->andReturn(collect($questions));
    }

    private function mockOrderContents(array $attendees, array $orderItems): void
    {
        $this->attendeeRepository->shouldReceive('findWhere')->andReturn(collect($attendees));
        $this->orderItemRepository->shouldReceive('findWhere')->andReturn(collect($orderItems));
    }
}
