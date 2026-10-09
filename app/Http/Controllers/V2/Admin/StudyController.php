<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Mail\V2\StudyIssued;
use App\Models\V2\Study;
use App\Studies\Analyzer;
use App\Studies\Studies;
use App\Studies\StudyTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/** The engineer's side of a study: check the automated findings, edit them, decide and issue. */
class StudyController extends Controller
{
    public function index(Request $r)
    {
        $q = Study::query()->latest();
        if ($r->filled('status')) {
            $q->where('status', $r->query('status'));
        }
        if ($r->filled('type')) {
            $q->where('type', $r->query('type'));
        }
        if ($r->filled('q')) {
            $term = '%' . $r->query('q') . '%';
            $q->where(fn ($w) => $w->where('code', 'like', $term)->orWhere('project_name', 'like', $term)->orWhere('client_name', 'like', $term)
                ->orWhere('client_company', 'like', $term)->orWhere('client_email', 'like', $term)->orWhere('reference', 'like', $term));
        }
        $counts = Study::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('v2.admin.studies.index', ['items' => $q->paginate(20)->withQueryString(), 'counts' => $counts, 'types' => StudyTypes::all()]);
    }

    public function show(Study $study)
    {
        return view('v2.admin.studies.show', ['s' => $study, 'def' => $study->def()]);
    }

    /** Save the engineer's edits; "issue" also makes the review final for the client. */
    public function update(Request $r, Study $study)
    {
        $data = $r->validate([
            'decision' => 'nullable|in:' . implode(',', Analyzer::DECISIONS),
            'remarks' => 'nullable|string|max:5000',
            'engineer' => 'nullable|string|max:120',
            'status' => 'nullable|in:' . implode(',', Study::STATUSES),
            'findings' => 'array',
            'findings.*.include' => 'nullable|boolean',
            'findings.*.en' => 'nullable|string|max:3000',
            'findings.*.ar' => 'nullable|string|max:3000',
            'add_en' => 'nullable|string|max:3000',
            'add_ar' => 'nullable|string|max:3000',
            'add_status' => 'nullable|in:fail,warn',
            'action' => 'nullable|in:save,issue',
        ]);
        $a = $study->analysis ?? ['findings' => []];
        foreach ($a['findings'] as $i => &$f) {
            $in = $data['findings'][$i] ?? [];
            $f['include'] = (bool) ($in['include'] ?? false);
            foreach (['en', 'ar'] as $loc) {
                $text = trim((string) ($in[$loc] ?? ''));
                if ($text !== '' && $text !== ($f['comment'][$loc] ?? '')) {
                    $f['comment'][$loc] = $text;
                    $f['edited'] = true;
                }
            }
        }
        unset($f);
        if (trim((string) ($data['add_en'] ?? '')) !== '' || trim((string) ($data['add_ar'] ?? '')) !== '') {
            $en = trim((string) ($data['add_en'] ?? '')) ?: trim((string) $data['add_ar']);
            $a['findings'][] = [
                'rule' => 'ENG', 'status' => $data['add_status'] ?? 'warn', 'label' => ['en' => 'Engineer comment', 'ar' => 'ملاحظة المهندس'], 'clause' => 'Engineer',
                'section' => '', 'field' => '', 'rows' => [], 'comment' => ['en' => $en, 'ar' => trim((string) ($data['add_ar'] ?? '')) ?: $en],
                'include' => true, 'edited' => true, 'no' => count($a['findings']) + 1,
            ];
        }
        $a['suggested'] = Analyzer::suggest($a['findings']);
        $study->analysis = $a;
        $study->fill(['decision' => $data['decision'] ?? null, 'remarks' => $data['remarks'] ?? null, 'engineer' => $data['engineer'] ?? null]);
        $study->finding_count = count($study->keptFindings());
        if (($data['action'] ?? 'save') === 'issue') {
            if (! $study->decision) {
                return back()->withInput()->with('bad', 'Choose the action (decision) before issuing.');
            }
            $study->status = 'issued';
            $study->issued_at ??= now();
        } elseif (! empty($data['status'])) {
            $study->status = $data['status'];
        } elseif ($study->status === 'submitted') {
            $study->status = 'review';
        }
        $study->save();
        Studies::report($study);

        return back()->with('ok', $study->isIssued() ? 'Saved. The review is issued: the client sees it on the tracking page.' : 'Saved.');
    }

    public function reanalyse(Study $study)
    {
        Studies::analyse($study);
        Studies::report($study);

        return back()->with('ok', 'The rules were run again with the current study-type definition (your edits to matching findings were kept).');
    }

    public function file(Study $study)
    {
        abort_unless($f = $study->filePath(), 404);

        return response()->download($f, $study->file_name ?: basename($f));
    }

    public function report(Study $study)
    {
        $f = Studies::report($study);
        abort_unless($f && is_file($f), 404);

        return response()->download($f, "Study-{$study->code}" . ($study->isIssued() ? '' : '-DRAFT') . '.pdf');
    }

    public function email(Request $r, Study $study)
    {
        $data = $r->validate(['note' => 'nullable|string|max:3000', 'to' => 'nullable|email']);
        if (! $study->isIssued()) {
            return back()->with('bad', 'Issue the review first.');
        }
        Studies::report($study);
        $to = ($data['to'] ?? null) ?: $study->client_email;
        try {
            Mail::to($to)->send(new StudyIssued($study, (string) ($data['note'] ?? '')));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('bad', 'Email failed: ' . $e->getMessage());
        }
        $study->update(['emailed_at' => now()]);
        $logOnly = in_array(config('mail.default'), ['log', 'array'], true);

        return back()->with($logOnly ? 'bad' : 'ok', $logOnly
            ? 'No mail server is configured (MAIL_MAILER=' . config('mail.default') . "): the email to $to was written to the log instead of being sent."
            : "Review emailed to $to.");
    }

    public function destroy(Study $study)
    {
        $code = $study->code;
        $study->delete();

        return redirect()->route('v2.admin.studies')->with('ok', "Study $code and its files were deleted.");
    }
}
