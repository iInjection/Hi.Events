<?php

namespace HiEvents\Http\Actions\Events;

use HiEvents\Resources\Event\EventResourcePublic;
use HiEvents\Services\Application\Handlers\Event\DTO\GetPublicEventDTO;
use HiEvents\Services\Application\Handlers\Event\GetPublicEventHandler;
use HiEvents\Services\Domain\ContentTranslation\ContentTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Psr\Log\LoggerInterface;

class GetEventPublicAction extends BasePublicEventAction
{
    public function __construct(
        private readonly GetPublicEventHandler $getPublicEventHandler,
        private readonly LoggerInterface $logger,
        private readonly ContentTranslationService $contentTranslationService,
    ) {}

    public function __invoke(int $eventId, Request $request): Response|JsonResponse
    {
        $event = $this->getPublicEventHandler->handle(GetPublicEventDTO::fromArray([
            'eventId' => $eventId,
            'ipAddress' => $this->getClientIp($request),
            'promoCode' => strtolower($request->string('promo_code')),
            'isAuthenticated' => $this->isUserAuthenticated(),
            'eventOccurrenceId' => $request->integer('event_occurrence_id') ?: null,
        ]));

        if (! $this->canUserViewEvent($event)) {
            $this->logger->debug(__('Event with ID :eventId is not live and user is not authenticated', [
                'eventId' => $eventId,
            ]));

            return $this->notFoundResponse();
        }

        $this->contentTranslationService->translateEvent($event, App::getLocale());

        return $this->resourceResponse(EventResourcePublic::class, $event);
    }
}
