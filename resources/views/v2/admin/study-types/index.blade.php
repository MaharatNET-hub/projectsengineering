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
        <td>@if (T::isCustom($k))<span class="chip ok">new</span>@elseif (T::isEdited($k))<span class="chip accent">edited</span>@endif</td>
      </tr>
    @endforeach
  </table></div>
</div>
<form class="card pad form" method="post" action="{{ route('v2.admin.study-types.store') }}" style="margin-top:16px">
  @csrf
  <h2 style="margin:0">New study type</h2>
  <div class="row2">
    <label class="f">Name (English)<input class="inp" name="name_en" value="{{ old('name_en') }}" required placeholder="Lighting design study"></label>
    <label class="f">Name (Arabic)<input class="inp" name="name_ar" value="{{ old('name_ar') }}" required dir="rtl" placeholder="دراسة الإنارة"></label>
  </div>
  <div class="row2">
    <label class="f">Key <small>(in the address: lower-case, digits, _)</small><input class="inp mono" name="key" value="{{ old('key') }}" required pattern="[a-z][a-z0-9_]*" placeholder="lighting_study"></label>
    <label class="f">Discipline<select class="inp" name="discipline">@foreach (['Electrical', 'Mechanical', 'Plumbing', 'Fire', 'Other'] as $d)<option>{{ $d }}</option>@endforeach</select></label>
  </div>
  <label class="f">Start from<select class="inp" name="from"><option value="">A blank form (one data section + one table)</option>@foreach ($types as $k => $d)<option value="{{ $k }}">A copy of: {{ T::t($d['name'], 'en') }}</option>@endforeach</select></label>
  <div><button class="btn primary"><x-v2.icon name="plus"/>Create</button></div>
</form>
@endsection
