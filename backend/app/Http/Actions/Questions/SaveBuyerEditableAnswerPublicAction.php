<?php

namespace HiEvents\Http\Actions\Questions;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Questions\SaveBuyerEditableAnswerRequest;
use HiEvents\Services\Application\Handlers\Question\DTO\SaveBuyerEditableAnswerDTO;
use HiEvents\Services\Application\Handlers\Question\SaveBuyerEditableAnswerHandler;
use HiEvents\Services\Domain\Question\Exception\InvalidAnswerException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class SaveBuyerEditableAnswerPublicAction extends BaseAction
{
    public function __construct(
        private readonly SaveBuyerEditableAnswerHandler $handler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(SaveBuyerEditableAnswerRequest $request, int $eventId, string $orderShortId): JsonResponse
    {
        try {
            return $this->jsonResponse(
                data: $this->handler->handle(new SaveBuyerEditableAnswerDTO(
                    event_id: $eventId,
                    order_short_id: $orderShortId,
                    question_id: (int) $request->validated('question_id'),
                    attendee_id: $request->validated('attendee_id') !== null ? (int) $request->validated('attendee_id') : null,
                    product_id: $request->validated('product_id') !== null ? (int) $request->validated('product_id') : null,
                    answer: $request->input('answer'),
                    locale: App::getLocale(),
                )),
                wrapInData: true,
            );
        } catch (InvalidAnswerException $exception) {
            throw ValidationException::withMessages(['answer' => $exception->getMessage()]);
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }
}
