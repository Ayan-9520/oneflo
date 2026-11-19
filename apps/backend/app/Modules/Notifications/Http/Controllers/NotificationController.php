<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\NotificationPreference;
use App\Models\NotificationProvider;
use App\Modules\Notifications\NotificationEvents;
use App\Services\AuthorizationService;
use App\Support\ApiResponse;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::data(AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('in_app', true)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (AppNotification $notification) => [
                'id' => $notification->id,
                'event' => $notification->event,
                'title' => $notification->title,
                'body' => $notification->body,
                'read_at' => $notification->read_at?->toIso8601String(),
            ])->all());
    }

    public function read(Request $request, AppNotification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(404, 'The requested resource was not found.');
        }
        $notification->read_at = now();
        $notification->save();

        return ApiResponse::data(['read' => true]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $stored = NotificationPreference::query()->where('user_id', $request->user()->id)->get()->keyBy('event');

        return ApiResponse::data(array_map(fn (string $event) => [
            'event' => $event,
            'in_app' => $stored->get($event)?->in_app ?? true,
            'email' => $stored->get($event)?->email ?? true,
            'sms' => $stored->get($event)?->sms ?? false,
            'whatsapp' => $stored->get($event)?->whatsapp ?? false,
        ], NotificationEvents::all()));
    }

    public function updatePreference(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', Rule::in(NotificationEvents::all())],
            'in_app' => ['required', 'boolean'],
            'email' => ['required', 'boolean'],
            'sms' => ['required', 'boolean'],
            'whatsapp' => ['required', 'boolean'],
        ]);
        $preference = NotificationPreference::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'event' => $data['event']],
            $data,
        );

        return ApiResponse::data($preference->only(['event', 'in_app', 'email', 'sms', 'whatsapp']));
    }

    public function providers(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::NOTIFICATIONS_MANAGE);

        return ApiResponse::data(NotificationProvider::query()->orderBy('channel')->get()->map(fn (NotificationProvider $provider) => [
            'channel' => $provider->channel,
            'endpoint' => $provider->endpoint,
            'sender' => $provider->sender,
            'enabled' => $provider->enabled,
        ])->all());
    }

    public function updateProvider(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::NOTIFICATIONS_MANAGE);
        $data = $request->validate([
            'channel' => ['required', Rule::in(['sms', 'whatsapp'])],
            'endpoint' => ['required', 'url', 'starts_with:https://'],
            'sender' => ['nullable', 'string', 'max:40'],
            'secret' => ['required', 'string', 'max:200'],
            'enabled' => ['required', 'boolean'],
        ]);
        $provider = NotificationProvider::query()->updateOrCreate(
            ['channel' => $data['channel']],
            $data,
        );

        return ApiResponse::data($provider->only(['channel', 'endpoint', 'sender', 'enabled']));
    }
}
