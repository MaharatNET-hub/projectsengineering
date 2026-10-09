<?php

namespace App\Studies;

/**
 * Runs a study type's rules on the entered values. A rule looks at one field of a section (every row of
 * a repeated section, optionally filtered by "when") and compares it with a fixed value or another field
 * ({"ref": "field"} in the same row, {"ref": "section.field"} elsewhere).
 *
 * Findings: fail / warn (rule severity), missing (value needed by a rule was left empty) and mismatch
 * (entered value differs from the supporting file — see CrossCheck).
 */
final class Analyzer
{
    public const DECISIONS = ['approved', 'noted', 'revise', 'rejected'];

    /**
     * @param  array|null  $extracted  what the PDF extractor read from the supporting file, if anything
     */
    public static function run(array $def, array $values, ?array $extracted = null): array
    {
        $values = Calculators::run($def, $values);
        $findings = [];
        $checks = [];
        foreach ($def['rules'] ?? [] as $rule) {
            [$check, $found] = self::rule($def, $rule, $values);
            $checks[] = $check;
            array_push($findings, ...$found);
        }
        $cross = null;
        if (! empty($def['extract']) && $extracted) {
            $cross = CrossCheck::run($def, $values, $extracted);
            array_push($findings, ...$cross['findings']);
            unset($cross['findings']);
        }
        foreach ($findings as $i => &$f) {
            $f['no'] = $i + 1;
            $f['include'] = true;
        }
        unset($f);
        $stats = ['fail' => 0, 'warn' => 0, 'missing' => 0, 'mismatch' => 0];
        foreach ($findings as $f) {
            $stats[$f['status']]++;
        }
        $stats['pass'] = count(array_filter($checks, fn ($c) => $c['status'] === 'pass'));
        $stats['checks'] = count($checks);

        return [
            'values' => $values,
            'findings' => $findings,
            'checks' => $checks,
            'stats' => $stats,
            'suggested' => self::suggest($findings),
            'crossCheck' => $cross,
            'typeVersion' => $def['version'] ?? 1,
            'at' => now()->toIso8601String(),
        ];
    }

    /** Suggested action from the findings the engineer kept. */
    public static function suggest(array $findings): string
    {
        $kept = array_filter($findings, fn ($f) => $f['include'] ?? true);
        if (array_filter($kept, fn ($f) => $f['status'] === 'fail')) {
            return 'revise';
        }

        return $kept ? 'noted' : 'approved';
    }

    /** @return array{0:array,1:list<array>} [check summary, findings] */
    private static function rule(array $def, array $rule, array $values): array
    {
        $sec = StudyTypes::section($def, $rule['section']);
        $repeat = ! empty($sec['repeat']);
        $rows = $repeat ? ($values[$rule['section']] ?? []) : [$values[$rule['section']] ?? []];
        $titleKey = $sec['repeat']['title'] ?? null;
        $field = StudyTypes::field($def, $rule['section'], $rule['field']) ?? ['type' => 'text'];
        $check = ['id' => $rule['id'], 'label' => $rule['label'] ?? $rule['field'], 'clause' => $rule['clause'] ?? '', 'severity' => $rule['severity'] ?? 'fail', 'applied' => 0];
        $bad = [];
        $missing = [];
        foreach ($rows as $i => $row) {
            if (! self::applies($rule['when'] ?? [], $row, $values)) {
                continue;
            }
            $check['applied']++;
            $name = $titleKey ? (string) ($row[$titleKey] ?? '#' . ($i + 1)) : '';
            $actual = $row[$rule['field']] ?? null;
            [$expected, $expField] = self::expected($def, $rule, $row, $values);
            if ($actual === null || $actual === '' || $expected === null) {
                $missing[] = $name;

                continue;
            }
            if (! self::compare($rule['op'], $actual, $expected)) {
                $bad[] = ['row' => $name, 'actual' => $actual, 'expected' => $expected, 'expField' => $expField];
            }
        }
        $check['status'] = $check['applied'] === 0 ? 'na' : ($bad ? ($rule['severity'] ?? 'fail') : ($missing ? 'missing' : 'pass'));
        $found = [];
        $rowsText = fn (array $names) => $repeat ? implode(', ', array_filter($names, fn ($n) => $n !== '')) : '';
        if ($bad) {
            $tokens = fn (string $loc) => [
                '{actual}' => self::distinct(array_map(fn ($b) => StudyTypes::display($field, $b['actual'], $loc, false), $bad)),
                '{expected}' => self::distinct(array_map(fn ($b) => self::expectedText($field, $b, $loc), $bad)),
                '{rows}' => $rowsText(array_column($bad, 'row')),
                '{unit}' => $field['unit'] ?? '',
                '{clause}' => $rule['clause'] ?? '',
            ];
            $found[] = [
                'rule' => $rule['id'], 'status' => $rule['severity'] ?? 'fail', 'label' => $rule['label'] ?? $rule['field'], 'clause' => $rule['clause'] ?? '',
                'section' => $rule['section'], 'field' => $rule['field'], 'rows' => array_column($bad, 'row'),
                'comment' => self::render($rule['comment'] ?? ['en' => '{actual} does not meet {expected}.'], $tokens),
            ];
        }
        if ($missing) {
            $label = $rule['label'] ?? $rule['field'];
            $rt = $rowsText($missing);
            $found[] = [
                'rule' => $rule['id'], 'status' => 'missing', 'label' => $label, 'clause' => $rule['clause'] ?? '',
                'section' => $rule['section'], 'field' => $rule['field'], 'rows' => $missing,
                'comment' => [
                    'en' => 'Value not provided — cannot check "' . StudyTypes::t($label, 'en') . '"' . ($rt !== '' ? ". Rows: $rt." : '.'),
                    'ar' => 'القيمة غير مُدخلة — لا يمكن التحقق من "' . StudyTypes::t($label, 'ar') . '"' . ($rt !== '' ? ". البنود: $rt." : '.'),
                ],
            ];
        }

        return [$check, $found];
    }

