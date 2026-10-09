@extends('v2.mail.layout')
@section('body')
@php $l = $s->locale; @endphp
<p>{{ __('studies.mail.hello', ['name' => $s->client_name], $l) }}</p>
<p>{{ __('studies.mail.received', ['type' => $s->typeName($l), 'project' => $s->project_name], $l) }}</p>
<p>{{ __('studies.result.code', [], $l) }}:</p>
<p style="font:700 24px/1.2 Consolas,monospace;letter-spacing:2px;color:#12306b;background:#eef2f8;border-radius:10px;padding:12px 16px;display:inline-block" dir="ltr">{{ $s->code }}</p>
<p><a href="{{ route('v2.studies.show', $s->code) }}" style="display:inline-block;background:#1f4fbf;color:#fff;text-decoration:none;padding:11px 18px;border-radius:9px;font-weight:600">{{ __('studies.mail.open', [], $l) }}</a></p>
@endsection
