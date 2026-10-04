<?php

namespace HiEvents\Http\Actions\ContentTranslations;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\InvalidContentTranslationException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\ContentTranslation\UpsertContentTranslationsRequest;
use HiEvents\Services\Application\Handlers\ContentTranslation\DTO\UpsertContentTranslationsDTO;
use HiEvents\Services\Application\Handlers\ContentTranslation\GetEventContentTranslationsHandler;
use HiEvents\Services\Application\Handlers\ContentTranslation\UpsertContentTranslationsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UpsertContentTranslationsAction extends BaseAction
{
    public function __construct(
        private readonly UpsertContentTranslationsHandler $upsertHandler,
        private readonly GetEventContentTranslationsHandler $getHandler,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(UpsertContentTranslationsRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $this->upsertHandler->handle(new UpsertContentTranslationsDTO(
                event_id: $eventId,
                locale: $request->validated('locale'),
                translations: $request->validated('translations'),
            ));
        } catch (InvalidContentTranslationException $exception) {
            throw ValidationException::withMessages(['translations' => $exception->getMessage()]);
        }

        return $this->jsonResponse(
            data: $this->getHandler->handle($eventId)->toArray(),
            wrapInData: true,
        );
    }
}
