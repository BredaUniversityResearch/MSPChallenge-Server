<?php

namespace App\Domain\WsServer\Console;

use App\Domain\Common\Stopwatch\StopwatchEvent;
use App\Domain\Common\Stopwatch\StopwatchPeriod;
use App\Domain\Event\NameAwareEvent;
use App\Domain\Helper\Util;
use DateTime;

class ProfilerView extends TableViewBase
{
    private array $eventsStartMemory = [];

    public function getName(): string
    {
        return 'profiler';
    }

    protected function process(NameAwareEvent $event): void
    {
        // stopwatch is required
        if (!$this->stopwatch) {
            return;
        }
        $this->table
            ->setStyle('box')
            ->setHeaderTitle('Profiler')
            ->setHeaders(
                [
                    'Name',
                    'Duration',
                    '#Measurements',
                    'First memory start',
                    'Last memory start',
                    'Last memory end',
                    'Last memory usage',
                    'Memory usage increases'
                ]
            )
            ->setRows($this->printHierarchy(''))
            ->setFooterTitle(' total mem: ' .
                Util::getHumanReadableSize(memory_get_usage()));
    }

    private function printHierarchy(string $indent, string $parent = 'root'): array
    {
        $rows = [];
        $section = current($this->stopwatch->getSections());
        $rootEvent = $section->getEvent('root');
        $events = $section->getEvents();
        unset($events['root']);
        $totalDuration = round(microtime(true) * 1000 - $rootEvent->getOrigin(), 1);
        $totalMemory = array_sum(array_map(fn(StopwatchEvent $e) => $e->getPeakMemoryUsage(), $events));

        /** @var StopwatchEvent[] $children */
        $children = array_filter($events, fn($e) => $this->isChildOf($e, $parent));
        foreach ($children as $index => $event) {
            $isLast = $index === array_key_last($children);
            $label = $this->getLabel($event, $parent);
            $duration = $event->getDuration();
            $periods = array_slice($event->getPeriodsWithMemoryIncrease(), -10);
            $memoryIncreases = implode(
                ' ⮆  ',
                array_map(
                    fn(StopwatchPeriod $p) => "{$p->getId()}⨽ ".Util::getHumanReadableSize($p->getMemoryUsage()),
                    $periods
                )
            );
            $durationPercentage = $totalDuration ? ($duration / $totalDuration) * 100 : 0;
            $memoryPercentage = $totalMemory ?
                (($event->getLastPeriod()?->getMemoryUsage() ?? 0) / $totalMemory) * 100 : 0;

            $this->eventsStartMemory[$event->getName()] ??= $event->getStartMemory();
            $rows[] = [
                $indent . ($isLast ? '└╴' : '├╴') . $label,
                sprintf('%s (%.2f%%)', Util::getHumanReadableDuration($duration), $durationPercentage),
                $event->getLastPeriod()?->getId() ?? 0,
                Util::getHumanReadableSize($this->eventsStartMemory[$event->getName()]),
                Util::getHumanReadableSize($event->getLastPeriod()?->getStartMemory() ?? 0),
                Util::getHumanReadableSize($event->getLastPeriod()?->getEndMemory() ?? 0),
                sprintf(
                    '%s (%.2f%%)',
                    Util::getHumanReadableSize($event->getLastPeriod()?->getMemoryUsage() ?? 0),
                    $memoryPercentage
                ),
                $memoryIncreases
            ];

            $newIndent = $indent . ($isLast ? '  ' : '│ ');
            $rows = array_merge($rows, $this->printHierarchy($newIndent, $parent.'.'.$label));
        }

        return $rows;
    }

    private function isChildOf($event, string $parent): bool
    {
        $prefix = $parent . '.';
        return str_starts_with($event->getName(), $prefix) &&
            (!str_contains(substr($event->getName(), strlen($prefix)), '.'));
    }

    private function getLabel($event, string $parent): string
    {
        $name = $event->getName();
        $relativeName = substr($name, strlen($parent) + 1);
        $dotPosition = strpos($relativeName, '.');
        return $dotPosition === false ? $relativeName : substr($relativeName, 0, $dotPosition);
    }
}
