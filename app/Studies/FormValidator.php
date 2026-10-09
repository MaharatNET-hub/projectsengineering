<?php

namespace App\Studies;

/**
 * Checks the values a visitor entered against the study type's field definitions and returns the
 * cleaned values (typed, unknown keys dropped) and the errors keyed "section.field" / "section.row.field".
 */
final class FormValidator
{
    /** @return array{0:array,1:array<string,string>} [values, errors] */
    public static function validate(array $def, mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $values = [];
        $errors = [];
        foreach ($def['sections'] as $s) {
            $key = $s['key'];
            if (! empty($s['repeat'])) {
                $rows = array_values(array_filter(is_array($input[$key] ?? null) ? $input[$key] : [], 'is_array'));
                $min = (int) ($s['repeat']['min'] ?? 1);
                $max = (int) ($s['repeat']['max'] ?? 100);
                if (count($rows) < $min) {
                    $errors[$key] = __('studies.err.rows_min', ['n' => $min]);
                }
                if (count($rows) > $max) {
                    $errors[$key] = __('studies.err.rows_max', ['n' => $max]);
                    $rows = array_slice($rows, 0, $max);
                }
                $values[$key] = [];
                foreach ($rows as $i => $row) {
                    $values[$key][$i] = self::fields($s['fields'], $row, "$key.$i", $errors);
                }
                // the title field (e.g. panel name) must be unique: findings refer to rows by it
                $title = $s['repeat']['title'] ?? null;
                if ($title) {
                    $seen = [];
                    foreach ($values[$key] as $i => $row) {
                        $t = mb_strtolower(trim((string) ($row[$title] ?? '')));
                        if ($t !== '' && isset($seen[$t])) {
                            $errors["$key.$i.$title"] = __('studies.err.duplicate');
                        }
                        $seen[$t] = true;
                    }
                }
            } else {
                $values[$key] = self::fields($s['fields'], is_array($input[$key] ?? null) ? $input[$key] : [], $key, $errors);
            }
        }

        return [$values, $errors];
    }

    private static function fields(array $fields, array $in, string $prefix, array &$errors): array
    {
        $out = [];
        foreach ($fields as $f) {
            $k = $f['key'];
            $raw = $in[$k] ?? null;
            if (is_string($raw)) {
                $raw = trim($raw);
            }
            $empty = $raw === null || $raw === '' || $raw === [];
            if ($empty) {
                $out[$k] = null;
                if (! empty($f['required'])) {
                    $errors["$prefix.$k"] = __('studies.err.required');
                }

                continue;
            }
            [$value, $error] = self::cast($f, $raw);
            $out[$k] = $value;
            if ($error) {
                $errors["$prefix.$k"] = $error;
            }
        }

        return $out;
    }

    /** @return array{0:mixed,1:?string} */
    private static function cast(array $f, mixed $raw): array
    {
        switch ($f['type']) {
            case 'number':
                $s = is_string($raw) ? str_replace([',', '٫'], '.', strtr($raw, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'])) : $raw;
                if (! is_numeric($s)) {
                    return [null, __('studies.err.number')];
                }
                $n = (float) $s;
                if (isset($f['min']) && $n < $f['min']) {
                    return [$n, __('studies.err.min', ['min' => $f['min']])];
                }
                if (isset($f['max']) && $n > $f['max']) {
                    return [$n, __('studies.err.max', ['max' => $f['max']])];
                }

                return [$n, null];
            case 'bool':
                $b = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                return $b === null ? [null, __('studies.err.choose')] : [$b, null];
            case 'select':
                foreach ($f['options'] ?? [] as $o) {
                    if ((string) $o['value'] === (string) $raw) {
                        return [(string) $o['value'], null];
                    }
                }

                return [null, __('studies.err.choose')];
            default:
                if (! is_scalar($raw)) {
                    return [null, __('studies.err.required')];
                }
                $s = (string) $raw;
                $max = (int) ($f['max'] ?? ($f['type'] === 'textarea' ? 3000 : 255));

                return mb_strlen($s) > $max ? [mb_substr($s, 0, $max), __('studies.err.long', ['max' => $max])] : [$s, null];
        }
    }
}
