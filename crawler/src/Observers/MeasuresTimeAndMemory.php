<?php

declare(strict_types=1);

namespace Longman\Crawler\Observers;

use function explode;
use function gmdate;
use function localeconv;
use function memory_get_usage;
use function microtime;
use function substr;

trait MeasuresTimeAndMemory
{
    protected float $startTime;
    protected int $startMemory;

    protected function getMemoryUsage(): int
    {
        return memory_get_usage(true);
    }

    protected function getMicroTime(): float
    {
        return microtime(true);
    }

    protected function getMeasuredTimeAsString(): string
    {
        $localeInfo = localeconv();
        $point = $localeInfo['decimal_point'] ?? '.';

        $time = $this->getMicroTime() - $this->startTime;
        [$sec, $usec] = explode($point, (string) $time);

        return gmdate('H:i:s', (int) $sec) . '.' . substr($usec, 0, 4);
    }
}
