@extends('v2.mail.layout')
@section('body')
@php $l = $s->locale; @endphp
<p>{{ __('studies.mail.hello', ['name' => $s->client_name], $l) }}</p>
<p>{{ __('studies.mail.issued', ['type' => $s->typeName($l), 'project' => $s->project_name, 'decision' => __('studies.decision.' . $s->decision, [], $l)], $l) }}</p>
@if ($note)<p style="background:#f8fafc;border-radius:8px;padding:10px 12px;white-space:pre-line">{{ $note }}</p>@endif
<p><a href="{{ route('v2.studies.show', $s->code) }}" style="display:inline-block;background:#1f4fbf;color:#fff;text-decoration:none;padding:11px 18px;border-radius:9px;font-weight:600">{{ __('studies.mail.open', [], $l) }}</a></p>
@endsection
