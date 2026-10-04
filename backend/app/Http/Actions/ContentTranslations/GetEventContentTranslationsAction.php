<?php

namespace HiEvents\Http\Actions\ContentTranslations;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\ContentTranslation\GetEventContentTranslationsHandler;
use Illuminate\Http\JsonResponse;

class GetEventContentTranslationsAction extends BaseAction
{
    public function __construct(
        private readonly GetEventContentTranslationsHandler $handler,
    ) {}

    public function __invoke(int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        return $this->jsonResponse(
            data: $this->handler->handle($eventId)->toArray(),
            wrapInData: true,
        );
    }
}
