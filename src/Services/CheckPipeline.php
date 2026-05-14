<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Services;

use Illuminate\Contracts\Container\Container;
use M7md5ttab\LaravelShared\Contracts\CheckInterface;
use M7md5ttab\LaravelShared\DTOs\ReadinessReport;
use M7md5ttab\LaravelShared\Support\ProjectEnvironmentResolver;

class CheckPipeline
{
    /**
     * @param  array<int, class-string<CheckInterface>>  $checkerClasses
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $checkerClasses,
        private readonly ProjectEnvironmentResolver $environmentResolver,
    ) {
    }

    /**
     * @param  null|callable(int): void  $progress
     */
    public function run(?callable $progress = null): ReadinessReport
    {
        $context = $this->environmentResolver->resolve();
        $results = [];

        foreach ($this->checkerClasses as $index => $checkerClass) {
            /** @var CheckInterface $checker */
            $checker = $this->container->make($checkerClass);
            $results[] = $checker->check($context);

            if ($progress !== null) {
                $progress($index + 1);
            }
        }

        return new ReadinessReport($context, $results);
    }

    public function count(): int
    {
        return count($this->checkerClasses);
    }
}
