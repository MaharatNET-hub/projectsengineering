<?php

namespace App\Studies;

/**
 * Derived values a study type needs before its rules run (named by "calc" in the definition). Each one
 * adds keys to the rows of a repeated section; the keys are declared under "computed" for display.
 */
final class Calculators
{
    private const MAP = ['cable' => 'cable', 'hvac' => 'hvac'];

    public static function names(): array
    {
        return array_keys(self::MAP);
    }

    public static function exists(string $name): bool
    {
        return isset(self::MAP[$name]);
    }

    /** @param array $values ['section' => fields | list of rows] */
    public static function run(array $def, array $values): array
    {
        $name = $def['calc'] ?? null;
        if (! $name || ! self::exists($name)) {
            return $values;
        }

        $method = self::MAP[$name];

        return self::$method($def, $values);
    }

    private static function num(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * Cable sizing: Ib from the load, Iz from the tabulated rating × derating × parallel runs, voltage
     * drop from the tabulated mV/A/m (per run).
     */
    private static function cable(array $def, array $values): array
    {
        $t = $def['tables'] ?? [];
        $p = $values['project'] ?? [];
        foreach ($values['circuits'] ?? [] as $i => $c) {
            $three = (string) ($c['phases'] ?? '3') === '3';
            $v = self::num($three ? ($p['v3'] ?? null) : ($p['v1'] ?? null));
            $kw = self::num($c['load_kw'] ?? null);
            $pf = self::num($c['pf'] ?? null);
            $len = self::num($c['length_m'] ?? null);
            $runs = max(1, (int) ($c['runs'] ?? 1));
            $size = (string) ($c['size_mm2'] ?? '');
            $al = ($c['material'] ?? 'Cu') === 'Al';
            $rating = $t[$three ? 'rating_3ph' : 'rating_1ph'][$size] ?? null;
            $mv = $t[$three ? 'mv_3ph' : 'mv_1ph'][$size] ?? null;
            if ($al) {
                $rating = $rating !== null ? $rating * ($t['al_rating_factor'] ?? 0.78) : null;
                $mv = $mv !== null ? $mv * ($t['al_mv_factor'] ?? 1.64) : null;
            }
            $ib = $v && $kw !== null && $pf ? $kw * 1000 / (($three ? sqrt(3) : 1) * $v * $pf) : null;
            $iz = $rating !== null ? $rating * (self::num($c['derating'] ?? 1) ?? 1) * $runs : null;
            $vd = $ib !== null && $mv !== null && $len !== null && $v ? ($mv * $ib / $runs * $len / 1000) / $v * 100 : null;
            $limit = self::num(($c['use'] ?? 'power') === 'lighting' ? ($p['vd_lighting'] ?? null) : ($p['vd_power'] ?? null));
            $values['circuits'][$i]['ib_a'] = $ib !== null ? round($ib, 1) : null;
            $values['circuits'][$i]['iz_a'] = $iz !== null ? round($iz, 1) : null;
            $values['circuits'][$i]['vd_pct'] = $vd !== null ? round($vd, 2) : null;
            $values['circuits'][$i]['vd_limit'] = $limit;
        }

        return $values;
    }

    /** HVAC: capacity ratio and the largest capacity the over-sizing margin allows. */
    private static function hvac(array $def, array $values): array
    {
        $margin = self::num($values['project']['oversize_pct'] ?? null) ?? 20;
        foreach ($values['units'] ?? [] as $i => $u) {
            $d = self::num($u['design_kw'] ?? null);
            $o = self::num($u['offered_kw'] ?? null);
            $values['units'][$i]['capacity_pct'] = $d && $o !== null ? round($o / $d * 100, 1) : null;
            $values['units'][$i]['max_kw'] = $d ? round($d * (1 + $margin / 100), 1) : null;
        }

        return $values;
    }
}
