<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use M7md5ttab\LaravelShared\Commands\HostingCheckCommand;
use M7md5ttab\LaravelShared\Commands\HostingRollbackCommand;
use M7md5ttab\LaravelShared\Commands\HostingRunCommand;
use M7md5ttab\LaravelShared\Contracts\ManifestRepositoryInterface;
use M7md5ttab\LaravelShared\Contracts\ProcessRunnerInterface;
use M7md5ttab\LaravelShared\Git\GitService;
use M7md5ttab\LaravelShared\Services\CheckPipeline;
use M7md5ttab\LaravelShared\Services\DeploymentManager;
use M7md5ttab\LaravelShared\Services\FixManager;
use M7md5ttab\LaravelShared\Services\RunManager;
use M7md5ttab\LaravelShared\Services\RollbackManager;
use M7md5ttab\LaravelShared\Support\DeploymentNameGenerator;
use M7md5ttab\LaravelShared\Support\HostingProviderDetector;
use M7md5ttab\LaravelShared\Support\IndexPhpPatcher;
use M7md5ttab\LaravelShared\Support\JsonManifestRepository;
use M7md5ttab\LaravelShared\Support\ProjectEnvironmentResolver;
use M7md5ttab\LaravelShared\Support\PublicHtmlLocator;
use M7md5ttab\LaravelShared\Support\SymfonyProcessRunner;
use M7md5ttab\LaravelShared\Support\SymlinkSupportDetector;

final class LaravelSharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/hosting-shared.php', 'hosting-shared');

        $this->app->singleton(ProcessRunnerInterface::class, SymfonyProcessRunner::class);
        $this->app->singleton(ManifestRepositoryInterface::class, JsonManifestRepository::class);
        $this->app->singleton(HostingProviderDetector::class);
        $this->app->singleton(PublicHtmlLocator::class);
        $this->app->singleton(SymlinkSupportDetector::class);
        $this->app->singleton(IndexPhpPatcher::class);
        $this->app->singleton(DeploymentNameGenerator::class);
        $this->app->singleton(ProjectEnvironmentResolver::class);
        $this->app->singleton(GitService::class);

        $this->app->singleton(CheckPipeline::class, function (Container $app): CheckPipeline {
            return new CheckPipeline(
                $app,
                (array) $app['config']->get('hosting-shared.checks', []),
                $app->make(ProjectEnvironmentResolver::class),
            );
        });

        $this->app->singleton(FixManager::class, function (Container $app): FixManager {
            return new FixManager(
                $app,
                (array) $app['config']->get('hosting-shared.fixers', []),
                $app->make(CheckPipeline::class),
                $app->make(GitService::class),
            );
        });

        $this->app->singleton(DeploymentManager::class, function (Container $app): DeploymentManager {
            return new DeploymentManager(
                $app,
                (array) $app['config']->get('hosting-shared.publishers', []),
                $app->make(ProjectEnvironmentResolver::class),
                $app->make(PublicHtmlLocator::class),
                $app->make(SymlinkSupportDetector::class),
                $app->make(DeploymentNameGenerator::class),
                $app->make(ManifestRepositoryInterface::class),
                $app->make(GitService::class),
            );
        });

        $this->app->singleton(RollbackManager::class, function (Container $app): RollbackManager {
            return new RollbackManager(
                $app,
                (array) $app['config']->get('hosting-shared.rollback_strategies', []),
                $app->make(ProjectEnvironmentResolver::class),
                $app->make(ManifestRepositoryInterface::class),
                $app->make(GitService::class),
            );
        });

        $this->app->singleton(RunManager::class, function (Container $app): RunManager {
            return new RunManager(
                $app->make(CheckPipeline::class),
                $app->make(FixManager::class),
                $app->make(DeploymentManager::class),
                $app->make(PublicHtmlLocator::class),
                $app->make(\Illuminate\Filesystem\Filesystem::class),
                $app->make(GitService::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/hosting-shared.php' => config_path('hosting-shared.php'),
        ], 'hosting-shared-config');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            HostingCheckCommand::class,
            HostingRunCommand::class,
            HostingRollbackCommand::class,
        ]);
    }
}
