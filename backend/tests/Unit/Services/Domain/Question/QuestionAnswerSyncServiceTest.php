<?php

namespace Tests\Unit\Services\Domain\Question;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionAnswerDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Services\Domain\Question\QuestionAnswerSyncService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class QuestionAnswerSyncServiceTest extends TestCase
{
    private OrderRepositoryInterface|MockInterface $orderRepository;

    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private OrderItemRepositoryInterface|MockInterface $orderItemRepository;

    private ProductRepositoryInterface|MockInterface $productRepository;

    private QuestionAnswerRepositoryInterface|MockInterface $questionAnswerRepository;

    private QuestionAnswerSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->orderItemRepository = Mockery::mock(OrderItemRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->questionAnswerRepository = Mockery::mock(QuestionAnswerRepositoryInterface::class);

        $this->service = new QuestionAnswerSyncService(
            $this->orderRepository,
            $this->attendeeRepository,
            $this->orderItemRepository,
            $this->productRepository,
            $this->questionAnswerRepository,
        );
    }

    public function test_adds_unanswered_order_question_to_existing_orders_without_an_answer(): void
    {
        $this->mockOrders([10, 11]);
        $this->mockExistingAnswers([
            $this->answer(id: 1, orderId: 10, answer: ['answer' => 'Vegan']),
        ]);

        $inserted = null;
        $this->questionAnswerRepository->shouldReceive('insert')->once()->andReturnUsing(function (array $rows) use (&$inserted) {
            $inserted = $rows;

            return true;
        });
        $this->questionAnswerRepository->shouldNotReceive('forceDeleteUnansweredForQuestion');

        $this->service->syncWithExistingOrders($this->question(QuestionBelongsTo::ORDER), []);

        $this->assertSame([
            ['order_id' => 11, 'attendee_id' => null, 'product_id' => null, 'question_id' => 5, 'answer' => null],
        ], $inserted);
    }

    public function test_adds_product_question_per_attendee_and_per_order_for_general_products(): void
    {
        $this->mockOrders([10, 11]);
        $this->productRepository->shouldReceive('findWhereIn')->with('id', [1, 2])->andReturn(collect([
            (new ProductDomainObject)->setId(1)->setProductType(ProductType::TICKET->name),
            (new ProductDomainObject)->setId(2)->setProductType(ProductType::GENERAL->name),
        ]));
        $this->attendeeRepository->shouldReceive('findWhere')->andReturn(collect([
            (new AttendeeDomainObject)->setId(100)->setOrderId(10)->setProductId(1),
            (new AttendeeDomainObject)->setId(101)->setOrderId(10)->setProductId(1),
        ]));
        $this->orderItemRepository->shouldReceive('findWhere')->andReturn(collect([
            (new OrderItemDomainObject)->setOrderId(11)->setProductId(2),
            (new OrderItemDomainObject)->setOrderId(11)->setProductId(2),
        ]));
        $this->mockExistingAnswers([
            $this->answer(id: 1, orderId: 10, attendeeId: 100, productId: 1, answer: ['answer' => 'Large']),
        ]);

        $inserted = null;
        $this->questionAnswerRepository->shouldReceive('insert')->once()->andReturnUsing(function (array $rows) use (&$inserted) {
            $inserted = $rows;

            return true;
        });
        $this->questionAnswerRepository->shouldNotReceive('forceDeleteUnansweredForQuestion');

        $this->service->syncWithExistingOrders($this->question(QuestionBelongsTo::PRODUCT), [1, 2]);

        $this->assertSame([
            ['order_id' => 10, 'attendee_id' => 101, 'product_id' => 1, 'question_id' => 5, 'answer' => null],
            ['order_id' => 11, 'attendee_id' => null, 'product_id' => 2, 'question_id' => 5, 'answer' => null],
        ], $inserted);
    }

    public function test_removes_only_unanswered_rows_that_no_longer_apply(): void
    {
        $this->mockOrders([10]);
        $this->productRepository->shouldReceive('findWhereIn')->andReturn(collect([
            (new ProductDomainObject)->setId(1)->setProductType(ProductType::TICKET->name),
        ]));
        $this->attendeeRepository->shouldReceive('findWhere')->andReturn(collect([
            (new AttendeeDomainObject)->setId(100)->setOrderId(10)->setProductId(1),
        ]));
        $this->mockExistingAnswers([
            $this->answer(id: 1, orderId: 10, attendeeId: 100, productId: 1, answer: null),
            $this->answer(id: 2, orderId: 10, attendeeId: 200, productId: 3, answer: null),
            $this->answer(id: 3, orderId: 10, attendeeId: 201, productId: 3, answer: ['answer' => 'Kept']),
        ]);

        $this->questionAnswerRepository->shouldNotReceive('insert');
        $this->questionAnswerRepository->shouldReceive('forceDeleteUnansweredForQuestion')->once()->with(5, [2])->andReturn(1);

        $this->service->syncWithExistingOrders($this->question(QuestionBelongsTo::PRODUCT), [1]);

        $this->assertTrue(true);
    }

    public function test_does_nothing_without_existing_orders(): void
    {
        $this->mockOrders([]);
        $this->mockExistingAnswers([]);
        $this->productRepository->shouldNotReceive('findWhereIn');
        $this->questionAnswerRepository->shouldNotReceive('insert');
        $this->questionAnswerRepository->shouldNotReceive('forceDeleteUnansweredForQuestion');

        $this->service->syncWithExistingOrders($this->question(QuestionBelongsTo::PRODUCT), [1]);

        $this->assertTrue(true);
    }

    private function question(QuestionBelongsTo $belongsTo): QuestionDomainObject
    {
        return (new QuestionDomainObject)->setId(5)->setEventId(1)->setBelongsTo($belongsTo->name);
    }

    private function mockOrders(array $orderIds): void
    {
        $this->orderRepository->shouldReceive('findWhere')->andReturn(collect(array_map(
            fn (int $orderId) => (new OrderDomainObject)->setId($orderId),
            $orderIds,
        )));
    }

    private function mockExistingAnswers(array $answers): void
    {
        $this->questionAnswerRepository->shouldReceive('findWhere')->andReturn(collect($answers));
    }

    private function answer(int $id, int $orderId, ?int $attendeeId = null, ?int $productId = null, ?array $answer = null): QuestionAnswerDomainObject
    {
        return (new QuestionAnswerDomainObject)
            ->setId($id)
            ->setQuestionId(5)
            ->setOrderId($orderId)
            ->setAttendeeId($attendeeId)
            ->setProductId($productId)
            ->setAnswer($answer);
    }
}
