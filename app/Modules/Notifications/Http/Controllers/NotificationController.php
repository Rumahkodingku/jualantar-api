<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Application\Actions\DeleteNotification;
use App\Modules\Notifications\Application\Actions\GetUnreadNotificationCount;
use App\Modules\Notifications\Application\Actions\MarkAllNotificationsAsRead;
use App\Modules\Notifications\Application\Actions\MarkNotificationAsRead;
use App\Modules\Notifications\Application\Services\NotificationQueryService;
use App\Modules\Notifications\Contracts\DataTransferObjects\NotificationData;
use App\Modules\Notifications\Domain\Models\Notification;
use App\Modules\Notifications\Http\Requests\ListNotificationsRequest;
use App\Modules\Notifications\Http\Resources\NotificationResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationQueryService $queryService,
        private readonly MarkNotificationAsRead $markNotificationAsRead,
        private readonly MarkAllNotificationsAsRead $markAllNotificationsAsRead,
        private readonly DeleteNotification $deleteNotification,
        private readonly GetUnreadNotificationCount $getUnreadNotificationCount,
    ) {}

    #[OpenApiResponse(200, 'Notifications', type: 'array{data: array<int, \App\Modules\Notifications\Http\Resources\NotificationResource>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}')]
    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $recipientId = (string) $request->user()->id;

        $paginator = $this->queryService->list($recipientId, $request->validated());

        return ApiResponse::paginated($paginator, NotificationResource::collection($paginator->items()));
    }

    #[OpenApiResponse(200, 'Notification', type: 'array{data: \App\Modules\Notifications\Http\Resources\NotificationResource}')]
    public function show(Notification $notification): JsonResponse
    {
        return ApiResponse::success(new NotificationResource(NotificationData::fromModel($notification)));
    }

    #[OpenApiResponse(200, 'Notification marked as read', type: 'array{data: \App\Modules\Notifications\Http\Resources\NotificationResource}')]
    public function read(Notification $notification): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->markNotificationAsRead)($notification),
            fn (NotificationData $data) => ApiResponse::success(new NotificationResource($data)),
        );
    }

    #[OpenApiResponse(200, 'Notifications marked as read', type: 'array{data: array{count: int}}')]
    public function readAll(Request $request): JsonResponse
    {
        $recipientId = (string) $request->user()->id;

        return ApiResponse::fromResult(
            ($this->markAllNotificationsAsRead)($recipientId),
            fn (int $count) => ApiResponse::success(['count' => $count]),
        );
    }

    #[OpenApiResponse(200, 'Unread notification count', type: 'array{data: array{count: int}}')]
    public function unreadCount(Request $request): JsonResponse
    {
        $recipientId = (string) $request->user()->id;

        return ApiResponse::fromResult(
            ($this->getUnreadNotificationCount)($recipientId),
            fn (int $count) => ApiResponse::success(['count' => $count]),
        );
    }

    #[OpenApiResponse(204, 'Notification deleted')]
    #[IgnoreResponse(200)]
    public function destroy(Notification $notification): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->deleteNotification)($notification),
            fn () => ApiResponse::noContent(),
        );
    }
}
