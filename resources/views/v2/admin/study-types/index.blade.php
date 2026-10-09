@php use App\Studies\StudyTypes as T; @endphp
@extends('v2.layouts.admin', ['title' => 'Study types'])
@section('content')
<p class="mute" style="margin-top:0">Each study type is a form definition: its sections and fields (what the client must enter), the rules that check them (limits and wording), and optional calculations. Edit a type to change limits, options or comments — no code change needed.</p>
<div class="card">
  <div class="tbl"><table class="t">
    <tr><th>Study type</th><th>Discipline</th><th class="num">Fields</th><th class="num">Rules</th><th>Calculation</th><th>Reads the file</th><th class="num">Studies</th><th></th></tr>
    @foreach ($types as $k => $d)
      @php $n = 0; foreach ($d['sections'] as $sec) { $n += count($sec['fields']); } @endphp
      <tr class="click" onclick="location.href='{{ route('v2.admin.study-types.edit', $k) }}'">
        <td><b>{{ T::t($d['name'], 'en') }}</b><div class="mute mono" style="font-size:12px">{{ $k }}</div></td>
        <td>{{ $d['discipline'] ?? '–' }}</td><td class="num">{{ $n }}</td><td class="num">{{ count($d['rules'] ?? []) }}</td>
        <td>{{ $d['calc'] ?? '–' }}</td><td>{{ ! empty($d['extract']) ? 'Yes (' . $d['extract'] . ')' : '–' }}</td><td class="num">{{ $counts[$k] ?? 0 }}</td>
        <td>@if (T::isEdited($k))<span class="chip accent">edited</span>@endif</td>
      </tr>
    @endforeach
  </table></div>
</div>
@endsection
