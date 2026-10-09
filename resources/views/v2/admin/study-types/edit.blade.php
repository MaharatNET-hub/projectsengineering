@php use App\Studies\StudyTypes as T; @endphp
@extends('v2.layouts.admin', ['title' => T::t($def['name'], 'en')])
@section('crumb')<div class="mute" style="font-size:12.5px"><a href="{{ route('v2.admin.study-types') }}">Study types</a> › <span class="mono">{{ $def['key'] }}</span></div>@endsection
@section('actions')<a class="btn" href="{{ route('v2.studies.form', $def['key']) }}" target="_blank"><x-v2.icon name="eye"/>Open the form</a>@endsection
@section('content')
<div class="grid g21">
  <form class="card pad form" method="post" action="{{ route('v2.admin.study-types.update', $def['key']) }}">
    @csrf @method('put')
    <h2 style="margin:0">Definition (JSON)</h2>
    <textarea class="inp mono" name="json" spellcheck="false" style="min-height:620px;font-size:12.5px;line-height:1.5;white-space:pre;tab-size:2" dir="ltr">{{ $json }}</textarea>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <button class="btn primary"><x-v2.icon name="check"/>Save</button>
      @if ($edited)<span class="chip accent">edited copy in use</span>@endif
    </div>
  </form>
  <div class="grid" style="align-content:start">
    <div class="card pad">
      <h2>Rules</h2>
      <div class="tbl"><table class="t">
        <tr><th>Id</th><th>Check</th><th>Severity</th></tr>
        @foreach ($def['rules'] ?? [] as $r)
          <tr><td class="mono">{{ $r['id'] }}</td><td>{{ T::t($r['label'] ?? $r['field'], 'en') }}<div class="mute mono" style="font-size:11.5px">{{ $r['section'] }}.{{ $r['field'] }} {{ $r['op'] }} {{ is_array($r['value']) ? json_encode($r['value']) : var_export($r['value'], true) }}@if (! empty($r['when'])) · when {{ json_encode($r['when']) }}@endif</div></td><td><span class="chip {{ ($r['severity'] ?? 'fail') === 'fail' ? 'fail' : 'warn' }}">{{ $r['severity'] ?? 'fail' }}</span></td></tr>
        @endforeach
      </table></div>
    </div>
    <div class="card pad">
      <h2>How to edit</h2>
      <ul style="margin:0;padding-inline-start:18px;font-size:13.5px;line-height:1.6">
        <li><b>sections[].fields[]</b>: <span class="mono">key, type</span> (text, textarea, number, select, bool), <span class="mono">label {en, ar}, required, unit, min, max, options</span>.</li>
        <li>A section with <b>repeat</b> is a table of rows (panels, circuits…); <span class="mono">repeat.title</span> names the field that identifies a row.</li>
        <li><b>rules[]</b>: <span class="mono">section, field, op</span> ({{ implode(', ', T::OPERATORS) }}), <span class="mono">value</span> — a number/text/list, or <span class="mono">{"ref": "field"}</span> / <span class="mono">{"ref": "project.field"}</span> to compare with another value; <span class="mono">when</span> limits it to rows, <span class="mono">severity</span> fail or warn.</li>
        <li><b>comment {en, ar}</b> may use <span class="mono">{actual} {expected} {rows} {unit} {clause}</span>.</li>
        <li>Changing a field <b>key</b> breaks studies already stored with the old key.</li>
      </ul>
      @if ($edited)
        <form method="post" action="{{ route('v2.admin.study-types.reset', $def['key']) }}" onsubmit="return confirm('Discard the edited copy and go back to the shipped definition?')" style="margin-top:14px">@csrf @method('delete')<button class="btn danger">Reset to the shipped definition</button></form>
      @endif
    </div>
  </div>
</div>
@endsection
