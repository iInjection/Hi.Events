<?php

namespace HiEvents\Http\Actions\ContentTranslations;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\ContentTranslation\UpdateEventTranslationSettingsRequest;
use HiEvents\Services\Application\Handlers\ContentTranslation\DTO\UpdateEventTranslationSettingsDTO;
use HiEvents\Services\Application\Handlers\ContentTranslation\GetEventContentTranslationsHandler;
use HiEvents\Services\Application\Handlers\ContentTranslation\UpdateEventTranslationSettingsHandler;
use Illuminate\Http\JsonResponse;

class UpdateEventTranslationSettingsAction extends BaseAction
{
    public function __construct(
        private readonly UpdateEventTranslationSettingsHandler $updateHandler,
        private readonly GetEventContentTranslationsHandler $getHandler,
    ) {}

    public function __invoke(UpdateEventTranslationSettingsRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->updateHandler->handle(new UpdateEventTranslationSettingsDTO(
            event_id: $eventId,
            source_locale: $request->validated('source_locale'),
            fallback_locale: $request->validated('fallback_locale'),
            locales: $request->validated('locales', []),
        ));

        return $this->jsonResponse(
            data: $this->getHandler->handle($eventId)->toArray(),
            wrapInData: true,
        );
    }
}
