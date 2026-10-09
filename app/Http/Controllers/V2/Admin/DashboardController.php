<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\V2\Activity;
use App\Models\V2\Message;
use App\Models\V2\Submission;

class DashboardController extends Controller
{
    public function index()
    {
        $me = auth()->user();
        if (! $me->isAdmin()) {
            return $this->engineer($me);
        }
        $all = Submission::query();
        $issued = Submission::whereNotNull('issued_at')->get(['created_at', 'issued_at']);
        $hours = $issued->map(fn ($s) => $s->created_at->diffInMinutes($s->issued_at) / 60);

        // submissions per week, last 8 weeks (oldest first)
        $weeks = [];
        for ($i = 7; $i >= 0; $i--) {
            $start = now()->startOfWeek()->subWeeks($i);
            $weeks[] = ['label' => $start->format('d M'), 'count' => Submission::whereBetween('created_at', [$start, (clone $start)->endOfWeek()])->count()];
        }
        $decisions = Submission::whereNotNull('decision')->where('status', 'issued')->selectRaw('decision, count(*) as n')->groupBy('decision')->orderByDesc('n')->pluck('n', 'decision');

        return view('v2.admin.dashboard', [
            'kpi' => [
                'total' => (clone $all)->count(),
                'waiting' => Submission::whereIn('status', ['received', 'analysing', 'review'])->count(),
                'issuedMonth' => Submission::where('issued_at', '>=', now()->startOfMonth())->count(),
                'turnaround' => $hours->count() ? $hours->avg() : null,
                'unread' => Message::whereNull('read_at')->count(),
            ],
            'weeks' => $weeks,
            'decisions' => $decisions,
            'queue' => Submission::whereIn('status', ['received', 'analysing', 'review'])->oldest()->take(6)->get(),
            'recent' => Submission::latest()->take(8)->get(),
            'activity' => Activity::with('submission', 'study', 'user')->latest()->take(10)->get(),
            'messages' => Message::latest()->take(4)->get(),
        ]);
    }

    /** An engineer's dashboard: their requests, what they can take in their categories, their categories. */
    private function engineer($me)
    {
        $cats = $me->categories()->orderBy('sort')->get();
        $open = ['received', 'analysing', 'review'];

        return view('v2.admin.dashboard-engineer', [
            'mine' => Submission::with('category')->where('assigned_to', $me->id)->whereIn('status', $open)->oldest()->get(),
            'free' => Submission::with('category')->whereNull('assigned_to')->whereIn('category_id', $cats->pluck('id'))->whereIn('status', $open)->oldest()->get(),
            'studies' => \App\Models\V2\Study::where('assigned_to', $me->id)->whereIn('status', ['submitted', 'review'])->oldest()->get(),
            'issuedMonth' => Submission::where('assigned_to', $me->id)->where('issued_at', '>=', now()->startOfMonth())->count()
                + \App\Models\V2\Study::where('assigned_to', $me->id)->where('issued_at', '>=', now()->startOfMonth())->count(),
            'categories' => $cats,
        ]);
    }
}
