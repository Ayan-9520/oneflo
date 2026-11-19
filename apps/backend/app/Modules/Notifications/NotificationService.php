<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Models\AppNotification;
use App\Models\Employee;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\NotificationProvider;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\User;
use App\Services\OrganizationContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class NotificationService
{
    public function __construct(private readonly OrganizationContext $context) {}

    /**
     * @param  list<User|null>  $users
     */
    public function publish(Organization $organization, array $users, string $event, string $dedupe, string $title, string $body): void
    {
        $previous = $this->context->get();
        $this->context->set($organization);
        try {
            foreach ($users as $user) {
                if (! $user instanceof User) {
                    continue;
                }
                $this->deliver($organization, $user, $event, $dedupe, $title, $body);
            }
        } finally {
            if ($previous instanceof Organization) {
                $this->context->set($previous);
            } else {
                $this->context->forget();
            }
        }
    }

    /**
     * @return list<User>
     */
    public function holders(Organization $organization, string $permission): array
    {
        return OrganizationAccess::query()
            ->where('organization_id', $organization->id)
            ->whereHas('role.permissions', fn ($query) => $query->where('name', $permission))
            ->with('user')
            ->get()
            ->map(fn (OrganizationAccess $access) => $access->user)
            ->filter()
            ->unique('id')
            ->values()
            ->all();
    }

    private function deliver(Organization $organization, User $user, string $event, string $dedupe, string $title, string $body): void
    {
        $exists = AppNotification::query()->where('user_id', $user->id)->where('dedupe_key', $dedupe)->exists();
        if ($exists) {
            return;
        }
        $preference = NotificationPreference::query()->where('user_id', $user->id)->where('event', $event)->first();
        $channels = [
            'in_app' => $preference?->in_app ?? true,
            'email' => $preference?->email ?? true,
            'sms' => $preference?->sms ?? false,
            'whatsapp' => $preference?->whatsapp ?? false,
        ];
        $notification = AppNotification::query()->create([
            'user_id' => $user->id,
            'event' => $event,
            'dedupe_key' => $dedupe,
            'title' => $title,
            'body' => $body,
            'in_app' => $channels['in_app'],
        ]);
        $this->record($notification, 'in_app', $channels['in_app'] ? 'sent' : 'skipped', $channels['in_app'] ? null : 'In-app delivery is turned off.');
        if ($channels['email']) {
            $this->email($notification, $user, $title, $body);
        } else {
            $this->record($notification, 'email', 'skipped', 'Email delivery is turned off.');
        }
        $phone = Employee::query()->where('user_id', $user->id)->value('phone');
        $this->mobile($notification, $organization, 'sms', $channels['sms'], $phone, $body);
        $this->mobile($notification, $organization, 'whatsapp', $channels['whatsapp'], $phone, $body);
    }

    private function email(AppNotification $notification, User $user, string $title, string $body): void
    {
        if ($user->email === '') {
            $this->record($notification, 'email', 'skipped', 'The account has no email address.');

            return;
        }
        try {
            Mail::raw($body, function ($message) use ($user, $title): void {
                $message->to($user->email)->subject($title);
            });
            $this->record($notification, 'email', 'sent', null);
        } catch (Throwable $exception) {
            $this->record($notification, 'email', 'failed', $exception->getMessage());
        }
    }

    private function mobile(AppNotification $notification, Organization $organization, string $channel, bool $enabled, mixed $phone, string $body): void
    {
        if (! $enabled) {
            $this->record($notification, $channel, 'skipped', ucfirst($channel).' delivery is turned off.');

            return;
        }
        $provider = NotificationProvider::query()->where('channel', $channel)->where('enabled', true)->first();
        if ($provider === null) {
            $this->record($notification, $channel, 'unconfigured', 'No approved '.$channel.' provider is enabled for this business.');

            return;
        }
        if (! is_string($phone) || $phone === '') {
            $this->record($notification, $channel, 'skipped', 'No mobile number is recorded for this employee.');

            return;
        }
        try {
            $response = Http::timeout(5)->withToken($provider->secret)->acceptJson()->post($provider->endpoint, [
                'to' => $phone,
                'sender' => $provider->sender,
                'body' => $body,
                'channel' => $channel,
                'organization_id' => $organization->id,
            ]);
            $this->record($notification, $channel, $response->successful() ? 'sent' : 'failed', 'Provider responded '.$response->status().'.');
        } catch (Throwable $exception) {
            $this->record($notification, $channel, 'failed', $exception->getMessage());
        }
    }

    private function record(AppNotification $notification, string $channel, string $status, ?string $detail): void
    {
        NotificationDelivery::query()->create([
            'notification_id' => $notification->id,
            'channel' => $channel,
            'status' => $status,
            'detail' => $detail,
        ]);
    }
}
