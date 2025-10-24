<?php
declare(strict_types=1);

/**
 * tdCronEntry – Parser für klassische Cron-Expressions (min hour dom month dow)
 * Unterstützt Werte, Bereiche, Intervalle, Monats- und Wochentagsnamen.
 * Kompatibel mit PHP 8.1–8.3
 */
class tdCronEntry
{
    /** @var array<int,array{min:int,max:int}> */
    private static array $ranges = [
        IDX_MINUTE  => ['min' => 0,  'max' => 59],
        IDX_HOUR    => ['min' => 0,  'max' => 23],
        IDX_DAY     => ['min' => 1,  'max' => 31],
        IDX_MONTH   => ['min' => 1,  'max' => 12],
        IDX_WEEKDAY => ['min' => 0,  'max' => 7],
    ];

    /** @var array<string,string> */
    private static array $intervals = [
        '@yearly'   => '0 0 1 1 *',
        '@annualy'  => '0 0 1 1 *',
        '@monthly'  => '0 0 1 * *',
        '@weekly'   => '0 0 * * 0',
        '@midnight' => '0 0 * * *',
        '@daily'    => '0 0 * * *',
        '@hourly'   => '0 * * * *',
    ];

    /** @var array<int,array<string,int>> */
    private static array $keywords = [
        IDX_MONTH => [
            '/\b(january|januar|jan)\b/i'     => 1,
            '/\b(february|februar|feb)\b/i'   => 2,
            '/\b(march|maerz|märz|mar|mae|mär)\b/i' => 3,
            '/\b(april|apr)\b/i'              => 4,
            '/\b(may|mai)\b/i'                => 5,
            '/\b(june|juni|jun)\b/i'          => 6,
            '/\b(july|juli|jul)\b/i'          => 7,
            '/\b(august|aug)\b/i'             => 8,
            '/\b(september|sep)\b/i'          => 9,
            '/\b(october|oktober|okt|oct)\b/i'=> 10,
            '/\b(november|nov)\b/i'           => 11,
            '/\b(december|dezember|dec|dez)\b/i'=> 12,
        ],
        IDX_WEEKDAY => [
            '/\b(sunday|sonntag|sun|son|su|so)\b/i'   => 0,
            '/\b(monday|montag|mon|mo)\b/i'           => 1,
            '/\b(tuesday|dienstag|die|tue|tu|di)\b/i' => 2,
            '/\b(wednesday|mittwoch|mit|wed|we|mi)\b/i'=> 3,
            '/\b(thursday|donnerstag|don|thu|th|do)\b/' => 4,
            '/\b(friday|freitag|fre|fri|fr)\b/i'      => 5,
            '/\b(saturday|samstag|sam|sat|sa)\b/i'    => 6,
        ],
    ];

    /**
     * Parst eine vollständige Cron-Expression in ein Array von Zahlenwerten.
     * @throws Exception bei fehlerhafter Syntax
     * @return array<int,int[]>
     */
    public static function parse(string $expression): array
    {
        $expression = trim($expression);

        // Named interval umwandeln (z. B. @hourly)
        if (str_starts_with($expression, '@')) {
            $expression = strtr($expression, self::$intervals);
            if (str_starts_with($expression, '@')) {
                throw new Exception("Unknown named interval: {$expression}", 10000);
            }
        }

        // In Segmente teilen
        $parts = preg_split('/\s+/', $expression);
        if (!$parts || count($parts) !== 5) {
            throw new Exception("Invalid cron expression: expected 5 segments, got " . count($parts), 10001);
        }

        $result = [];
        foreach ($parts as $idx => $segment) {
            $result[$idx] = self::expandSegment($idx, $segment);
        }

        return $result;
    }

    /**
     * Expandiert ein einzelnes Segment in Zahlenwerte.
     * @throws Exception
     * @return int[]
     */
    private static function expandSegment(int $idx, string $segment): array
    {
        $original = $segment;

        // Monat/Wochentag-Keywords ersetzen
        if (isset(self::$keywords[$idx])) {
            $segment = preg_replace(
                array_keys(self::$keywords[$idx]),
                array_values(self::$keywords[$idx]),
                $segment
            );
        }

        // Wildcards * → vollständiger Bereich
        if (preg_match('/^\*(?:\/(\d+))?$/', $segment, $m)) {
            $step = isset($m[1]) ? (int)$m[1] : 1;
            $segment = self::$ranges[$idx]['min'] . '-' . self::$ranges[$idx]['max'] . '/' . $step;
        }

        // Sicherheitsprüfung
        if (preg_match('/[^0-9,\-\/]/', $segment)) {
            throw new Exception("Failed to parse segment: {$original}", 10002);
        }

        // Mehrfachwerte splitten
        $values = [];
        foreach (explode(',', $segment) as $atom) {
            $values = array_merge($values, self::parseAtom($atom));
        }

        $values = array_unique($values);
        sort($values);

        // Woche 0/7 behandeln
        if ($idx === IDX_WEEKDAY && end($values) === 7) {
            if (reset($values) !== 0) {
                array_unshift($values, 0);
            }
            array_pop($values);
        }

        // Bereichsprüfung
        foreach ($values as $v) {
            if ($v < self::$ranges[$idx]['min'] || $v > self::$ranges[$idx]['max']) {
                throw new Exception("Invalid value {$v} in segment: {$original}", 10003);
            }
        }

        return $values;
    }

    /**
     * Zerlegt einen Atom-String (z. B. „5“, „1-10/2“) in Werte.
     * @return int[]
     */
    private static function parseAtom(string $atom): array
    {
        $atom = trim($atom);
        if ($atom === '') {
            return [];
        }

        if (preg_match('/^(\d+)-(\d+)(?:\/(\d+))?$/', $atom, $m)) {
            $low  = (int)$m[1];
            $high = (int)$m[2];
            if ($low > $high) {
                [$low, $high] = [$high, $low];
            }
            $step = isset($m[3]) ? max(1, (int)$m[3]) : 1;

            $out = [];
            for ($i = $low; $i <= $high; $i += $step) {
                $out[] = $i;
            }
            return $out;
        }

        return [(int)$atom];
    }
}
