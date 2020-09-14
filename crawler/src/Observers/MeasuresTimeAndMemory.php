<?php

declare(strict_types=1);

namespace Longman\Crawler\Observers;

use function explode;
use function floor;
use function gmdate;
use function localeconv;
use function memory_get_usage;
use function microtime;
use function pow;
use function sprintf;
use function strlen;
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

    protected function getMeasuredMemoryAsString(): string
    {
        $memory = $this->getMemoryUsage() - $this->startMemory;

        return $this->bytesToHumanReadable($memory);
    }

    private function bytesToHumanReadable(int $bytes, int $decimals = 2): string
    {
        $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $factor = (int) floor((strlen((string) $bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $size[$factor];
    }
}
