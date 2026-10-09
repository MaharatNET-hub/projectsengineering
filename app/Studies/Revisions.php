<?php

namespace App\Studies;

use App\Models\V2\Study;

/**
 * What changed between two revisions of a study: the entered values (rows matched by their title, e.g.
 * the panel name) and the findings (resolved / still open / new, per rule and row).
 */
final class Revisions
{
    public static function diff(Study $old, Study $new): array
    {
        $def = $new->def() ?? $old->def() ?? ['sections' => []];
        $a = $old->values ?? [];
        $b = $new->values ?? [];
        $changes = [];
        $added = [];
        $removed = [];
        foreach ($def['sections'] as $sec) {
            $k = $sec['key'];
            if (empty($sec['repeat'])) {
                self::compareRow($sec, '', $a[$k] ?? [], $b[$k] ?? [], $changes);

                continue;
            }
            $title = $sec['repeat']['title'] ?? null;
            $key = fn ($row, $i) => $title ? mb_strtoupper(trim((string) ($row[$title] ?? ''))) : (string) $i;
            $oldRows = [];
            foreach ($a[$k] ?? [] as $i => $row) {
                $oldRows[$key($row, $i)] = $row;
            }
            $seen = [];
            foreach ($b[$k] ?? [] as $i => $row) {
                $rk = $key($row, $i);
                $seen[$rk] = true;
                $name = $title ? (string) ($row[$title] ?? '') : '#' . ($i + 1);
                if (! isset($oldRows[$rk])) {
                    $added[] = ['section' => $sec['title'], 'row' => $name];

                    continue;
                }
                self::compareRow($sec, $name, $oldRows[$rk], $row, $changes);
            }
            foreach ($oldRows as $rk => $row) {
                if (! isset($seen[$rk])) {
                    $removed[] = ['section' => $sec['title'], 'row' => $title ? (string) ($row[$title] ?? '') : $rk];
                }
            }
        }

        $pairs = fn (Study $s) => self::pairs($s->isIssued() ? $s->keptFindings() : ($s->analysis['findings'] ?? []));
        $before = $pairs($old);
        $after = $pairs($new);

        return [
            'from' => $old->revLabel(), 'to' => $new->revLabel(),
            'changes' => $changes, 'added' => $added, 'removed' => $removed,
            'resolved' => array_values(array_diff_key($before, $after)),
            'open' => array_values(array_intersect_key($after, $before)),
            'new' => array_values(array_diff_key($after, $before)),
        ];
    }

    private static function compareRow(array $sec, string $row, array $a, array $b, array &$changes): void
    {
        foreach ($sec['fields'] as $f) {
            $x = $a[$f['key']] ?? null;
            $y = $b[$f['key']] ?? null;
            if ((string) json_encode($x) === (string) json_encode($y) || (is_numeric($x) && is_numeric($y) && (float) $x === (float) $y)) {
                continue;
            }
            $changes[] = ['section' => $sec['title'], 'row' => $row, 'field' => $f['key'], 'label' => $f['label'], 'old' => [$f, $x], 'new' => [$f, $y]];
        }
    }

    /** Findings split per row: "rule|row" => {label, row, status}. */
    private static function pairs(array $findings): array
    {
        $out = [];
        foreach ($findings as $f) {
            if (($f['rule'] ?? '') === 'ENG') {
                continue; // free-text engineer comments cannot be matched across revisions
            }
            foreach ($f['rows'] ?: [''] as $row) {
                $out[$f['rule'] . '|' . $f['status'] . '|' . mb_strtoupper((string) $row)] = ['label' => $f['label'], 'row' => (string) $row, 'status' => $f['status'], 'rule' => $f['rule']];
            }
        }

        return $out;
    }
}
