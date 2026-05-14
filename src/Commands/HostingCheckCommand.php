<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Commands;

use Illuminate\Console\Command;
use M7md5ttab\LaravelShared\Console\Concerns\InteractsWithHostingOutput;
use M7md5ttab\LaravelShared\Services\CheckPipeline;

final class HostingCheckCommand extends Command
{
    use InteractsWithHostingOutput;

    protected $signature = 'hosting:check';

    protected $description = 'Analyze the current Laravel application and generate a shared hosting deployment readiness report.';

    public function handle(CheckPipeline $pipeline): int
    {
        $this->components->info('Running shared hosting readiness checks...');

        $bar = $this->output->createProgressBar($pipeline->count());
        $bar->start();

        $report = $pipeline->run(function () use ($bar): void {
            $bar->advance();
        });

        $bar->finish();
        $this->newLine(2);
        $this->renderReadinessReport($report);

        return $report->hasBlockingFailures() ? self::FAILURE : self::SUCCESS;
    }
}
