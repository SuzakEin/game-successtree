<?php

declare(strict_types=1);

namespace SuccessTree\Service;

/**
 * Position presets (world units, origin at 0,0, y pointing DOWN). Mirrors SuccessTree.layouts in JS.
 */
final class Layout
{
    /**
     * Heart preset (SPEC §8): 9 hubs on the curve x = 16 sin³t, y = -(13 cos t − 5 cos 2t − 2 cos 3t − cos 4t),
     * normalised by 16 (so |x| ≤ 1) then scaled by $r. Hubs at t = π + 2πk/9 (k = 0..8):
     * k = 0 is the bottom tip, k and 9−k are left/right mirrors. The top dip (t = 0) stays empty.
     * The 10th (central) hub sits on the origin at (0,0).
     *
     * @return array{points: array<int, array{x: float, y: float}>, center: array{x: float, y: float}}
     */
    public static function heart(float $r = 420.0): array
    {
        // Equal arc-length spacing, starting at the bottom tip (t = pi): one hub in the tip,
        // then 4 mirrored pairs; the top dip (t = 2pi, half the perimeter) stays empty.
        $steps = 1440;
        $lengths = [0.0];
        $prev = self::heartPoint(M_PI, 1.0);
        for ($i = 1; $i <= $steps; $i++) {
            $cur = self::heartPoint(M_PI + 2 * M_PI * $i / $steps, 1.0);
            $lengths[$i] = $lengths[$i - 1] + hypot($cur['x'] - $prev['x'], $cur['y'] - $prev['y']);
            $prev = $cur;
        }
        $total = $lengths[$steps];
        $points = [];
        $i = 0;
        for ($k = 0; $k < 9; $k++) {
            $target = $total * $k / 9;
            while ($i < $steps && $lengths[$i + 1] < $target) {
                $i++;
            }
            $seg = $lengths[$i + 1] - $lengths[$i];
            $frac = $seg > 0 ? ($target - $lengths[$i]) / $seg : 0.0;
            $points[] = self::heartPoint(M_PI + 2 * M_PI * ($i + $frac) / $steps, $r);
        }
        return ['points' => $points, 'center' => ['x' => 0.0, 'y' => 0.0]];
    }

    /** One point of the normalised heart curve. */
    public static function heartPoint(float $t, float $r = 420.0): array
    {
        $x = 16 * pow(sin($t), 3);
        $y = -(13 * cos($t) - 5 * cos(2 * $t) - 2 * cos(3 * $t) - cos(4 * $t));
        return ['x' => self::clean($x / 16 * $r), 'y' => self::clean($y / 16 * $r)];
    }

    /**
     * $n points evenly spread on a circle of radius $r, starting at $start (radians, default: top).
     *
     * @return array<int, array{x: float, y: float}>
     */
    public static function radial(int $n, float $r = 300.0, float $start = -M_PI / 2): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $a = $start + 2 * M_PI * $i / max(1, $n);
            $out[] = ['x' => self::clean(cos($a) * $r), 'y' => self::clean(sin($a) * $r)];
        }
        return $out;
    }

    /**
     * Concentric rings: $counts[i] points on ring i (radius = ($i + 1) * $spacing), each ring
     * rotated by half a step relative to the previous one.
     *
     * @param int[] $counts
     * @return array<int, array{x: float, y: float, ring: int}>
     */
    public static function ring(array $counts, float $spacing = 160.0): array
    {
        $out = [];
        foreach (array_values($counts) as $i => $n) {
            $n = (int)$n;
            if ($n <= 0) {
                continue;
            }
            $offset = ($i % 2) * (M_PI / $n);
            foreach (self::radial($n, ($i + 1) * $spacing, -M_PI / 2 + $offset) as $p) {
                $p['ring'] = $i;
                $out[] = $p;
            }
        }
        return $out;
    }

    /** Application-side unique id, e.g. "n_3f9a1c0b2d4e" (matches the uid regex). */
    public static function uid(string $prefix = 'n'): string
    {
        $prefix = preg_replace('/[^A-Za-z0-9_\-:.]/', '', $prefix);
        return substr(($prefix === '' ? 'n' : $prefix) . '_' . bin2hex(random_bytes(6)), 0, 64);
    }

    private static function clean(float $v): float
    {
        $v = round($v, 4);
        return $v == 0.0 ? 0.0 : $v; // avoid -0
    }
}
