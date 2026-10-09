@extends('v2.layouts.admin', ['title' => __('Home'), 'icon' => 'home', 'subtitle' => __('What is waiting for you, and what you can take next.')])
@section('content')
@php
  $cards = [
    ['red', 'clipboard', __('My studies'), $studies->count(), route('v2.admin.studies', ['who' => 'mine'])],
    ['cyan', 'layers', __('Unassigned studies'), $openStudies->count(), route('v2.admin.studies', ['who' => 'none'])],
    ['violet', 'check', __('Issued this month'), $issuedMonth, route('v2.admin.studies', ['who' => 'mine', 'status' => 'issued'])],
  ];
  if ($mine->count() || $free->count()) {
    $cards[] = ['amber', 'inbox', __('Submittals assigned to me'), $mine->count(), route('v2.admin.submissions')];
    $cards[] = ['slate', 'inbox', __('Waiting in my categories'), $free->count(), route('v2.admin.submissions')];
  }
@endphp
<div class="stats">
  @foreach ($cards as $i => [$c, $ic, $label, $n, $href])
    <a class="stat-card {{ $c }} {{ $n ? '' : 'zero' }}" style="--d: {{ $i }}" href="{{ $href }}">
      <div><div class="lbl">{{ $label }}</div><b>{{ $n }}</b><span class="more">{{ __('View details') }} <x-v2.icon name="ext"/></span></div>
      <span class="ico"><x-v2.icon :name="$ic"/></span>
    </a>
  @endforeach
</div>

<div class="grid g2">
  <div class="card">
    <div class="card-h"><h2>{{ __('My studies') }}</h2></div>
    @if ($studies->isEmpty())<div class="empty">{{ __('Nothing assigned to you right now.') }}</div>@else
    <div class="tbl"><table class="t">
      @foreach ($studies as $s)<tr class="click" onclick="location.href='{{ route('v2.admin.studies.show', $s) }}'"><td class="mono"><a href="{{ route('v2.admin.studies.show', $s) }}">{{ $s->code }}</a></td><td><b>{{ \Illuminate\Support\Str::limit($s->project_name, 36) }}</b><div class="mute" style="font-size:12.5px">{{ $s->typeName() }} · {{ $s->revLabel() }}</div></td><td class="num">{{ __(':n findings', ['n' => (int) $s->finding_count]) }}</td></tr>@endforeach
    </table></div>@endif
  </div>
  <div class="card">
    <div class="card-h"><h2>{{ __('Unassigned studies') }}</h2></div>
    @if ($openStudies->isEmpty())<div class="empty">{{ __('No unassigned requests.') }}</div>@else
    <div class="tbl"><table class="t">
      @foreach ($openStudies as $s)<tr class="click" onclick="location.href='{{ route('v2.admin.studies.show', $s) }}'"><td class="mono"><a href="{{ route('v2.admin.studies.show', $s) }}">{{ $s->code }}</a></td><td><b>{{ \Illuminate\Support\Str::limit($s->project_name, 36) }}</b><div class="mute" style="font-size:12.5px">{{ $s->typeName() }}</div></td><td class="mute">{{ $s->created_at->diffForHumans(null, true) }}</td></tr>@endforeach
    </table></div>@endif
  </div>
</div>

@if ($mine->isNotEmpty() || $free->isNotEmpty())
<div class="card" style="margin-top:18px">
  <div class="card-h"><h2>{{ __('My submittals') }}</h2></div>
  @if ($mine->isEmpty())<div class="empty">{{ __('Nothing assigned to you right now.') }}</div>@else
  <div class="tbl"><table class="t">
    <tr><th>{{ __('Received') }}</th><th>{{ __('Code') }}</th><th>{{ __('Project / title') }}</th><th>{{ __('Status') }}</th><th></th></tr>
    @foreach ($mine as $s)
      <tr class="click" onclick="location.href='{{ route('v2.admin.submissions.show', $s) }}'">
        <td>{{ $s->created_at->translatedFormat('d M') }} <span class="mute">· {{ $s->created_at->diffForHumans(null, true) }}</span></td>
        <td class="mono">{{ $s->code }}</td>
        <td><b>{{ \Illuminate\Support\Str::limit($s->project_name, 40) }}</b><div class="mute" style="font-size:12.5px">{{ \Illuminate\Support\Str::limit($s->title ?: $s->file_name, 50) }}</div></td>
        <td>@include('v2.admin.partials.status')</td>
        <td>@if (! $s->review())<a class="btn primary sm" href="{{ route('v2.admin.submissions.workspace', [$s, 'start' => 1]) }}" onclick="event.stopPropagation()">{{ __('Start the check') }}</a>@else<a class="btn sm" href="{{ route('v2.admin.submissions.workspace', $s) }}" onclick="event.stopPropagation()">{{ __('Open the check') }}</a>@endif</td>
      </tr>
    @endforeach
  </table></div>@endif
  @if ($free->isNotEmpty())
    <div class="card-h" style="border-top:1px solid var(--line)"><h2>{{ __('Waiting in my categories') }}</h2></div>
    <div class="tbl"><table class="t">
      @foreach ($free as $s)
        <tr><td class="mono">{{ $s->code }}</td><td>{{ \Illuminate\Support\Str::limit($s->project_name, 36) }}<div class="mute" style="font-size:12.5px">{{ $s->category?->name_en }}</div></td>
          <td style="text-align:end"><form method="post" action="{{ route('v2.admin.submissions.assign', $s) }}">@csrf<input type="hidden" name="user" value="{{ auth()->id() }}"><button class="btn sm">{{ __('Take') }}</button></form></td></tr>
      @endforeach
    </table></div>
  @endif
</div>
@endif

@if ($categories->isNotEmpty())
<div class="card pad" style="margin-top:18px">
  <h2>{{ __('My categories') }}</h2>
  @foreach ($categories as $c)
    <div style="padding:10px 0;border-bottom:1px solid var(--line)"><b>{{ $c->name_en }}</b>
      <div class="mute" style="font-size:12.5px">{{ __(':n criteria', ['n' => count(array_filter($c->rules ?? [], fn ($r) => $r['active'] ?? true))]) }} @if ($c->specPath())· <a href="{{ route('v2.admin.categories.spec', $c) }}" target="_blank">{{ __('specification') }} ↗</a>@else· {{ __('no specification file') }} @endif</div></div>
  @endforeach
</div>
@endif
@endsection
