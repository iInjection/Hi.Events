<?php

namespace HiEvents\Http\Actions\Questions;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Question\GetBuyerEditableAnswersHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetBuyerEditableAnswersPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetBuyerEditableAnswersHandler $handler,
    ) {}

    public function __invoke(int $eventId, string $orderShortId): JsonResponse
    {
        try {
            return $this->jsonResponse(
                data: $this->handler->handle($eventId, $orderShortId, App::getLocale()),
                wrapInData: true,
            );
        } catch (ResourceNotFoundException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }
}
