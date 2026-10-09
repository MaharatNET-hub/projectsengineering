@php
  use App\Studies\StudyTypes as T;
  $a = $s->analysis ?? [];
  $findings = $a['findings'] ?? [];
  $values = $a['values'] ?? $s->values ?? [];
  $chip = ['fail' => 'fail', 'warn' => 'warn', 'missing' => 'mute', 'mismatch' => 'accent'];
@endphp
@extends('v2.layouts.admin', ['title' => $s->project_name])
@section('crumb')<div class="mute" style="font-size:12.5px"><a href="{{ route('v2.admin.studies') }}">{{ __('Studies') }}</a> › <span class="mono">{{ $s->code }}</span> · {{ $s->revLabel() }}</div>@endsection
@section('actions')
  <a class="btn" href="{{ route('v2.admin.studies.report', $s) }}"><x-v2.icon name="down"/>{{ $s->isIssued() ? __('Report PDF') : __('Draft report PDF') }}</a>
  <a class="btn" href="{{ route('v2.studies.show', $s->code) }}" target="_blank"><x-v2.icon name="eye"/>{{ __('Client page') }}</a>
@endsection
@section('content')
<div class="grid g21">
  <div class="grid" style="align-content:start;min-width:0">
    @if ($s->child)<div class="alert ok" style="margin:0">{{ __('A newer revision was submitted:') }} <a href="{{ route('v2.admin.studies.show', $s->child) }}">{{ $s->child->code }} ({{ $s->child->revLabel() }})</a>.</div>@endif
    @if ($diff)
      <div class="card pad">
        <h2 style="margin-top:0">{{ __('Changes since :from', ['from' => $diff['from']]) }} <a class="mono" style="font-size:13px;font-weight:500" href="{{ route('v2.admin.studies.show', $s->parent) }}">{{ $s->parent->code }}</a></h2>
        @include('v2.studies._diff', ['diff' => $diff])
      </div>
    @endif
    @unless ($canEdit)<div class="alert bad" style="margin:0">{{ __('Assigned to :name — only they or an admin can edit this review.', ['name' => $s->assignee?->name]) }}</div>@endunless
    <form class="card pad form" method="post" action="{{ route('v2.admin.studies.update', $s) }}">
      @csrf @method('patch')
      <fieldset @disabled(! $canEdit) style="border:0;padding:0;margin:0;display:grid;gap:14px">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap"><h2 style="margin:0">{{ __('Findings') }}</h2>
        <span class="mute" style="font-size:13px">{{ __(':pass of :checks checks pass', ['pass' => $a['stats']['pass'] ?? 0, 'checks' => $a['stats']['checks'] ?? 0]) }} · {{ __('suggested:') }} <b>{{ __('studies.decision.' . ($a['suggested'] ?? 'noted')) }}</b></span></div>
      <p class="mute" style="margin:0;font-size:13px">{{ __('Untick a finding to drop it from the report. Edit the wording in English and Arabic (the client sees their language; the PDF is in English).') }}</p>
      @forelse ($findings as $i => $f)
        <div style="border:1px solid var(--line);border-radius:10px;padding:12px;{{ ($f['include'] ?? true) ? '' : 'opacity:.55' }}">
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
            <input type="hidden" name="findings[{{ $i }}][include]" value="0">
            <label class="check"><input type="checkbox" name="findings[{{ $i }}][include]" value="1" @checked($f['include'] ?? true)> <b>{{ T::t($f['label']) }}</b></label>
            <span class="chip {{ $chip[$f['status']] ?? 'mute' }}">{{ __('studies.finding.' . $f['status']) }}</span>
            <span class="mute mono" style="font-size:12px">{{ $f['rule'] }} · {{ $f['clause'] }}</span>
            @if (! empty($f['edited']))<span class="chip accent">{{ __('edited') }}</span>@endif
          </div>
          <div class="row2">
            <textarea class="inp" name="findings[{{ $i }}][en]" style="min-height:70px">{{ $f['comment']['en'] ?? '' }}</textarea>
            <textarea class="inp" name="findings[{{ $i }}][ar]" dir="rtl" style="min-height:70px">{{ $f['comment']['ar'] ?? '' }}</textarea>
          </div>
        </div>
      @empty
        <p class="mute">{{ __('No findings: every check that could be applied complies.') }}</p>
      @endforelse
      <details style="border:1px dashed var(--line);border-radius:10px;padding:10px 12px">
        <summary style="cursor:pointer;font-weight:600">{{ __('+ Add an engineer comment') }}</summary>
        <div style="display:grid;gap:10px;margin-top:10px">
          <select class="inp" name="add_status" style="max-width:220px"><option value="warn">{{ __('Clarify') }}</option><option value="fail">{{ __('Non-compliant') }}</option></select>
          <div class="row2"><textarea class="inp" name="add_en" placeholder="{{ __('Comment (English)') }}"></textarea><textarea class="inp" name="add_ar" dir="rtl" placeholder="الملاحظة (عربي)"></textarea></div>
        </div>
      </details>
      <h3 style="margin:8px 0 0">{{ __('Decision') }}</h3>
      <div class="row2">
        <label class="f">{{ __('Action') }}<select class="inp" name="decision"><option value="">{{ __('— choose —') }}</option>@foreach (\App\Studies\Analyzer::DECISIONS as $d)<option value="{{ $d }}" @selected(old('decision', $s->decision) === $d)>{{ __("studies.decision.$d") }}{{ ($a['suggested'] ?? null) === $d ? ' (' . __('suggested') . ')' : '' }}</option>@endforeach</select></label>
        <label class="f">{{ __('Engineer') }}<input class="inp" name="engineer" value="{{ old('engineer', $s->engineer ?: auth()->user()->name) }}"></label>
      </div>
      <label class="f">{{ __('Remarks to the client') }} <small>{{ __('(optional)') }}</small><textarea class="inp" name="remarks">{{ old('remarks', $s->remarks) }}</textarea></label>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn" name="action" value="save"><x-v2.icon name="check"/>{{ __('Save') }}</button>
        <button class="btn primary" name="action" value="issue"><x-v2.icon name="send"/>{{ $s->isIssued() ? __('Save (issued)') : __('Save & issue to the client') }}</button>
      </div>
      </fieldset>
    </form>

    <div class="card pad">
      <h2>{{ __('Values entered') }}</h2>
      @if ($def)
        @foreach ($def['sections'] as $sec)
          @php $cols = array_merge($sec['fields'], array_map(fn ($c) => $c + ['type' => 'number', 'computed' => true], $def['computed'][$sec['key']] ?? [])); @endphp
          <h3 style="margin:14px 0 8px">{{ T::t($sec['title']) }}</h3>
          @if (empty($sec['repeat']))
            <dl class="kv">@foreach ($cols as $f)<dt>{{ T::t($f['label']) }}</dt><dd>{{ T::display($f, $values[$sec['key']][$f['key']] ?? null) }}</dd>@endforeach</dl>
          @else
            <div class="tbl"><table class="t">
              <tr>@foreach ($cols as $f)<th style="{{ ! empty($f['computed']) ? 'background:#eef2ff' : '' }}">{{ T::t($f['label']) }}</th>@endforeach</tr>
              @foreach ($values[$sec['key']] ?? [] as $row)<tr>@foreach ($cols as $f)<td style="white-space:nowrap;{{ ! empty($f['computed']) ? 'background:#f8faff' : '' }}">{{ T::display($f, $row[$f['key']] ?? null) }}</td>@endforeach</tr>@endforeach
            </table></div>
          @endif
        @endforeach
      @else
        <p class="mute">{{ __('The study type ":type" no longer exists.', ['type' => $s->type]) }}</p>
      @endif
    </div>
  </div>

  <div class="grid" style="align-content:start">
    <div class="card pad">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px"><h2 style="margin:0">{{ __('Study') }}</h2><span class="chip {{ $s->statusColor() }}"><span class="dot"></span>{{ __(ucfirst($s->status)) }}</span></div>
      <dl class="kv">
        <dt>{{ __('Code') }}</dt><dd class="mono">{{ $s->code }}</dd>
        <dt>{{ __('Type') }}</dt><dd>{{ $s->typeName() }}</dd>
        <dt>{{ __('Received') }}</dt><dd>{{ $s->created_at->format('d M Y, H:i') }}</dd>
        <dt>{{ __('Reference') }}</dt><dd>{{ $s->reference ?: '–' }}</dd>
        <dt>{{ __('File') }}</dt><dd>@if ($s->filePath())<a href="{{ route('v2.admin.studies.file', $s) }}">{{ $s->file_name }}</a> <span class="mute">· {{ $s->sizeLabel() }}{{ $s->page_count ? ' · ' . __(':n pages', ['n' => $s->page_count]) : '' }}</span>@else{{ $s->file_name ?: '–' }}@endif</dd>
        @if (! empty($a['crossCheck']))<dt>{{ __('Form vs file') }}</dt><dd>{{ ($a['crossCheck']['panels'] ?? 0) ? __(':matched of :panels panels in the file matched', ['matched' => $a['crossCheck']['matched'], 'panels' => $a['crossCheck']['panels']]) : __('File layout not recognised — check by hand') }}</dd>@endif
        @if ($s->issued_at)<dt>{{ __('Issued') }}</dt><dd>{{ $s->issued_at->format('d M Y, H:i') }}</dd>@endif
      </dl>
      @if ($s->notes)<h3 style="margin-top:14px">{{ __('Notes from the client') }}</h3><p style="white-space:pre-line;margin:0">{{ $s->notes }}</p>@endif
      <form method="post" action="{{ route('v2.admin.studies.reanalyse', $s) }}" style="margin-top:14px">@csrf<button class="btn"><x-v2.icon name="chart"/>{{ __('Run the rules again') }}</button></form>
    </div>
    <div class="card pad">
      <h2>{{ __('Engineer') }}</h2>
      <p style="margin:0 0 10px">{!! $s->assignee ? '<b>' . e($s->assignee->name) . '</b>' : '<span class="mute">' . e(__('Not assigned')) . '</span>' !!}</p>
      @if (auth()->user()->isAdmin())
        <form method="post" action="{{ route('v2.admin.studies.assign', $s) }}" style="display:flex;gap:8px">@csrf
          <select class="inp" name="user"><option value="">{{ __('— nobody —') }}</option>@foreach ($engineers as $e)<option value="{{ $e->id }}" @selected($s->assigned_to === $e->id)>{{ $e->name }} ({{ $e->role }})</option>@endforeach</select>
          <button class="btn">{{ __('Assign') }}</button>
        </form>
      @elseif (! $s->assigned_to)
        <form method="post" action="{{ route('v2.admin.studies.assign', $s) }}">@csrf<input type="hidden" name="user" value="{{ auth()->id() }}"><button class="btn primary">{{ __('Take this study') }}</button></form>
      @elseif ($s->assigned_to === auth()->id())
        <form method="post" action="{{ route('v2.admin.studies.assign', $s) }}">@csrf<button class="btn">{{ __('Hand it back') }}</button></form>
      @endif
    </div>
    @if (count($history) > 1)
      <div class="card pad">
        <h2>{{ __('Revisions') }}</h2>
        <ol style="margin:0;padding-inline-start:20px">@foreach ($history as $h)<li style="margin-bottom:4px">@if ($h->is($s))<b>{{ $h->revLabel() }}</b> ({{ __('this one') }})@else<a href="{{ route('v2.admin.studies.show', $h) }}">{{ $h->revLabel() }}</a>@endif <span class="mono mute" style="font-size:12px">{{ $h->code }}</span> · {{ $h->decision ? __('studies.decision.' . $h->decision) : __(ucfirst($h->status)) }}</li>@endforeach</ol>
      </div>
    @endif
    <div class="card pad">
      <h2>{{ __('Client') }}</h2>
      <dl class="kv">
        <dt>{{ __('Name') }}</dt><dd>{{ $s->client_name }}</dd>
        <dt>{{ __('Company') }}</dt><dd>{{ $s->client_company ?: '–' }}</dd>
        <dt>{{ __('Email') }}</dt><dd><a href="mailto:{{ $s->client_email }}">{{ $s->client_email }}</a></dd>
        <dt>{{ __('Phone') }}</dt><dd>{{ $s->client_phone ?: '–' }}</dd>
        <dt>{{ __('Language') }}</dt><dd>{{ $s->locale === 'ar' ? __('Arabic') : __('English') }}</dd>
        <dt>{{ __('Account') }}</dt><dd>{{ $s->client ? __('Yes') . ' · ' . $s->client->email : __('No (tracking code only)') }}</dd>
      </dl>
    </div>
    <div class="card pad">
      <h2>{{ __('Activity') }}</h2>
      <ul class="feed" style="margin:0">
        @forelse ($activity as $a)<li><span>{{ str_replace(['.', '_'], ' ', $a->action) }}@if ($a->detail)<span class="mute"> — {{ $a->detail }}</span>@endif <span class="mute" style="font-size:12px">· {{ $a->user?->name ?? __('client') }}</span></span><span class="when">{{ $a->created_at->diffForHumans(null, true) }}</span></li>
        @empty<li class="mute">{{ __('No activity yet.') }}</li>@endforelse
      </ul>
    </div>
    <div class="card pad">
      <h2>{{ __('Send to the client') }}</h2>
      @if ($s->isIssued())
        <form class="form" method="post" action="{{ route('v2.admin.studies.email', $s) }}">
          @csrf
          <label class="f">{{ __('To') }}<input class="inp" type="email" name="to" value="{{ $s->client_email }}"></label>
          <label class="f">{{ __('Message') }} <small>{{ __('(optional)') }}</small><textarea class="inp" name="note"></textarea></label>
          <div><button class="btn primary"><x-v2.icon name="send"/>{{ __('Email the review') }}</button> @if ($s->emailed_at)<span class="mute" style="font-size:12.5px">{{ __('Last sent :when.', ['when' => $s->emailed_at->diffForHumans()]) }}</span>@endif</div>
        </form>
      @else
        <p class="mute" style="margin:0">{!! __('Available once the review is issued (choose the action and press :button).', ['button' => '<b>' . e(__('Save & issue')) . '</b>']) !!}</p>
      @endif
    </div>
    <div class="card pad">
      <h2>{{ __('Status') }}</h2>
      <form method="post" action="{{ route('v2.admin.studies.update', $s) }}" style="display:flex;gap:8px">@csrf @method('patch')
        @foreach ($findings as $i => $f)<input type="hidden" name="findings[{{ $i }}][include]" value="{{ ($f['include'] ?? true) ? 1 : 0 }}">@endforeach
        <input type="hidden" name="decision" value="{{ $s->decision }}"><input type="hidden" name="engineer" value="{{ $s->engineer }}"><input type="hidden" name="remarks" value="{{ $s->remarks }}">
        <select class="inp" name="status">@foreach (\App\Models\V2\Study::STATUSES as $st)<option value="{{ $st }}" @selected($s->status === $st)>{{ __(ucfirst($st)) }}</option>@endforeach</select>
        <button class="btn">{{ __('Save') }}</button>
      </form>
      @if (auth()->user()->isAdmin())<form method="post" action="{{ route('v2.admin.studies.destroy', $s) }}" onsubmit="return confirm(@js(__('Delete this study and its file?')))" style="margin-top:12px">@csrf @method('delete')<button class="btn danger"><x-v2.icon name="trash"/>{{ __('Delete') }}</button></form>@endif
    </div>
  </div>
</div>
@endsection
