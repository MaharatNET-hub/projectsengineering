<?php

namespace App\Studies;

/**
 * Compares what the visitor typed with what the PDF extractor (App\Review\Extractor) read from the
 * supporting file, and turns the file's panels into form rows for "fill from file". Only the
 * "switchgear" extractor exists today; its heuristics were written for one data-sheet layout, so a file
 * it cannot read simply produces no comparison.
 */
final class CrossCheck
{
    /** Normalised values per panel name from extracted.json. */
    public static function panels(array $extracted): array
    {
        $out = [];
        foreach ($extracted['panels'] ?? [] as $name => $p) {
            $v = $p['values'] ?? [];
            $first = fn (string $k) => $v[$k]['list'][0]['value'] ?? $v[$k]['value'] ?? null;
            $page = fn (string $k) => $v[$k]['page'] ?? null;
            $row = ['name' => $name, 'kind' => self::kind($p['kind'] ?? explode('-', $name)[0]), 'pages' => []];
            if ($ip = $first('ip')) {
                $row['ip'] = preg_replace('/\D/', '', $ip);
                $row['pages']['ip'] = $page('ip');
            }
            if ($form = $first('form')) {
                $row['form'] = self::form($form);
                $row['pages']['form'] = $page('form');
            }
            $wires = array_map(fn ($o) => (float) $o['value'], $v['auxWire']['list'] ?? []);
            if ($wires) {
                $row['aux_wire'] = rtrim(rtrim(number_format(min($wires), 1, '.', ''), '0'), '.');
                $row['pages']['aux_wire'] = $page('auxWire');
            }
            if ($bus = $first('busbar')) {
                $row['busbar'] = str_starts_with(strtoupper($bus), 'AL') ? 'Aluminium' : 'Copper';
            }
            if (array_key_exists('tinPlated', $v)) {
                $row['tin_plated'] = (bool) $v['tinPlated'];
            }
            // material lists are read for heaters / thermostats; absence is only meaningful if one was read
            $hasMaterial = (bool) array_filter($p['pages'] ?? [], fn ($pg) => in_array($pg['type'], ['MATERIAL', 'SLD'], true));
            if ($hasMaterial) {
                $row['heater'] = (bool) ($v['hasHeater'] ?? false);
                $row['thermostat'] = (bool) ($v['hasThermostat'] ?? false);
            }
            $out[$name] = $row;
        }

        return $out;
    }

    private static function kind(string $k): string
    {
        return match (strtoupper($k)) {
            'EMDB', 'ESMDB' => 'EMDB', 'SMDB' => 'SMDB', 'MDB' => 'MDB', 'MCC' => 'MCC', default => 'DB',
        };
    }

    /** "FORM-4, TYPE-6" → "4-6", "FORM-3B" → "3b", "FORM-2" → "2" (type not declared). */
    private static function form(string $s): string
    {
        $s = strtoupper($s);
        preg_match('/FORM-?\s*(\d)\s*([AB])?/', $s, $m);
        $form = ($m[1] ?? '') . strtolower($m[2] ?? '');
        if (preg_match('/TYPE-?\s*(\d)/', $s, $t)) {
            $form .= '-' . $t[1];
        }

        return $form;
    }

    /** Rows for the panels section, ready to fill the form. */
    public static function prefill(array $extracted): array
    {
        return array_values(array_map(function ($r) {
            unset($r['pages']);

            return $r;
        }, self::panels($extracted)));
    }

