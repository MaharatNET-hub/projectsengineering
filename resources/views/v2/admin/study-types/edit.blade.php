@php use App\Studies\StudyTypes as T; @endphp
@extends('v2.layouts.admin', ['title' => T::t($def['name'], 'en')])
@section('crumb')<div class="mute" style="font-size:12.5px"><a href="{{ route('v2.admin.study-types') }}">Study types</a> › <span class="mono">{{ $def['key'] }}</span></div>@endsection
@section('actions')<a class="btn" href="{{ route('v2.studies.form', $def['key']) }}" target="_blank"><x-v2.icon name="eye"/>Open the form</a>@endsection
@section('content')
<form method="post" action="{{ route('v2.admin.study-types.update', $def['key']) }}" id="teForm">
  @csrf @method('put')
  <div class="te-bar">
    <div class="seg"><a href="#" data-tab="visual" class="on" onclick="return false">Editor</a><a href="#" data-tab="json" onclick="return false">JSON (advanced)</a></div>
    <span class="mute" style="font-size:13px">@if ($custom)<span class="chip ok">created here</span>@elseif ($edited)<span class="chip accent">edited copy in use</span>@else shipped definition @endif · used by {{ $used }} {{ \Illuminate\Support\Str::plural('study', $used) }}</span>
    <button class="btn primary" style="margin-inline-start:auto"><x-v2.icon name="check"/>Save</button>
  </div>
  <div id="te" data-calcs='@json(\App\Studies\Calculators::names())' style="display:grid;gap:16px"></div>
  <div id="jsonPane" hidden class="card pad">
    <textarea class="inp mono" name="json" id="json" spellcheck="false" style="min-height:640px;font-size:12.5px;line-height:1.5;white-space:pre;tab-size:2" dir="ltr">{{ $json }}</textarea>
  </div>
  <div style="margin-top:16px;display:flex;gap:10px"><button class="btn primary"><x-v2.icon name="check"/>Save</button></div>
</form>
@if ($edited)
  <form method="post" action="{{ route('v2.admin.study-types.reset', $def['key']) }}" onsubmit="return confirm('{{ $custom ? 'Delete this study type?' : 'Discard the edited copy and go back to the shipped definition?' }}')" style="margin-top:16px">@csrf @method('delete')<button class="btn danger">{{ $custom ? 'Delete this study type' : 'Reset to the shipped definition' }}</button></form>
@endif
@endsection
@push('scripts')<script src="{{ asset('lib/v2/type-editor.js') }}?v={{ filemtime(public_path('lib/v2/type-editor.js')) }}"></script>@endpush
