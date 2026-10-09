@extends('v2.layouts.admin', ['title' => 'My requests'])
@section('content')
<div class="kpis">
  <div class="card kpi"><div class="lbl">Assigned to me</div><b style="color:{{ $mine->count() ? 'var(--warn)' : 'inherit' }}">{{ $mine->count() }}</b><small>submittals to review</small></div>
  <div class="card kpi"><div class="lbl">Studies (form)</div><b>{{ $studies->count() }}</b><small>assigned to me</small></div>
  <div class="card kpi"><div class="lbl">Waiting in my categories</div><b>{{ $free->count() }}</b><small>not assigned yet</small></div>
  <div class="card kpi"><div class="lbl">Issued this month</div><b>{{ $issuedMonth }}</b><small>{{ now()->format('F') }}</small></div>
</div>

<div class="card" style="margin-top:16px">
  <div class="pad" style="padding-bottom:6px"><h2>My submittals</h2></div>
  @if ($mine->isEmpty())<div class="empty">Nothing assigned to you right now.</div>@else
  <div class="tbl"><table class="t">
    <tr><th>Received</th><th>Code</th><th>Project / title</th><th>Category</th><th>Status</th><th></th></tr>
    @foreach ($mine as $s)
      <tr class="click" onclick="location.href='{{ route('v2.admin.submissions.show', $s) }}'">
        <td>{{ $s->created_at->format('d M') }} <span class="mute">· {{ $s->created_at->diffForHumans(null, true) }}</span></td>
        <td class="mono">{{ $s->code }}</td>
        <td><b>{{ \Illuminate\Support\Str::limit($s->project_name, 40) }}</b><div class="mute" style="font-size:12.5px">{{ \Illuminate\Support\Str::limit($s->title ?: $s->file_name, 50) }}</div></td>
        <td>{{ $s->category?->name_en ?? '–' }}</td>
        <td>@include('v2.admin.partials.status')</td>
        <td>@if (! $s->review())<a class="btn primary" style="padding:6px 10px" href="{{ route('v2.admin.submissions.workspace', [$s, 'start' => 1]) }}" onclick="event.stopPropagation()">Start the check</a>@else<a class="btn" style="padding:6px 10px" href="{{ route('v2.admin.submissions.workspace', $s) }}" onclick="event.stopPropagation()">Open</a>@endif</td>
      </tr>
    @endforeach
  </table></div>
  @endif
</div>

<div class="grid g21" style="margin-top:16px">
  <div class="card">
    <div class="pad" style="padding-bottom:6px"><h2>Waiting in my categories</h2></div>
    @if ($free->isEmpty())<div class="empty">No unassigned requests.</div>@else
    <div class="tbl"><table class="t">
      @foreach ($free as $s)
        <tr><td class="mono">{{ $s->code }}</td><td>{{ \Illuminate\Support\Str::limit($s->project_name, 36) }}<div class="mute" style="font-size:12.5px">{{ $s->category?->name_en }}</div></td>
          <td style="text-align:end"><form method="post" action="{{ route('v2.admin.submissions.assign', $s) }}">@csrf<input type="hidden" name="user" value="{{ auth()->id() }}"><button class="btn" style="padding:6px 10px">Take</button></form></td></tr>
      @endforeach
    </table></div>
    @endif
  </div>
  <div class="card pad">
    <h2>My categories</h2>
    @forelse ($categories as $c)
      <div style="padding:8px 0;border-bottom:1px solid var(--line)"><b>{{ $c->name_en }}</b>
        <div class="mute" style="font-size:12.5px">{{ count(array_filter($c->rules ?? [], fn ($r) => $r['active'] ?? true)) }} criteria @if ($c->specPath())· <a href="{{ route('v2.admin.categories.spec', $c) }}" target="_blank">specification ↗</a>@else· no specification file @endif</div></div>
    @empty
      <p class="mute">You are not responsible for a category yet — an admin sets this under Categories.</p>
    @endforelse
  </div>
</div>

@if ($studies->isNotEmpty())
<div class="card" style="margin-top:16px">
  <div class="pad" style="padding-bottom:6px"><h2>My studies (form)</h2></div>
  <div class="tbl"><table class="t">
    @foreach ($studies as $s)<tr class="click" onclick="location.href='{{ route('v2.admin.studies.show', $s) }}'"><td class="mono">{{ $s->code }}</td><td>{{ $s->project_name }}<div class="mute" style="font-size:12.5px">{{ $s->typeName('en') }} · {{ $s->revLabel() }}</div></td><td class="num">{{ $s->finding_count }} findings</td></tr>@endforeach
  </table></div>
</div>
@endif
@endsection
