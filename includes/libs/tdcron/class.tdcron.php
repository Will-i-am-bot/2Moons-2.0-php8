<?php
/**
 * tdCron v1.1.0 – Fixed for PHP 8.x and 2Moons
 * Cron Parser & Scheduler
 *
 * Original author: Christian Land / tagdocs.de (2010)
 * Modified & modernized by OPTtheTI (2025)
 *
 * Supports standard 5-field cron syntax:
 *   ┌───────────── minute (0 - 59)
 *   │ ┌───────────── hour (0 - 23)
 *   │ │ ┌───────────── day of month (1 - 31)
 *   │ │ │ ┌───────────── month (1 - 12)
 *   │ │ │ │ ┌───────────── day of week (0 - 6) (Sunday=0)
 *   │ │ │ │ │
 *   * * * * *
 */

declare(strict_types=1);

class tdCron
{
    /** @var array<string,mixed> Cache for parsed expressions */
    private static array $pcron = [];

    // -------------------------------------------------------------------------
    // 🕒 Public API
    // -------------------------------------------------------------------------

    /**
     * Returns the next execution timestamp for a given cron expression.
     *
     * @param string $expression The cron string ("* * * * *")
     * @param int|null $timestamp Reference time (default: now)
     * @return int Next timestamp (≥ now+60)
     * @throws Exception
     */
    public static function getNextOccurrence(string $expression, ?int $timestamp = null): int
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        // Only support 5 fields; if older 6-field syntax used → fix it
        $parts = preg_split('/\s+/', trim($expression));
        if (count($parts) !== 5) {
            $parts = array_slice($parts, -5);
        }

        $normalized = implode(' ', $parts);

        // Parse and calculate next timestamp
        $rtime = self::getTimestamp($timestamp);
        $next  = self::calculateDateTime($normalized, $rtime);

        // Safety fix: ensure at least +60 seconds difference
        if ($next - $timestamp < 60) {
            $next = $timestamp + 60;
        }

        return $next;
    }

    /**
     * Returns the previous execution timestamp for a given cron expression.
     *
     * @param string $expression
     * @param int|null $timestamp
     * @return int
     * @throws Exception
     */
    public static function getLastOccurrence(string $expression, ?int $timestamp = null): int
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        $parts = preg_split('/\s+/', trim($expression));
        if (count($parts) !== 5) {
            $parts = array_slice($parts, -5);
        }

        $normalized = implode(' ', $parts);
        $rtime      = self::getTimestamp($timestamp);

        return self::calculateDateTime($normalized, $rtime, false);
    }

    // -------------------------------------------------------------------------
    // 🧠 Internal logic
    // -------------------------------------------------------------------------

    /**
     * Calculates next or last timestamp for a cron expression.
     */
    private static function calculateDateTime(string $expression, array $rtime, bool $next = true): int
    {
        $cron = self::getExpression($expression, !$next);

        // Normalize reference time
        [$minute, $hour, $day, $month, $weekday, $year] = $rtime;

        $found = false;

        // Search up to 10 years into future/past for next valid date
        for ($y = $year; $next ? $y <= $year + 10 : $y >= $year - 10; $y += ($next ? 1 : -1)) {
            foreach ($cron[3] as $m) {
                foreach ($cron[2] as $d) {
                    if (!checkdate($m, $d, $y)) {
                        continue;
                    }

                    $date = mktime(0, 0, 0, $m, $d, $y);
                    $w    = (int)date('w', $date);

                    if (!in_array($w, $cron[4], true)) {
                        continue;
                    }

                    foreach ($cron[1] as $h) {
                        foreach ($cron[0] as $min) {
                            $candidate = mktime($h, $min, 0, $m, $d, $y);

                            if ($next && $candidate > time()) {
                                return $candidate;
                            }

                            if (!$next && $candidate < time()) {
                                $found = $candidate;
                            }
                        }
                    }

                    if ($found !== false && !$next) {
                        return (int)$found;
                    }
                }
            }
        }

        throw new Exception('No valid cron occurrence found within 10 years.');
    }

    /**
     * Converts timestamp → [minute,hour,day,month,weekday,year]
     */
    private static function getTimestamp(?int $timestamp = null): array
    {
        if ($timestamp === null) {
            $timestamp = time();
        }
        $arr = explode(',', date('i,H,d,m,w,Y', $timestamp));

        return array_map(static fn($v) => (int)ltrim($v, '0'), $arr);
    }

    /**
     * Returns parsed cron expression (cached).
     */
    private static function getExpression(string $expression, bool $reverse = false): array
    {
        $expression = preg_replace('/(\s+)/', ' ', strtolower(trim($expression)));

        if (!isset(self::$pcron[$expression])) {
            self::$pcron[$expression] = tdCronEntry::parse($expression);
            self::$pcron['reverse'][$expression] = self::arrayReverse(self::$pcron[$expression]);
        }

        return $reverse ? self::$pcron['reverse'][$expression] : self::$pcron[$expression];
    }

    /**
     * Helper to reverse nested arrays.
     */
    private static function arrayReverse(array $cron): array
    {
        foreach ($cron as $key => $value) {
            $cron[$key] = array_reverse($value);
        }
        return $cron;
    }
}
