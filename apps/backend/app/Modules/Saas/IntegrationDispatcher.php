<?php

declare(strict_types=1);

namespace App\Modules\Saas;

use App\Models\ExpenseClaim;
use App\Models\IntegrationDelivery;
use App\Models\IntegrationProvider;
use App\Models\Organization;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\Http;
use Throwable;

final class IntegrationDispatcher
{
    public function payroll(Organization $organization, PayrollRun $run): void
    {
        $this->send($organization, 'payroll', 'run:'.$run->id, [
            'organization_id' => $organization->id,
            'year' => $run->year,
            'month' => $run->month,
            'lines' => $run->lines()->count(),
        ]);
    }

    public function accounting(Organization $organization, ExpenseClaim $claim): void
    {
        $this->send($organization, 'accounting', 'expense:'.$claim->id, [
            'organization_id' => $organization->id,
            'claim_id' => $claim->id,
            'amount' => (string) $claim->amount,
            'status' => $claim->status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(Organization $organization, string $kind, string $subject, array $payload): void
    {
        $provider = IntegrationProvider::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('kind', $kind)
            ->first();
        if ($provider === null) {
            $this->record($organization, $kind, 'unconfigured', 'No '.$kind.' integration is configured for this business.', $subject);

            return;
        }
        if (! $provider->enabled) {
            $this->record($organization, $kind, 'skipped', 'The '.$kind.' integration is turned off.', $subject);

            return;
        }
        if (! str_starts_with($provider->endpoint, 'https://')) {
            $this->record($organization, $kind, 'failed', 'The endpoint must use HTTPS.', $subject);

            return;
        }
        try {
            $response = Http::timeout(5)->withToken($provider->secret)->post($provider->endpoint, $payload);
            $this->record($organization, $kind, $response->successful() ? 'sent' : 'failed', 'HTTP '.$response->status(), $subject);
        } catch (Throwable $exception) {
            $this->record($organization, $kind, 'failed', $exception->getMessage(), $subject);
        }
    }

    private function record(Organization $organization, string $kind, string $status, string $detail, string $subject): void
    {
        $delivery = new IntegrationDelivery;
        $delivery->organization_id = $organization->id;
        $delivery->kind = $kind;
        $delivery->status = $status;
        $delivery->detail = $detail;
        $delivery->subject = $subject;
        $delivery->save();
    }
}
