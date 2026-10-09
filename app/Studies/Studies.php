<?php

namespace App\Studies;

use App\Models\V2\Study;

/** Analysis and report for one study. */
final class Studies
{
    /**
     * Run (or re-run) the rules. Findings the engineer already edited keep their edits when the same
     * finding comes out again.
     */
    public static function analyse(Study $s): array
    {
        $def = $s->def();
        if (! $def) {
            throw new \RuntimeException("Unknown study type {$s->type}");
        }
        $a = Analyzer::run($def, $s->values ?? [], $s->extracted());
        $old = [];
        foreach ($s->analysis['findings'] ?? [] as $f) {
            $old[self::key($f)] = $f;
        }
        foreach ($a['findings'] as &$f) {
            if ($prev = $old[self::key($f)] ?? null) {
                $f['include'] = $prev['include'] ?? true;
                if (! empty($prev['edited'])) {
                    $f['comment'] = $prev['comment'];
                    $f['edited'] = true;
                }
            }
        }
        unset($f);
        // engineer-added findings survive a re-run
        foreach ($s->analysis['findings'] ?? [] as $f) {
            if (($f['rule'] ?? '') === 'ENG') {
                $f['no'] = count($a['findings']) + 1;
                $a['findings'][] = $f;
            }
        }
        $a['suggested'] = Analyzer::suggest($a['findings']);
        $s->analysis = $a;
        $s->finding_count = count($s->keptFindings());
        $s->save();

        return $a;
    }

    private static function key(array $f): string
    {
        return implode('|', [$f['rule'], $f['status'], $f['field'] ?? '', implode(',', $f['rows'] ?? [])]);
    }

    /** Rebuild the PDF report; failures do not block the flow (the web report is the reference). */
    public static function report(Study $s): ?string
    {
        try {
            return Report::build($s);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
