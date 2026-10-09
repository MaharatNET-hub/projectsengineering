@php
  use App\Http\Controllers\V2\Admin\StudyStatsController as Stats;
  use App\Studies\StudyTypes as T;
  $wmax = max(1, max(array_column($weeks, 'count')));
  $rmax = max(1, ...array_map(fn ($r) => $r['studies'], $rules ?: [['studies' => 1]]));
  $tmax = max(1, $byType->max() ?? 1);
  $dmax = max(1, $decisions->max() ?? 1);
@endphp
@extends('v2.layouts.admin', ['title' => __('Study statistics')])
@section('content')
<form class="filters" method="get">
  <div class="seg">@foreach (Stats::PERIODS as $k => $l)<a href="{{ route('v2.admin.studies.stats', array_filter(['period' => $k, 'type' => $type])) }}" class="{{ $period === (string) $k ? 'on' : '' }}">{{ __($l) }}</a>@endforeach</div>
  <input type="hidden" name="period" value="{{ $period }}">
  <select class="inp" name="type" style="max-width:260px" onchange="this.form.submit()"><option value="">{{ __('All study types') }}</option>@foreach ($types as $k => $d)<option value="{{ $k }}" @selected($type === $k)>{{ T::t($d['name']) }}</option>@endforeach</select>
</form>

<div class="kpis">
  <div class="card kpi"><div class="lbl">{{ __('Studies received') }}</div><b>{{ $kpi['total'] }}</b><small>{{ __(':n of them revisions', ['n' => $kpi['resubmissions']]) }}</small></div>
  <div class="card kpi"><div class="lbl">{{ __('Open') }}</div><b style="color:{{ $kpi['open'] ? 'var(--warn)' : 'inherit' }}">{{ $kpi['open'] }}</b><small>{{ __(':n not assigned', ['n' => $kpi['unassigned']]) }}</small></div>
  <div class="card kpi"><div class="lbl">{{ __('Issued') }}</div><b>{{ $kpi['issued'] }}</b><small>{{ __('reviews sent') }}</small></div>
  <div class="card kpi"><div class="lbl">{{ __('Average time to issue') }}</div><b>{{ Stats::duration($kpi['hours']) }}</b><small>{{ __('received → issued') }}</small></div>
  <div class="card kpi"><div class="lbl">{{ __('Approved first time') }}</div><b>{{ $kpi['firstRate'] === null ? '–' : $kpi['firstRate'] . '%' }}</b><small>{{ __('of :n Rev 0 reviews (approved / as noted)', ['n' => $kpi['firsts']]) }}</small></div>
</div>

<div class="grid g21">
  <div class="card pad">
    <h2>{{ __('Studies per week') }}</h2>
    <div class="bars" style="--n: {{ count($weeks) }}" role="img" aria-label="{{ __('Studies per week for the last :n weeks', ['n' => count($weeks)]) }}">
      @foreach ($weeks as $w)<div class="b {{ $loop->last ? 'last' : '' }}" title="{{ __('Week of :date', ['date' => $w['label']]) }}: {{ $w['count'] }}"><span class="v">{{ $w['count'] }}</span><i style="height: {{ round(100 * $w['count'] / $wmax) }}%"></i></div>@endforeach
    </div>
    <div class="bars-x" style="--n: {{ count($weeks) }}">@foreach ($weeks as $w)<span>{{ $w['label'] }}</span>@endforeach</div>
    <table class="sr"><caption>{{ __('Studies per week') }}</caption><tr><th>{{ __('Week of') }}</th><th>{{ __('Studies') }}</th></tr>@foreach ($weeks as $w)<tr><td>{{ $w['label'] }}</td><td>{{ $w['count'] }}</td></tr>@endforeach</table>
  </div>
  <div class="card pad">
    <h2>{{ __('Decisions issued') }}</h2>
    @forelse ($decisions as $d => $n)
      <div class="hbar" title="{{ __("studies.decision.$d") }}: {{ $n }}"><span>{{ __("studies.decision.$d") }}</span><span class="track"><i style="width: {{ round(100 * $n / $dmax) }}%"></i></span><span class="n">{{ $n }}</span></div>
    @empty
      <p class="mute">{{ __('No reviews issued in this period.') }}</p>
    @endforelse
  </div>
</div>

<div class="grid g21" style="margin-top:16px">
  <div class="card pad">
    <h2>{{ __('Most frequent problems') }}</h2>
    <p class="mute" style="margin:-4px 0 10px;font-size:13px">{{ __('Number of studies with each non-compliance, missing value or form ≠ file finding — what to explain better on the form or in the specification.') }}</p>
    @forelse ($rules as $r)
      <div class="hbar" style="grid-template-columns:minmax(0,1.4fr) 1fr 34px" title="{{ $r['label'] }}: {{ __(':studies studies, :rows items', ['studies' => $r['studies'], 'rows' => $r['rows']]) }}">
        <span style="min-width:0">{{ $r['label'] }} <span class="mute" style="font-size:12px">· {{ T::t($types[$r['type']]['name'] ?? $r['type']) }} · {{ __('studies.finding.' . $r['status']) }}</span></span>
        <span class="track"><i style="width: {{ round(100 * $r['studies'] / $rmax) }}%"></i></span><span class="n">{{ $r['studies'] }}</span>
      </div>
    @empty
      <p class="mute">{{ __('No findings in this period.') }}</p>
    @endforelse
  </div>
  <div class="card pad">
    <h2>{{ __('By study type') }}</h2>
    @forelse ($byType as $k => $n)
      <div class="hbar" title="{{ $n }}"><span>{{ T::t($types[$k]['name'] ?? $k) }}</span><span class="track"><i style="width: {{ round(100 * $n / $tmax) }}%"></i></span><span class="n">{{ $n }}</span></div>
    @empty
      <p class="mute">{{ __('No studies in this period.') }}</p>
    @endforelse
  </div>
</div>

<div class="card" style="margin-top:16px">
  <div class="pad" style="padding-bottom:6px"><h2>{{ __('Engineers') }}</h2></div>
  <div class="tbl"><table class="t">
    <tr><th>{{ __('Engineer') }}</th><th class="num">{{ __('Open') }}</th><th class="num">{{ __('Issued') }}</th><th class="num">{{ __('Average time to issue') }}</th></tr>
    @forelse ($engineers as $e)
      <tr style="{{ $e['active'] ? '' : 'opacity:.55' }}"><td>{{ $e['name'] }}</td><td class="num">{{ $e['open'] }}</td><td class="num">{{ $e['issued'] }}</td><td class="num">{{ Stats::duration($e['hours']) }}</td></tr>
    @empty
      <tr><td colspan="4" class="mute">{{ __('No engineers yet.') }}</td></tr>
    @endforelse
  </table></div>
</div>
@endsection
