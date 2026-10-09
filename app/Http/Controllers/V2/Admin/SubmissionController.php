<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Mail\V2\ReviewIssued;
use App\Models\User;
use App\Models\V2\Activity;
use App\Models\V2\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SubmissionController extends Controller
{
    /**
     * What an office user may see: admins everything; an engineer the requests assigned to them and the
     * unassigned ones of their categories.
     */
    public static function visible($user)
    {
        $q = Submission::query();
        if (! $user->isAdmin()) {
            $cats = $user->categories()->pluck('v2_categories.id');
            $q->where(fn ($w) => $w->where('assigned_to', $user->id)->orWhere(fn ($x) => $x->whereNull('assigned_to')->whereIn('category_id', $cats)));
        }

        return $q;
    }

    /** Admins edit any request; an engineer their own, or an unassigned one of their categories (which they then take). */
    public static function canEdit(Submission $s): bool
    {
        $u = auth()->user();

        return $u->isAdmin() || $s->assigned_to === $u->id || (! $s->assigned_to && $u->categories()->whereKey($s->category_id)->exists());
    }

    private function authorizeView(Submission $s): void
    {
        abort_unless(self::visible(auth()->user())->whereKey($s->id)->exists(), 403, 'This request belongs to another engineer.');
    }

    private function filtered(Request $r)
    {
        $q = self::visible($r->user())->with('category', 'assignee')->latest();
        if ($r->filled('category')) {
            $q->where('category_id', $r->query('category'));
        }
        match ($r->query('who')) {
            'mine' => $q->where('assigned_to', $r->user()->id),
            'none' => $q->whereNull('assigned_to'),
            default => null,
        };
        if ($r->filled('status')) {
            $q->where('status', $r->query('status'));
        }
        if ($r->filled('q')) {
            $term = '%' . $r->query('q') . '%';
            $q->where(fn ($w) => $w->where('code', 'like', $term)->orWhere('project_name', 'like', $term)->orWhere('client_name', 'like', $term)
                ->orWhere('client_company', 'like', $term)->orWhere('client_email', 'like', $term)->orWhere('title', 'like', $term)->orWhere('submittal_no', 'like', $term));
        }

        return $q;
    }

    public function index(Request $r)
    {
        $counts = self::visible($r->user())->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('v2.admin.submissions.index', ['items' => $this->filtered($r)->paginate(20)->withQueryString(), 'counts' => $counts, 'categories' => \App\Models\V2\Category::orderBy('sort')->get()]);
    }

    /** Spreadsheet of the (filtered) list — opens in Excel. */
    public function export(Request $r)
    {
        $rows = $this->filtered($r)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Arabic correctly
            fputcsv($out, ['Code', 'Status', 'Received', 'Client', 'Company', 'Email', 'Phone', 'Project', 'Submittal no.', 'Title', 'Category', 'Pages', 'Comments', 'Decision', 'Engineer', 'Issued', 'Emailed']);
            foreach ($rows as $s) {
                fputcsv($out, [$s->code, $s->status, $s->created_at?->format('Y-m-d H:i'), $s->client_name, $s->client_company, $s->client_email, $s->client_phone, $s->project_name,
                    $s->submittal_no, $s->title, $s->category?->name_en ?? $s->discipline, $s->page_count, $s->comment_count, $s->decision, $s->engineer, $s->issued_at?->format('Y-m-d H:i'), $s->emailed_at?->format('Y-m-d H:i')]);
            }
            fclose($out);
        }, 'submissions-' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Submission $submission)
    {
        $this->authorizeView($submission);

        return view('v2.admin.submissions.show', [
            's' => $submission, 'review' => $submission->review(), 'activity' => $submission->activities()->with('user')->get(),
            'canEdit' => self::canEdit($submission), 'engineers' => User::where('active', true)->orderBy('name')->get(),
            'letter' => \App\V2\Letter::draft($submission),
        ]);
    }

    public function assign(Request $r, Submission $submission)
    {
        $this->authorizeView($submission);

        $to = $r->validate(['user' => 'nullable|exists:users,id'])['user'] ?? null;
        $me = $r->user();
        // engineers take an unassigned request of their categories or hand back their own
        abort_unless($me->isAdmin() || (! $submission->assigned_to && (int) $to === $me->id && self::canEdit($submission)) || ($submission->assigned_to === $me->id && ! $to), 403);
        $user = $to ? User::where('active', true)->findOrFail($to) : null;
        $submission->update(['assigned_to' => $user?->id]);
        Activity::log($submission, 'submission.assigned', $user ? "to {$user->name}" : 'unassigned');

        return back()->with('ok', $user ? __('Assigned to :name.', ['name' => $user->name]) : __('Unassigned.'));
    }

    /**
     * Start (again) with the category's current criteria: the review folder is prepared from the category
     * and the engineer is sent to the check screen, which reads the file and applies the criteria.
     */
    public function restart(Submission $submission)
    {
        $this->authorizeView($submission);

        abort_unless(self::canEdit($submission), 403);
        abort_unless($submission->hasOriginal(), 404);
        if (! $submission->assigned_to) {
            $submission->update(['assigned_to' => auth()->id()]);
        }
        \App\V2\Submissions::prepare($submission, true);
        \App\Review\Store::using($submission->dir(), fn () => \App\Review\Store::delete('review.json', 'overrides.json', 'extracted.json', 'job.json', 'pages.json'));
        Activity::log($submission, 'analysis.restarted', 'criteria of ' . ($submission->category?->name_en ?? 'the default template'));

        return redirect()->route('v2.admin.submissions.workspace', [$submission, 'start' => 1]);
    }

    public function update(Request $r, Submission $submission)
    {
        $this->authorizeView($submission);

        $data = $r->validate(['status' => 'required|in:' . implode(',', Submission::STATUSES)]);
        if ($data['status'] !== $submission->status) {
            $submission->update($data);
            Activity::log($submission, 'status.' . $data['status'], 'changed by hand');
        }

        return back()->with('ok', __('Status updated.'));
    }

    public function destroy(Submission $submission)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $code = $submission->code;
        $submission->delete();

        return redirect()->route('v2.admin.submissions')->with('ok', __('Submission :code and its files were deleted.', ['code' => $code]));
    }

    public function original(Submission $submission)
    {
        $this->authorizeView($submission);

        abort_unless($submission->hasOriginal(), 404);

        return response()->download($submission->originalPath(), $submission->file_name ?: 'submittal.pdf');
    }

    public function issued(Submission $submission)
    {
        $this->authorizeView($submission);

        abort_unless($f = $submission->outputPath(), 404);

        return response()->download($f, basename($f));
    }

    public function workspace(Submission $submission)
    {
        $this->authorizeView($submission);

        return view('v2.admin.submissions.workspace', ['s' => $submission]);
    }

    /** Email the reviewed file to the client. */
    public function email(Request $r, Submission $submission)
    {
        $this->authorizeView($submission);

        $data = $r->validate(['note' => 'nullable|string|max:3000', 'to' => 'nullable|email', 'letter_subject' => 'nullable|string|max:255', 'letter_body' => 'nullable|string|max:8000']);
        if (! $submission->outputPath()) {
            return back()->with('bad', __('Generate the reviewed PDF first.'));
        }
        if (! ($submission->review()['final'] ?? false)) {
            return back()->with('bad', __('The review is still a draft: open the analysis and press "Approve & generate" first.'));
        }
        $to = ($data['to'] ?? null) ?: $submission->client_email;
        try {
            $letter = filled($data['letter_body'] ?? null) ? ['subject' => $data['letter_subject'] ?: \App\V2\Letter::draft($submission)['subject'], 'body' => $data['letter_body']] : null;
            $mail = new ReviewIssued($submission, (string) ($data['note'] ?? ''), $letter, $submission->assignee ?? $r->user());
            Mail::to($to)->send($mail);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('bad', __('Email failed: :error', ['error' => $e->getMessage()]));
        }
        $submission->update(['emailed_at' => now(), 'status' => 'issued', 'issued_at' => $submission->issued_at ?? now()]);
        Activity::log($submission, 'review.emailed', "to $to" . ($mail->attached ? ' · PDF attached' : ' · download link'));
        $logOnly = in_array(config('mail.default'), ['log', 'array'], true);

        return back()->with($logOnly ? 'bad' : 'ok', $logOnly
            ? __('No mail server is configured (MAIL_MAILER=:mailer): the email to :to was written to storage/logs/mail.log instead of being sent. Set the SMTP settings in .env.', ['mailer' => config('mail.default'), 'to' => $to])
            : __('Review emailed to :to.', ['to' => $to]));
    }
}