    /** "when": {"field": [allowed values]} — every listed field must match. */
    private static function applies(array $when, array $row, array $values): bool
    {
        foreach ($when as $k => $allowed) {
            $v = self::lookup($k, $row, $values);
            if (! in_array((string) (is_bool($v) ? (int) $v : $v), array_map(fn ($a) => (string) (is_bool($a) ? (int) $a : $a), (array) $allowed), true)) {
                return false;
            }
        }

        return true;
    }

    private static function lookup(string $ref, array $row, array $values): mixed
    {
        if (str_contains($ref, '.')) {
            [$s, $f] = explode('.', $ref, 2);
            $sv = $values[$s] ?? [];

            return array_is_list($sv) ? null : ($sv[$f] ?? null);
        }

        return $row[$ref] ?? null;
    }

    /** @return array{0:mixed,1:?array} [expected value, the field it comes from (refs)] */
    private static function expected(array $def, array $rule, array $row, array $values): array
    {
        $v = $rule['value'] ?? null;
        if (is_array($v) && isset($v['ref'])) {
            $ref = (string) $v['ref'];
            [$s, $f] = str_contains($ref, '.') ? explode('.', $ref, 2) : [$rule['section'], $ref];

            return [self::lookup($ref, $row, $values), StudyTypes::field($def, $s, $f)];
        }

        return [$v, null];
    }

    public static function compare(string $op, mixed $a, mixed $b): bool
    {
        $num = fn ($x) => is_bool($x) ? (int) $x : (float) $x;
        $str = fn ($x) => is_bool($x) ? ($x ? '1' : '0') : (string) $x;

        return match ($op) {
            'gte' => $num($a) >= $num($b) - 1e-9,
            'lte' => $num($a) <= $num($b) + 1e-9,
            'gt' => $num($a) > $num($b),
            'lt' => $num($a) < $num($b),
            'eq' => is_numeric($a) && is_numeric($b) ? abs($num($a) - $num($b)) < 1e-9 : $str($a) === $str($b),
            'neq' => ! (is_numeric($a) && is_numeric($b) ? abs($num($a) - $num($b)) < 1e-9 : $str($a) === $str($b)),
            'in' => in_array($str($a), array_map($str, (array) $b), true),
            'notIn' => ! in_array($str($a), array_map($str, (array) $b), true),
            'between' => is_array($b) && count($b) === 2 && $num($a) >= $num($b[0]) && $num($a) <= $num($b[1]),
            default => true,
        };
    }

    private static function expectedText(array $field, array $b, string $loc): string
    {
        $e = $b['expected'];
        $f = $b['expField'] ?? $field;
        if (is_array($e)) {
            return implode(' / ', array_map(fn ($x) => StudyTypes::display($f, $x, $loc, false), $e));
        }

        return StudyTypes::display($f, $e, $loc, false);
    }

    private static function distinct(array $list): string
    {
        return implode(', ', array_values(array_unique($list)));
    }

    /** @return array{en:string,ar:string} */
    private static function render(mixed $tpl, callable $tokens): array
    {
        $tpl = is_array($tpl) ? $tpl : ['en' => (string) $tpl];
        $out = [];
        foreach (['en', 'ar'] as $loc) {
            $out[$loc] = strtr($tpl[$loc] ?? $tpl['en'] ?? '', $tokens($loc));
        }

        return $out;
    }
}
