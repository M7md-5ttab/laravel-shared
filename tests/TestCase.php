<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Tests;

use M7md5ttab\LaravelShared\LaravelSharedServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LaravelSharedServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
    }
}
