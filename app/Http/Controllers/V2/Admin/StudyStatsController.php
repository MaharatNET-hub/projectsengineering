<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\V2\Study;
use App\Studies\StudyTypes;
use Illuminate\Http\Request;

/** How the review work is going: volume, speed, first-time approval and the most frequent non-compliances. */
class StudyStatsController extends Controller
{
    public const PERIODS = ['30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last 12 months', 'all' => 'All time'];

    public function index(Request $r)
    {
        $period = array_key_exists((string) $r->query('period'), self::PERIODS) ? (string) $r->query('period') : '90';
        $type = (string) $r->query('type', '');
        $q = Study::query()
            ->when($period !== 'all', fn ($w) => $w->where('created_at', '>=', now()->subDays((int) $period)))
            ->when($type !== '', fn ($w) => $w->where('type', $type));
        $studies = $q->get(['id', 'type', 'status', 'decision', 'revision', 'parent_id', 'assigned_to', 'analysis', 'created_at', 'issued_at']);
        $issued = $studies->where('status', 'issued');
        $hours = $issued->filter(fn ($s) => $s->issued_at)->map(fn ($s) => $s->created_at->diffInMinutes($s->issued_at) / 60);
        $firsts = $issued->where('revision', 0);
        $firstOk = $firsts->filter(fn ($s) => in_array($s->decision, ['approved', 'noted'], true))->count();

        // weekly volume, last 12 weeks, oldest first
        $weeks = [];
        for ($i = 11; $i >= 0; $i--) {
            $start = now()->startOfWeek()->subWeeks($i);
            $end = (clone $start)->endOfWeek();
            $weeks[] = ['label' => $start->format('d M'), 'count' => $studies->filter(fn ($s) => $s->created_at->between($start, $end))->count()];
        }

        // most frequent findings: issued reviews count what the engineer kept, others the automated check
        $rules = [];
        foreach ($studies as $s) {
            $list = $s->status === 'issued' ? $s->keptFindings() : ($s->analysis['findings'] ?? []);
            foreach ($list as $f) {
                if (! in_array($f['status'], ['fail', 'mismatch', 'missing'], true) || ($f['rule'] ?? '') === 'ENG') {
                    continue;
                }
                $k = $s->type . '|' . $f['rule'];
                $rules[$k] ??= ['label' => StudyTypes::t($f['label'], 'en'), 'type' => $s->type, 'status' => $f['status'], 'studies' => 0, 'rows' => 0];
                $rules[$k]['studies']++;
                $rules[$k]['rows'] += max(1, count($f['rows'] ?? []));
            }
        }
        usort($rules, fn ($a, $b) => [$b['studies'], $b['rows']] <=> [$a['studies'], $a['rows']]);

        $types = StudyTypes::all();
        $byType = $studies->groupBy('type')->map->count()->sortDesc();
        $decisions = $issued->groupBy('decision')->map->count();
        $engineers = User::orderBy('name')->get()->map(function ($u) use ($studies) {
            $mine = $studies->where('assigned_to', $u->id);
            $done = $mine->where('status', 'issued')->filter(fn ($s) => $s->issued_at);

            return ['name' => $u->name, 'active' => $u->active, 'open' => $mine->whereIn('status', ['submitted', 'review'])->count(), 'issued' => $done->count(),
                'hours' => $done->count() ? $done->avg(fn ($s) => $s->created_at->diffInMinutes($s->issued_at) / 60) : null];
        })->filter(fn ($e) => $e['open'] || $e['issued'] || $e['active'])->values();

        return view('v2.admin.studies.stats', [
            'period' => $period, 'type' => $type, 'types' => $types,
            'kpi' => [
                'total' => $studies->count(),
                'open' => $studies->whereIn('status', ['submitted', 'review'])->count(),
                'unassigned' => $studies->whereIn('status', ['submitted', 'review'])->whereNull('assigned_to')->count(),
                'issued' => $issued->count(),
                'hours' => $hours->count() ? $hours->avg() : null,
                'firstRate' => $firsts->count() ? round($firstOk / $firsts->count() * 100) : null,
                'firsts' => $firsts->count(),
                'resubmissions' => $studies->where('revision', '>', 0)->count(),
            ],
            'weeks' => $weeks, 'rules' => array_slice($rules, 0, 10), 'byType' => $byType, 'decisions' => $decisions, 'engineers' => $engineers,
        ]);
    }

    public static function duration(?float $h): string
    {
        return $h === null ? '–' : ($h < 48 ? round($h, 1) . ' h' : round($h / 24, 1) . ' d');
    }
}
