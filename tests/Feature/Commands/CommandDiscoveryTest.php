<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Tests\Feature\Commands;

use M7md5ttab\LaravelShared\Tests\TestCase;

final class CommandDiscoveryTest extends TestCase
{
    public function test_it_registers_the_run_and_check_commands_without_the_legacy_fix_command(): void
    {
        $this->artisan('list --raw')
            ->expectsOutputToContain('hosting:run')
            ->expectsOutputToContain('hosting:check')
            ->expectsOutputToContain('hosting:rollback')
            ->doesntExpectOutputToContain('hosting:fix')
            ->doesntExpectOutputToContain('hosting:publish')
            ->assertExitCode(0);
    }
}
