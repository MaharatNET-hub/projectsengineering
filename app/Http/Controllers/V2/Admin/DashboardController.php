<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\V2\Activity;
use App\Models\V2\Client;
use App\Models\V2\Message;
use App\Models\V2\Study;
use App\Models\V2\Submission;

class DashboardController extends Controller
{
    public function index()
    {
        $me = auth()->user();
        if (! $me->isAdmin()) {
            return $this->engineer($me);
        }

        // studies per week, last 8 weeks (oldest first)
        $weeks = [];
        for ($i = 7; $i >= 0; $i--) {
            $start = now()->startOfWeek()->subWeeks($i);
            $weeks[] = ['label' => $start->translatedFormat('d M'), 'count' => Study::whereBetween('created_at', [$start, (clone $start)->endOfWeek()])->count()];
        }
        $decisions = Study::whereNotNull('decision')->where('status', 'issued')->selectRaw('decision, count(*) as n')->groupBy('decision')->orderByDesc('n')->pluck('n', 'decision');
        $issued = Study::whereNotNull('issued_at')->get(['created_at', 'issued_at']);
        $hours = $issued->map(fn ($s) => $s->created_at->diffInMinutes($s->issued_at) / 60);

        return view('v2.admin.dashboard', [
            'kpi' => [
                'waiting' => Study::where('status', 'submitted')->count(),
                'review' => Study::where('status', 'review')->count(),
                'issuedMonth' => Study::where('issued_at', '>=', now()->startOfMonth())->count(),
                'total' => Study::count(),
                'clients' => Client::count(),
                'unread' => Message::whereNull('read_at')->count(),
                'engineers' => User::where('active', true)->count(),
                'unassigned' => Study::whereIn('status', ['submitted', 'review'])->whereNull('assigned_to')->count(),
                'submissions' => Submission::whereIn('status', ['received', 'analysing', 'review'])->count(),
                'turnaround' => $hours->count() ? $hours->avg() : null,
            ],
            'weeks' => $weeks,
            'decisions' => $decisions,
            'recent' => Study::with('assignee')->latest()->take(8)->get(),
            'activity' => Activity::with('submission', 'study', 'user')->latest()->take(10)->get(),
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
            'studies' => Study::where('assigned_to', $me->id)->whereIn('status', ['submitted', 'review'])->oldest()->get(),
            'openStudies' => Study::whereNull('assigned_to')->whereIn('status', ['submitted', 'review'])->oldest()->take(10)->get(),
            'issuedMonth' => Submission::where('assigned_to', $me->id)->where('issued_at', '>=', now()->startOfMonth())->count()
                + Study::where('assigned_to', $me->id)->where('issued_at', '>=', now()->startOfMonth())->count(),
            'categories' => $cats,
        ]);
    }
}
