@extends('v2.layouts.admin', ['title' => __('Home'), 'icon' => 'home', 'subtitle' => __('Live counts of the study requests, the team and the inbox. Open any card for the full list.')])
@section('actions')<a class="btn" href="{{ route('v2.admin.studies.stats') }}"><x-v2.icon name="chart"/>{{ __('Statistics') }}</a><a class="btn primary" href="{{ route('v2.admin.studies', ['status' => 'submitted']) }}"><x-v2.icon name="clipboard"/>{{ __('Review queue') }}</a>@endsection
@section('content')
@php
  $max = max(1, max(array_column($weeks, 'count'))); $dmax = max(1, $decisions->max() ?? 1);
  $cards = [
    ['red', 'clipboard', __('New studies'), $kpi['waiting'], route('v2.admin.studies', ['status' => 'submitted'])],
    ['cyan', 'eye', __('Under review'), $kpi['review'], route('v2.admin.studies', ['status' => 'review'])],
    ['violet', 'check', __('Issued this month'), $kpi['issuedMonth'], route('v2.admin.studies', ['status' => 'issued'])],
    ['sky', 'layers', __('All studies'), $kpi['total'], route('v2.admin.studies')],
    ['amber', 'user', __('Unassigned'), $kpi['unassigned'], route('v2.admin.studies', ['who' => 'none'])],
    ['green', 'users', __('Clients'), $kpi['clients'], route('v2.admin.clients')],
    ['red', 'mail', __('Unread messages'), $kpi['unread'], route('v2.admin.messages')],
    ['slate', 'helmet', __('Active team members'), $kpi['engineers'], route('v2.admin.users')],
  ];
  if ($kpi['submissions']) $cards[] = ['amber', 'inbox', __('Submittals waiting'), $kpi['submissions'], route('v2.admin.submissions')];
@endphp
<div class="snap"><span class="chip"><x-v2.icon name="clock" class="i" style="width:15px;height:15px"/>{{ __('Snapshot') }} {{ now()->format('H:i:s') }}</span><span>{{ __('Average turnaround') }}: <b style="color:var(--ink)">{{ $kpi['turnaround'] === null ? '–' : ($kpi['turnaround'] < 48 ? round($kpi['turnaround'], 1) . ' ' . __('h') : round($kpi['turnaround'] / 24, 1) . ' ' . __('d')) }}</b></span></div>

<div class="stats">
  @foreach ($cards as $i => [$c, $ic, $label, $n, $href])
    <a class="stat-card {{ $c }} {{ $n ? '' : 'zero' }}" style="--d: {{ $i }}" href="{{ $href }}">
      <div><div class="lbl">{{ $label }}</div><b data-count="{{ $n }}">{{ $n }}</b><span class="more">{{ __('View details') }} <x-v2.icon name="ext"/></span></div>
      <span class="ico"><x-v2.icon :name="$ic"/></span>
    </a>
  @endforeach
</div>

<div class="card" style="margin-bottom:22px">
  <div class="card-h"><h2>{{ __('Latest studies') }}</h2><a class="btn primary sm" href="{{ route('v2.admin.studies') }}">{{ __('All studies') }}</a></div>
  @if ($recent->isEmpty())<div class="empty">{{ __('No studies yet. They arrive through the public form at') }} <a href="{{ route('v2.studies') }}" target="_blank">/v2/studies</a>.</div>@else
  <div class="tbl"><table class="t"><tr><th>{{ __('Reference') }}</th><th>{{ __('Project') }}</th><th>{{ __('Client') }}</th><th>{{ __('Engineer') }}</th><th>{{ __('Status') }}</th><th>{{ __('Date') }}</th></tr>
    @foreach ($recent as $s)<tr class="click" onclick="location.href='{{ route('v2.admin.studies.show', $s) }}'"><td class="mono"><a href="{{ route('v2.admin.studies.show', $s) }}">{{ $s->code }}</a></td><td><b>{{ \Illuminate\Support\Str::limit($s->project_name, 38) }}</b><div class="mute" style="font-size:12.5px">{{ $s->typeName() }}</div></td><td>{{ $s->client_name }}</td><td>{!! $s->assignee ? e($s->assignee->name) : '<span class="mute">–</span>' !!}</td><td><span class="chip {{ $s->statusColor() }}">{{ __(ucfirst($s->status)) }}</span></td><td class="mute">{{ $s->created_at->diffForHumans() }}</td></tr>@endforeach
  </table></div>@endif
</div>

<div class="grid g21">
  <div class="card pad">
    <h2>{{ __('Studies per week') }}</h2>
    <div class="bars" style="--n: {{ count($weeks) }}" role="img" aria-label="{{ __('Studies per week') }}">
      @foreach ($weeks as $w)<div class="b {{ $loop->last ? 'last' : '' }}" title="{{ $w['label'] }}: {{ $w['count'] }}"><span class="v">{{ $w['count'] }}</span><i style="height: {{ round(100 * $w['count'] / $max) }}%"></i></div>@endforeach
    </div>
    <div class="bars-x" style="--n: {{ count($weeks) }}">@foreach ($weeks as $w)<span>{{ $w['label'] }}</span>@endforeach</div>
    <table class="sr"><caption>{{ __('Studies per week') }}</caption>@foreach ($weeks as $w)<tr><td>{{ $w['label'] }}</td><td>{{ $w['count'] }}</td></tr>@endforeach</table>
    <h2 style="margin-top:26px">{{ __('Decisions issued') }}</h2>
    @forelse ($decisions as $d => $n)
      <div class="hbar"><span>{{ __('studies.decision.' . $d) }}</span><span class="track"><i style="width: {{ round(100 * $n / $dmax) }}%"></i></span><span class="n">{{ $n }}</span></div>
    @empty
      <p class="mute">{{ __('No reviews issued yet.') }}</p>
    @endforelse
  </div>
  <div class="card pad">
    <h2>{{ __('Activity') }}</h2>
    <ul class="feed">
      @forelse ($activity as $a)<li><span>{{ __(str_replace(['.', '_'], ' ', $a->action)) }}@if ($a->submission) · <a href="{{ route('v2.admin.submissions.show', $a->submission) }}" class="mono">{{ $a->submission->code }}</a>@elseif ($a->study) · <a href="{{ route('v2.admin.studies.show', $a->study) }}" class="mono">{{ $a->study->code }}</a>@endif @if ($a->detail)<span class="mute"> — {{ \Illuminate\Support\Str::limit($a->detail, 60) }}</span>@endif</span><span class="when">{{ $a->created_at->diffForHumans(null, true) }}</span></li>
      @empty<li class="mute">{{ __('No activity yet.') }}</li>@endforelse
    </ul>
  </div>
</div>
@endsection
@push('scripts')
<script>
// count the numbers up once on load
document.querySelectorAll('[data-count]').forEach(function (el) {
  var to = +el.dataset.count; if (!to || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var t0 = performance.now(), d = 700;
  (function step(t) { var k = Math.min(1, (t - t0) / d); el.textContent = Math.round(to * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(step); })(t0);
});
</script>
@endpush