    /** @return array{ran:bool,panels:int,matched:int,findings:list<array>} */
    public static function run(array $def, array $values, array $extracted): array
    {
        $file = self::panels($extracted);
        $res = ['ran' => true, 'panels' => count($file), 'matched' => 0, 'pageCount' => $extracted['pageCount'] ?? null, 'findings' => []];
        if (! $file) {
            return $res;
        }
        $byName = [];
        foreach ($file as $name => $row) {
            $byName[self::key($name)] = $row;
        }
        $fields = ['ip', 'form', 'aux_wire', 'busbar', 'tin_plated', 'heater', 'thermostat'];
        $entered = [];
        foreach ($values['panels'] ?? [] as $row) {
            $k = self::key((string) ($row['name'] ?? ''));
            $entered[$k] = true;
            $f = $byName[$k] ?? null;
            if (! $f) {
                $res['findings'][] = self::finding('X0', 'name', [$row['name']], [
                    'en' => "Panel {$row['name']} is in the form but was not found in the supporting file — submit its data sheet / GA drawing.",
                    'ar' => "اللوحة {$row['name']} مُدخلة في النموذج لكنها غير موجودة في الملف الداعم — يرجى تقديم جدول بياناتها / مخطط GA.",
                ], ['en' => 'Panel missing from the file', 'ar' => 'لوحة غير موجودة في الملف']);

                continue;
            }
            $res['matched']++;
            foreach ($fields as $key) {
                if (! array_key_exists($key, $f) || ($row[$key] ?? null) === null) {
                    continue;
                }
                $field = StudyTypes::field($def, 'panels', $key) ?? ['type' => 'text'];
                $a = $row[$key];
                $b = $f[$key];
                $same = $key === 'form' && ! str_contains((string) $b, '-') && ! ctype_alpha(substr((string) $b, -1))
                    ? explode('-', (string) $a)[0] === (string) $b // the file gives the form without its type
                    : Analyzer::compare('eq', $a, $b);
                if ($same) {
                    continue;
                }
                $label = $field['label'] ?? $key;
                $page = $f['pages'][$key] ?? null;
                $shown = fn (string $loc) => $key === 'form' && ! str_contains((string) $b, '-') ? 'Form ' . $b : StudyTypes::display($field, $b, $loc, false);
                $res['findings'][] = self::finding('X1', $key, [$row['name']], [
                    'en' => "{$row['name']}: " . StudyTypes::t($label, 'en') . ' entered as ' . StudyTypes::display($field, $a, 'en', false) . ' but the supporting file shows ' . $shown('en') . ($page ? " (page $page)" : '') . '. Clarify which is correct.',
                    'ar' => "{$row['name']}: " . StudyTypes::t($label, 'ar') . ' أُدخلت ' . StudyTypes::display($field, $a, 'ar', false) . ' بينما الملف الداعم يذكر ' . $shown('ar') . ($page ? " (صفحة $page)" : '') . '. يرجى توضيح القيمة الصحيحة.',
                ], ['en' => 'Form ≠ file: ' . StudyTypes::t($label, 'en'), 'ar' => 'النموذج ≠ الملف: ' . StudyTypes::t($label, 'ar')]);
            }
        }
        $extra = array_values(array_filter($file, fn ($r) => ! isset($entered[self::key($r['name'])])));
        if ($extra) {
            $names = implode(', ', array_column($extra, 'name'));
            $res['findings'][] = self::finding('X2', 'name', array_column($extra, 'name'), [
                'en' => "The supporting file also contains $names, which are not in the form — add them or confirm they are not part of this submittal.",
                'ar' => "الملف الداعم يحتوي أيضاً على $names وهي غير مُدخلة في النموذج — يرجى إضافتها أو تأكيد أنها ليست ضمن هذا التقديم.",
            ], ['en' => 'Panels in the file but not in the form', 'ar' => 'لوحات في الملف وليست في النموذج']);
        }

        return $res;
    }

    private static function key(string $name): string
    {
        return strtoupper(preg_replace('/\s+/', '', $name));
    }

    private static function finding(string $id, string $field, array $rows, array $comment, array $label): array
    {
        return ['rule' => $id, 'status' => 'mismatch', 'label' => $label, 'clause' => 'Form vs file', 'section' => 'panels', 'field' => $field, 'rows' => $rows, 'comment' => $comment];
    }
}
