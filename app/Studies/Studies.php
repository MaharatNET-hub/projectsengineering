<?php

namespace App\Studies;

use App\Models\V2\Activity;
use App\Models\V2\Category;
use App\Models\V2\Study;
use App\Models\V2\Submission;
use App\V2\Submissions;
use Illuminate\Support\Facades\File;

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

    /**
     * The study's PDF in the check of its category: a linked submission (same engineer) holds the file, the
     * category's criteria and, once read, the marked-up draft the engineer reviews. Made once; null when the
     * file is not a PDF or no category takes this study type.
     */
    public static function linkCheck(Study $s, ?int $engineer = null): ?Submission
    {
        if ($sub = Submission::where('study_id', $s->id)->first()) {
            return $sub;
        }
        $category = Category::forStudyType($s->type);
        if (! $category || ! $s->isPdf()) {
            return null;
        }
        $sub = Submission::create([
            'study_id' => $s->id, 'status' => 'received', 'category_id' => $category->id,
            'assigned_to' => $s->assigned_to ?? $engineer, 'engineer' => $s->engineer,
            'client_name' => $s->client_name, 'client_company' => $s->client_company, 'client_email' => $s->client_email,
            'client_phone' => $s->client_phone, 'project_name' => $s->project_name, 'submittal_no' => $s->reference,
            'discipline' => $category->discipline ?? 'Other', 'title' => $s->typeName('en'), 'locale' => $s->locale,
            'file_name' => $s->file_name ?: 'study.pdf',
        ]);
        File::ensureDirectoryExists($sub->dir());
        File::copy($s->filePath(), $sub->originalPath());
        $sub->update(['file_size' => filesize($sub->originalPath()), 'page_count' => $s->page_count]);
        Submissions::prepare($sub, true);
        Activity::log($sub, 'submission.received', "from study {$s->code} · {$category->name_en}");
        Activity::forStudy($s, 'study.checked', "file sent to the {$category->name_en} check ({$sub->code})");

        return $sub;
    }

    /**
     * Drive the category check of a study's file one batch at a time. $review false only reads pages; the
     * final call drafts the marked-up review, then the study's own rules run again with what the file says.
     */
    public static function checkStep(Study $s, bool $review): array
    {
        $sub = Submission::where('study_id', $s->id)->first();
        if (! $sub || ! $sub->hasOriginal()) {
            return ['done' => true];
        }
        if ($sub->status === 'received' || ($sub->status === 'analysing' && ! is_file($sub->dir() . '/job.json'))) {
            Submissions::start($sub);
        }
        $r = Submissions::step($sub->fresh(), (float) config('demo.step_seconds', 12), $review);
        if ($review && $r['done'] && empty($r['busy'])) {
            if (is_file($sub->dir() . '/extracted.json')) {
                File::copy($sub->dir() . '/extracted.json', $s->dir() . '/extracted.json');
            }
            self::analyse($s);
            self::report($s);
        }

        return $r;
    }
}
