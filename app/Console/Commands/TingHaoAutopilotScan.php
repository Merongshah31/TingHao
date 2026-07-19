<?php

namespace App\Console\Commands;

use App\Services\Agent\AutopilotInventoryScanService;
use Illuminate\Console\Command;
use Throwable;

class TingHaoAutopilotScan extends Command
{
    protected $signature = 'tinghao:autopilot-scan';

    protected $description = 'Scan inventory and expiry risk, reuse stock predictions, and optionally prepare approval-gated PO drafts.';

    public function handle(AutopilotInventoryScanService $scanService): int
    {
        try {
            $result = $scanService->scan();
        } catch (Throwable $exception) {
            $this->error('Autopilot scan failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($result['duplicate']) {
            $this->info('A recent autopilot scan already exists as Agent Run #'.$result['run']->id.'. No duplicate run was created.');

            return self::SUCCESS;
        }

        $this->info('Autopilot scan completed as Agent Run #'.$result['run']->id.'.');
        $this->line('Predictions recorded: '.$result['predictions']);
        $this->line('Pending approval PO drafts created: '.$result['drafts']);

        return self::SUCCESS;
    }
}
