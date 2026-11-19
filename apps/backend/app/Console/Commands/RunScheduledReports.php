<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Modules\Notifications\NotificationService;
use App\Modules\Reports\ReportScope;
use App\Modules\Reports\ReportService;
use App\Services\OrganizationContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RunScheduledReports extends Command
{
    protected $signature = 'reports:run';

    protected $description = 'Generate due saved report schedules for each business';

    public function handle(ReportService $reports, OrganizationContext $context, NotificationService $notifications): int
    {
        $due = ReportSchedule::withoutGlobalScopes()->whereDate('next_run_on', '<=', now()->toDateString())->orderBy('id')->get();
        foreach ($due as $schedule) {
            $organization = Organization::query()->find($schedule->organization_id);
            $user = User::query()->find($schedule->user_id);
            if ($organization === null || $user === null) {
                $schedule->last_error = 'The schedule owner or business no longer exists.';
                $schedule->next_run_on = $this->advance($schedule);
                $schedule->save();

                continue;
            }
            $context->set($organization);
            try {
                $table = $reports->run($schedule->report, ReportScope::fromArray($schedule->filters ?? []), $user);
                $contents = match ($schedule->format) {
                    'xlsx' => $reports->xlsx($table),
                    'pdf' => $reports->pdf($schedule->report, $table),
                    default => $reports->csv($table),
                };
                $extension = $schedule->format === 'xlsx' ? 'xlsx' : ($schedule->format === 'pdf' ? 'pdf' : 'csv');
                Storage::disk('documents')->put(
                    'reports/'.$organization->id.'/'.$schedule->id.'/'.$schedule->next_run_on?->format('Ymd').'.'.$extension,
                    $contents,
                );
                $notifications->publish(
                    $organization,
                    [$user],
                    'report.ready',
                    'report:'.$schedule->id.':'.$schedule->next_run_on?->toDateString(),
                    'Scheduled report ready',
                    'The '.$schedule->report.' report was generated.',
                );
                $schedule->last_error = null;
                $schedule->next_run_on = $this->advance($schedule);
                $schedule->save();
            } catch (\Throwable $exception) {
                $schedule->last_error = $exception->getMessage();
                $schedule->save();
            } finally {
                $context->forget();
            }
        }

        return self::SUCCESS;
    }

    private function advance(ReportSchedule $schedule): \Illuminate\Support\Carbon
    {
        $date = ($schedule->next_run_on ?? now())->copy();

        return match ($schedule->cadence) {
            'weekly' => $date->addWeek(),
            'monthly' => $date->addMonth(),
            default => $date->addDay(),
        };
    }
}
