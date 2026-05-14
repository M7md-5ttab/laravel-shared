<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\DTOs;

use M7md5ttab\LaravelShared\Enums\CheckStatus;

final class ReadinessReport
{
    /**
     * @param  array<int, CheckResult>  $results
     */
    public function __construct(
        public readonly EnvironmentContext $environment,
        public readonly array $results,
    ) {
    }

    public function score(): int
    {
        if ($this->results === []) {
            return 0;
        }

        $total = array_reduce(
            $this->results,
            static fn (float $carry, CheckResult $result): float => $carry + $result->status->scoreWeight(),
            0.0,
        );

        return (int) round(($total / count($this->results)) * 100);
    }

    public function hasBlockingFailures(): bool
    {
        foreach ($this->results as $result) {
            if ($result->status === CheckStatus::Failed && $result->blocking) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, CheckResult>
     */
    public function failures(): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (CheckResult $result): bool => $result->status === CheckStatus::Failed,
        ));
    }

    /**
     * @return array<int, CheckResult>
     */
    public function warnings(): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (CheckResult $result): bool => $result->status === CheckStatus::Warning,
        ));
    }
}
