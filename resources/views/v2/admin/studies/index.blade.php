@php use App\Studies\StudyTypes as T; @endphp
@extends('v2.layouts.admin', ['title' => 'Studies (form)'])
@section('actions')<a class="btn" href="{{ route('v2.admin.studies.stats') }}"><x-v2.icon name="chart"/>Statistics</a>@if (auth()->user()->isAdmin())<a class="btn" href="{{ route('v2.admin.study-types') }}"><x-v2.icon name="edit"/>Study types</a>@endif<a class="btn" href="{{ route('v2.studies') }}" target="_blank"><x-v2.icon name="globe"/>Public form</a>@endsection
@section('content')
<form class="filters" method="get">
  <div class="seg">
    <a href="{{ route('v2.admin.studies', array_filter(['q' => request('q'), 'type' => request('type')])) }}" class="{{ request('status') ? '' : 'on' }}">All<span class="n">{{ $counts->sum() }}</span></a>
    @foreach (\App\Models\V2\Study::STATUSES as $st)@if ($counts[$st] ?? 0)<a href="{{ route('v2.admin.studies', array_filter(['status' => $st, 'q' => request('q'), 'type' => request('type')])) }}" class="{{ request('status') === $st ? 'on' : '' }}">{{ ucfirst($st) }}<span class="n">{{ $counts[$st] }}</span></a>@endif @endforeach
  </div>
  <div class="seg">
    @foreach (['' => 'Everyone', 'mine' => 'Mine', 'none' => 'Unassigned'] as $k => $l)<a href="{{ route('v2.admin.studies', array_filter(['who' => $k, 'status' => request('status'), 'type' => request('type'), 'q' => request('q')])) }}" class="{{ (string) request('who') === $k ? 'on' : '' }}">{{ $l }}</a>@endforeach
  </div>
  <input type="hidden" name="status" value="{{ request('status') }}"><input type="hidden" name="who" value="{{ request('who') }}">
  <select class="inp" name="type" style="max-width:240px" onchange="this.form.submit()"><option value="">All study types</option>@foreach ($types as $k => $d)<option value="{{ $k }}" @selected(request('type') === $k)>{{ T::t($d['name'], 'en') }}</option>@endforeach</select>
  <input class="inp" type="search" name="q" value="{{ request('q') }}" placeholder="Search code, project, client, email…" style="max-width:280px">
  <button class="btn"><x-v2.icon name="search"/>Search</button>
</form>
<div class="card">
  @if ($items->isEmpty())<div class="empty">No studies yet. They arrive through the public form at <a href="{{ route('v2.studies') }}" target="_blank">/v2/studies</a>.</div>@else
  <div class="tbl"><table class="t">
    <tr><th>Received</th><th>Code</th><th>Study / project</th><th>Client</th><th class="num">Findings</th><th>Engineer</th><th>Action</th><th>Status</th></tr>
    @foreach ($items as $s)
      <tr class="click" onclick="location.href='{{ route('v2.admin.studies.show', $s) }}'">
        <td>{{ $s->created_at->format('d M Y') }}<div class="mute" style="font-size:12px">{{ $s->created_at->format('H:i') }}</div></td>
        <td class="mono"><a href="{{ route('v2.admin.studies.show', $s) }}">{{ $s->code }}</a>@if ($s->revision)<div class="mute" style="font-size:12px">{{ $s->revLabel() }}</div>@endif</td>
        <td><b>{{ \Illuminate\Support\Str::limit($s->project_name, 40) }}</b><div class="mute" style="font-size:12.5px">{{ $s->typeName('en') }}</div></td>
        <td>{{ $s->client_name }}<div class="mute" style="font-size:12.5px">{{ $s->client_company }}</div></td>
        <td class="num">{{ $s->finding_count ?? '–' }}</td>
        <td>{!! $s->assignee ? e($s->assignee->name) : '<span class="mute">–</span>' !!}</td>
        <td>{{ $s->decision ? __('studies.decision.' . $s->decision, [], 'en') : '–' }}@if (! $s->decision && ($s->analysis['suggested'] ?? null))<div class="mute" style="font-size:12px">suggested: {{ __('studies.decision.' . $s->analysis['suggested'], [], 'en') }}</div>@endif</td>
        <td><span class="chip {{ $s->statusColor() }}"><span class="dot"></span>{{ ucfirst($s->status) }}</span></td>
      </tr>
    @endforeach
  </table></div>
  {{ $items->links() }}
  @endif
</div>
@endsection
