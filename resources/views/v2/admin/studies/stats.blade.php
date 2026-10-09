@php
  use App\Http\Controllers\V2\Admin\StudyStatsController as Stats;
  use App\Studies\StudyTypes as T;
  $wmax = max(1, max(array_column($weeks, 'count')));
  $rmax = max(1, ...array_map(fn ($r) => $r['studies'], $rules ?: [['studies' => 1]]));
  $tmax = max(1, $byType->max() ?? 1);
  $dmax = max(1, $decisions->max() ?? 1);
@endphp
@extends('v2.layouts.admin', ['title' => 'Study statistics'])
@section('content')
<form class="filters" method="get">
  <div class="seg">@foreach (Stats::PERIODS as $k => $l)<a href="{{ route('v2.admin.studies.stats', array_filter(['period' => $k, 'type' => $type])) }}" class="{{ $period === (string) $k ? 'on' : '' }}">{{ $l }}</a>@endforeach</div>
  <input type="hidden" name="period" value="{{ $period }}">
  <select class="inp" name="type" style="max-width:260px" onchange="this.form.submit()"><option value="">All study types</option>@foreach ($types as $k => $d)<option value="{{ $k }}" @selected($type === $k)>{{ T::t($d['name'], 'en') }}</option>@endforeach</select>
</form>

<div class="kpis">
  <div class="card kpi"><div class="lbl">Studies received</div><b>{{ $kpi['total'] }}</b><small>{{ $kpi['resubmissions'] }} of them revisions</small></div>
  <div class="card kpi"><div class="lbl">Open</div><b style="color:{{ $kpi['open'] ? 'var(--warn)' : 'inherit' }}">{{ $kpi['open'] }}</b><small>{{ $kpi['unassigned'] }} not assigned</small></div>
  <div class="card kpi"><div class="lbl">Issued</div><b>{{ $kpi['issued'] }}</b><small>reviews sent</small></div>
  <div class="card kpi"><div class="lbl">Average time to issue</div><b>{{ Stats::duration($kpi['hours']) }}</b><small>received → issued</small></div>
  <div class="card kpi"><div class="lbl">Approved first time</div><b>{{ $kpi['firstRate'] === null ? '–' : $kpi['firstRate'] . '%' }}</b><small>of {{ $kpi['firsts'] }} Rev 0 reviews (approved / as noted)</small></div>
</div>

<div class="grid g21">
  <div class="card pad">
    <h2>Studies per week</h2>
    <div class="bars" style="--n: {{ count($weeks) }}" role="img" aria-label="Studies per week for the last {{ count($weeks) }} weeks">
      @foreach ($weeks as $w)<div class="b {{ $loop->last ? 'last' : '' }}" title="Week of {{ $w['label'] }}: {{ $w['count'] }}"><span class="v">{{ $w['count'] }}</span><i style="height: {{ round(100 * $w['count'] / $wmax) }}%"></i></div>@endforeach
    </div>
    <div class="bars-x" style="--n: {{ count($weeks) }}">@foreach ($weeks as $w)<span>{{ $w['label'] }}</span>@endforeach</div>
    <table class="sr"><caption>Studies per week</caption><tr><th>Week of</th><th>Studies</th></tr>@foreach ($weeks as $w)<tr><td>{{ $w['label'] }}</td><td>{{ $w['count'] }}</td></tr>@endforeach</table>
  </div>
  <div class="card pad">
    <h2>Decisions issued</h2>
    @forelse ($decisions as $d => $n)
      <div class="hbar" title="{{ __("studies.decision.$d", [], 'en') }}: {{ $n }}"><span>{{ __("studies.decision.$d", [], 'en') }}</span><span class="track"><i style="width: {{ round(100 * $n / $dmax) }}%"></i></span><span class="n">{{ $n }}</span></div>
    @empty
      <p class="mute">No reviews issued in this period.</p>
    @endforelse
  </div>
</div>

<div class="grid g21" style="margin-top:16px">
  <div class="card pad">
    <h2>Most frequent problems</h2>
    <p class="mute" style="margin:-4px 0 10px;font-size:13px">Number of studies with each non-compliance, missing value or form ≠ file finding — what to explain better on the form or in the specification.</p>
    @forelse ($rules as $r)
      <div class="hbar" style="grid-template-columns:minmax(0,1.4fr) 1fr 34px" title="{{ $r['label'] }}: {{ $r['studies'] }} studies, {{ $r['rows'] }} items">
        <span style="min-width:0">{{ $r['label'] }} <span class="mute" style="font-size:12px">· {{ T::t($types[$r['type']]['name'] ?? $r['type'], 'en') }} · {{ __('studies.finding.' . $r['status'], [], 'en') }}</span></span>
        <span class="track"><i style="width: {{ round(100 * $r['studies'] / $rmax) }}%"></i></span><span class="n">{{ $r['studies'] }}</span>
      </div>
    @empty
      <p class="mute">No findings in this period.</p>
    @endforelse
  </div>
  <div class="card pad">
    <h2>By study type</h2>
    @forelse ($byType as $k => $n)
      <div class="hbar" title="{{ $n }}"><span>{{ T::t($types[$k]['name'] ?? $k, 'en') }}</span><span class="track"><i style="width: {{ round(100 * $n / $tmax) }}%"></i></span><span class="n">{{ $n }}</span></div>
    @empty
      <p class="mute">No studies in this period.</p>
    @endforelse
  </div>
</div>

<div class="card" style="margin-top:16px">
  <div class="pad" style="padding-bottom:6px"><h2>Engineers</h2></div>
  <div class="tbl"><table class="t">
    <tr><th>Engineer</th><th class="num">Open</th><th class="num">Issued</th><th class="num">Average time to issue</th></tr>
    @forelse ($engineers as $e)
      <tr style="{{ $e['active'] ? '' : 'opacity:.55' }}"><td>{{ $e['name'] }}</td><td class="num">{{ $e['open'] }}</td><td class="num">{{ $e['issued'] }}</td><td class="num">{{ Stats::duration($e['hours']) }}</td></tr>
    @empty
      <tr><td colspan="4" class="mute">No engineers yet.</td></tr>
    @endforelse
  </table></div>
</div>
@endsection
