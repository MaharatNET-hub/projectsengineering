@php
  use App\Studies\StudyTypes as T;
  $a = $s->analysis ?? [];
  $loc = app()->getLocale();
  $issued = $s->isIssued();
  $findings = $issued ? $s->keptFindings() : ($a['findings'] ?? []);
  $decision = $issued ? $s->decision : ($a['suggested'] ?? null);
  $order = ['submitted', 'check', 'review', 'issued'];
  $at = $issued || $s->status === 'archived' ? 4 : ($s->status === 'review' ? 2 : 1);
  $isNew = in_array($s->id, (array) session('studies', []), true) && ! $issued;
  $count = fn ($k) => count(array_filter($findings, fn ($f) => $f['status'] === $k));
  $values = $a['values'] ?? $s->values ?? [];
@endphp
@extends('v2.layouts.site', ['title' => __('studies.result.title', ['code' => $s->code])])
@push('head')<link rel="stylesheet" href="{{ asset('lib/v2/studies.css') }}?v={{ filemtime(public_path('lib/v2/studies.css')) }}">@endpush
@section('content')
<section class="page-head"><div class="wrap"><div class="crumb no-print"><a href="{{ route('v2.studies') }}">← {{ __('studies.form.back') }}</a></div><h1>{{ $def ? T::t($def['name']) : $s->type }}</h1><div class="code" style="font-size:20px;padding:8px 14px">{{ $s->code }}</div></div></section>
<section class="block">
  <div class="wrap sr-page">
    @if ($isNew)<div class="alert ok"><b>{{ __('studies.result.received') }}.</b> {{ __('studies.result.keep') }}</div>@endif

    <div class="card">
      <div class="steps">
        @foreach (['submitted' => __('studies.status.submitted'), 'check' => __('studies.result.preliminary'), 'review' => __('studies.status.review'), 'issued' => __('studies.status.issued')] as $k => $label)
          <div class="{{ $loop->index < $at ? 'done' : ($loop->index === $at ? 'now' : '') }}">{{ $label }}</div>
        @endforeach
      </div>
      <dl class="kv">
        <dt>{{ __('studies.result.status') }}</dt><dd>{{ __("studies.status.{$s->status}") }}</dd>
        <dt>{{ __('studies.form.project') }}</dt><dd>{{ $s->project_name }}</dd>
        @if ($s->reference)<dt>{{ __('studies.form.reference') }}</dt><dd>{{ $s->reference }}</dd>@endif
        <dt>{{ __('studies.result.submitted') }}</dt><dd dir="ltr" style="text-align:start">{{ $s->created_at->format('d M Y, H:i') }}</dd>
        <dt>{{ __('studies.result.file') }}</dt><dd>{{ $s->file_name ?: '–' }}@if ($s->page_count) · {{ $s->page_count }} {{ __('v2.track.pages') }}@endif</dd>
      </dl>
    </div>

    @if ($showFindings && $a)
      <div class="card">
        <h2 style="margin-top:0">{{ $issued ? __('studies.result.final') : __('studies.result.preliminary') }}</h2>
        @unless ($issued)<p class="mute" style="margin-top:-6px">{{ __('studies.result.preliminary_note') }}</p>@endunless
        <div class="verdict {{ $decision }}">
          <div><div class="mute" style="font-size:13px">{{ $issued ? __('studies.result.decision') : __('studies.result.suggested') }}</div><b>{{ $decision ? __("studies.decision.$decision") : '–' }}</b></div>
          <div class="stat">
            @foreach (['fail', 'warn', 'missing', 'mismatch'] as $k)@if ($count($k))<span class="st {{ $k }}">{{ $count($k) }} · {{ __("studies.finding.$k") }}</span>@endif @endforeach
            <span class="st pass">{{ $a['stats']['pass'] ?? 0 }} / {{ $a['stats']['checks'] ?? 0 }} · {{ __('studies.finding.pass') }}</span>
          </div>
        </div>

        <h3 style="margin-top:26px">{{ __('studies.result.findings') }}</h3>
        @if (! $findings)
          <p style="color:var(--ok);font-weight:600">{{ __('studies.result.none') }}</p>
        @else
          <ol class="findings">
            @foreach ($findings as $f)
              <li>
                <div class="h"><span class="st {{ $f['status'] }}">{{ __("studies.finding.{$f['status']}") }}</span>{{ T::t($f['label']) }} @if (! empty($f['clause']))<small dir="ltr">{{ $f['clause'] }}</small>@endif</div>
                <p>{{ $f['comment'][$loc] ?? $f['comment']['en'] }}</p>
              </li>
            @endforeach
          </ol>
        @endif

        @if (! empty($a['crossCheck']))
          <p class="mute" style="margin:16px 0 0;font-size:14px"><b>{{ __('studies.result.cross') }}:</b>
            {{ ($a['crossCheck']['panels'] ?? 0) ? __('studies.result.cross_ok', ['matched' => $a['crossCheck']['matched'], 'total' => $a['crossCheck']['panels']]) : __('studies.result.cross_none') }}</p>
        @endif

        @if ($issued && ($s->remarks || $s->engineer))
          <h3 style="margin-top:26px">{{ __('studies.result.remarks') }}</h3>
          @if ($s->remarks)<p style="white-space:pre-line">{{ $s->remarks }}</p>@endif
          @if ($s->engineer)<p class="mute">{{ __('studies.result.engineer') }}: <b>{{ $s->engineer }}</b></p>@endif
        @endif

        <div class="no-print" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:22px">
          <a class="btn primary" href="{{ route('v2.studies.report', $s->code) }}"><x-v2.icon name="down"/>{{ __('studies.result.download') }}</a>
          <button type="button" class="btn line" onclick="window.print()"><x-v2.icon name="file"/>{{ __('studies.result.print') }}</button>
        </div>
      </div>

      <div class="card">
        <h3 style="margin-top:0">{{ __('studies.result.checks') }}</h3>
        <table class="checks">
          @foreach ($a['checks'] ?? [] as $c)
            <tr><td style="width:150px"><span class="st {{ $c['status'] }}">{{ __("studies.finding.{$c['status']}") }}</span></td><td>{{ T::t($c['label']) }}</td><td dir="ltr">{{ $c['clause'] }}</td></tr>
          @endforeach
        </table>
      </div>
    @elseif (! $showFindings)
      <div class="card"><p style="margin:0">{{ __('studies.result.waiting') }}</p></div>
    @endif

    @if ($def)
      <div class="card">
        <h3 style="margin-top:0">{{ __('studies.result.values') }}</h3>
        @foreach ($def['sections'] as $sec)
          <h4 style="margin:18px 0 8px">{{ T::t($sec['title']) }}</h4>
          @php $computed = array_map(fn ($c) => $c + ['type' => 'number', 'computed' => true], $def['computed'][$sec['key']] ?? []); @endphp
          @if (empty($sec['repeat']))
            <dl class="kv">@foreach (array_merge($sec['fields'], $computed) as $f)<dt>{{ T::t($f['label']) }}</dt><dd>{{ T::display($f, $values[$sec['key']][$f['key']] ?? null) }}</dd>@endforeach</dl>
          @else
            <div class="tbl-wrap"><table class="vals">
              <tr>@foreach (array_merge($sec['fields'], $computed) as $f)<th class="{{ ! empty($f['computed']) ? 'c' : '' }}">{{ T::t($f['label']) }}@if (! empty($f['computed'])) <span title="{{ __('studies.form.computed') }}">ƒ</span>@endif</th>@endforeach</tr>
              @foreach ($values[$sec['key']] ?? [] as $row)
                <tr>@foreach (array_merge($sec['fields'], $computed) as $f)<td class="{{ ! empty($f['computed']) ? 'c' : '' }}">{{ T::display($f, $row[$f['key']] ?? null) }}</td>@endforeach</tr>
              @endforeach
            </table></div>
          @endif
        @endforeach
      </div>
    @endif

    <p class="no-print"><a href="{{ route('v2.studies') }}">{{ __('studies.result.another') }} →</a></p>
  </div>
</section>
@endsection
