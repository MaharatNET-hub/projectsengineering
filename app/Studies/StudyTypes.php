<?php

namespace App\Studies;

use Illuminate\Support\Facades\File;

/**
 * The study types offered on the form. Each one is a JSON definition (fields, rules, tables) shipped in
 * resources/studies/; an engineer can edit a copy from the admin, saved to storage/app/studies/types/.
 */
final class StudyTypes
{
    public const OPERATORS = ['gte', 'lte', 'gt', 'lt', 'eq', 'neq', 'in', 'notIn', 'between'];

    public const FIELD_TYPES = ['text', 'textarea', 'number', 'select', 'bool'];

    public const SEVERITIES = ['fail', 'warn'];

    /** @return array<string,array> key => definition, in a stable order */
    public static function all(): array
    {
        $out = [];
        foreach (glob(resource_path('studies/*.json')) ?: [] as $f) {
            $key = basename($f, '.json');
            if ($def = self::find($key)) {
                $out[$key] = $def;
            }
        }

        return $out;
    }

    public static function find(string $key): ?array
    {
        if (! preg_match('/^[a-z0-9_]+$/', $key)) {
            return null;
        }
        $f = is_file(self::overridePath($key)) ? self::overridePath($key) : self::defaultPath($key);
        if (! is_file($f)) {
            return null;
        }
        $def = json_decode((string) file_get_contents($f), true);

        return is_array($def) ? $def + ['key' => $key] : null;
    }

    public static function defaultPath(string $key): string
    {
        return resource_path("studies/$key.json");
    }

    public static function overridePath(string $key): string
    {
        return storage_path("app/studies/types/$key.json");
    }

    public static function isEdited(string $key): bool
    {
        return is_file(self::overridePath($key));
    }

    public static function save(string $key, array $def): void
    {
        File::ensureDirectoryExists(dirname(self::overridePath($key)));
        $def['key'] = $key;
        file_put_contents(self::overridePath($key), json_encode($def, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    public static function reset(string $key): void
    {
        @unlink(self::overridePath($key));
    }

    /** Text in the visitor's language ({en, ar} objects or a plain string). */
    public static function t(mixed $v, ?string $locale = null): string
    {
        if (is_array($v)) {
            $locale ??= app()->getLocale();

            return (string) ($v[$locale] ?? $v['en'] ?? reset($v) ?: '');
        }

        return (string) ($v ?? '');
    }

    /** Section by key. */
    public static function section(array $def, string $key): ?array
    {
        foreach ($def['sections'] ?? [] as $s) {
            if ($s['key'] === $key) {
                return $s;
            }
        }

        return null;
    }

    /** Field definition (form fields and computed values) for "section.field". */
    public static function field(array $def, string $section, string $field): ?array
    {
        foreach (self::section($def, $section)['fields'] ?? [] as $f) {
            if ($f['key'] === $field) {
                return $f;
            }
        }
        foreach ($def['computed'][$section] ?? [] as $f) {
            if ($f['key'] === $field) {
                return $f + ['type' => 'number', 'computed' => true];
            }
        }

        return null;
    }

    /** Display a stored value: option label, yes/no, number + unit. */
    public static function display(array $field, mixed $value, ?string $locale = null, bool $unit = true): string
    {
        $locale ??= app()->getLocale();
        if ($value === null || $value === '') {
            return '–';
        }
        if (($field['type'] ?? '') === 'bool') {
            return $locale === 'ar' ? ($value ? 'نعم' : 'لا') : ($value ? 'Yes' : 'No');
        }
        if (($field['type'] ?? '') === 'select') {
            foreach ($field['options'] ?? [] as $o) {
                if ((string) $o['value'] === (string) $value) {
                    return self::t($o['label'], $locale);
                }
            }
        }
        if (is_float($value) || is_int($value)) {
            $value = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        }
        $u = $unit && ! empty($field['unit']) && ($field['type'] ?? '') !== 'select' ? ' ' . $field['unit'] : '';

        return $value . $u;
    }

    /**
     * Check a definition before saving it from the admin editor.
     *
     * @return list<string> problems (empty = valid)
     */
    public static function problems(array $def): array
    {
        $p = [];
        if (empty($def['name']) || empty($def['sections']) || ! is_array($def['sections'])) {
            return ['"name" and a non-empty "sections" list are required.'];
        }
        $fields = [];
        foreach ($def['sections'] as $i => $s) {
            if (empty($s['key']) || ! preg_match('/^[a-z0-9_]+$/', (string) $s['key'])) {
                $p[] = "sections[$i]: \"key\" must be lower-case letters, digits or _.";

                continue;
            }
            foreach ($s['fields'] ?? [] as $j => $f) {
                if (empty($f['key']) || ! preg_match('/^[a-z0-9_]+$/', (string) $f['key'])) {
                    $p[] = "{$s['key']}.fields[$j]: invalid \"key\".";

                    continue;
                }
                if (! in_array($f['type'] ?? '', self::FIELD_TYPES, true)) {
                    $p[] = "{$s['key']}.{$f['key']}: type must be one of " . implode(', ', self::FIELD_TYPES) . '.';
                }
                if (($f['type'] ?? '') === 'select' && empty($f['options'])) {
                    $p[] = "{$s['key']}.{$f['key']}: a select needs \"options\".";
                }
                $fields[$s['key']][] = $f['key'];
            }
            foreach ($def['computed'][$s['key']] ?? [] as $c) {
                $fields[$s['key']][] = $c['key'] ?? '';
            }
        }
        foreach ($def['rules'] ?? [] as $i => $r) {
            $id = $r['id'] ?? "rules[$i]";
            if (! isset($fields[$r['section'] ?? ''])) {
                $p[] = "$id: unknown section \"" . ($r['section'] ?? '') . '".';

                continue;
            }
            if (! in_array($r['field'] ?? '', $fields[$r['section']], true)) {
                $p[] = "$id: unknown field \"" . ($r['field'] ?? '') . "\" in {$r['section']}.";
            }
            if (! in_array($r['op'] ?? '', self::OPERATORS, true)) {
                $p[] = "$id: op must be one of " . implode(', ', self::OPERATORS) . '.';
            }
            if (! in_array($r['severity'] ?? 'fail', self::SEVERITIES, true)) {
                $p[] = "$id: severity must be fail or warn.";
            }
            if (! array_key_exists('value', $r)) {
                $p[] = "$id: \"value\" is required.";
            }
        }
        if (! empty($def['calc']) && ! Calculators::exists($def['calc'])) {
            $p[] = 'Unknown calc "' . $def['calc'] . '" (available: ' . implode(', ', Calculators::names()) . ').';
        }

        return $p;
    }
}
