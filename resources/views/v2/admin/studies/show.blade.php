@php
  use App\Studies\StudyTypes as T;
  $a = $s->analysis ?? [];
  $findings = $a['findings'] ?? [];
  $values = $a['values'] ?? $s->values ?? [];
  $chip = ['fail' => 'fail', 'warn' => 'warn', 'missing' => 'mute', 'mismatch' => 'accent'];
@endphp
@extends('v2.layouts.admin', ['title' => $s->project_name])
@section('crumb')<div class="mute" style="font-size:12.5px"><a href="{{ route('v2.admin.studies') }}">Studies</a> › <span class="mono">{{ $s->code }}</span></div>@endsection
@section('actions')
  <a class="btn" href="{{ route('v2.admin.studies.report', $s) }}"><x-v2.icon name="down"/>{{ $s->isIssued() ? 'Report PDF' : 'Draft report PDF' }}</a>
  <a class="btn" href="{{ route('v2.studies.show', $s->code) }}" target="_blank"><x-v2.icon name="eye"/>Client page</a>
@endsection
@section('content')
<div class="grid g21">
  <div class="grid" style="align-content:start;min-width:0">
    <form class="card pad form" method="post" action="{{ route('v2.admin.studies.update', $s) }}">
      @csrf @method('patch')
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap"><h2 style="margin:0">Findings</h2>
        <span class="mute" style="font-size:13px">{{ $a['stats']['pass'] ?? 0 }} of {{ $a['stats']['checks'] ?? 0 }} checks pass · suggested: <b>{{ __('studies.decision.' . ($a['suggested'] ?? 'noted'), [], 'en') }}</b></span></div>
      <p class="mute" style="margin:0;font-size:13px">Untick a finding to drop it from the report. Edit the wording in English and Arabic (the client sees their language; the PDF is in English).</p>
      @forelse ($findings as $i => $f)
        <div style="border:1px solid var(--line);border-radius:10px;padding:12px;{{ ($f['include'] ?? true) ? '' : 'opacity:.55' }}">
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
            <input type="hidden" name="findings[{{ $i }}][include]" value="0">
            <label class="check"><input type="checkbox" name="findings[{{ $i }}][include]" value="1" @checked($f['include'] ?? true)> <b>{{ T::t($f['label'], 'en') }}</b></label>
            <span class="chip {{ $chip[$f['status']] ?? 'mute' }}">{{ __('studies.finding.' . $f['status'], [], 'en') }}</span>
            <span class="mute mono" style="font-size:12px">{{ $f['rule'] }} · {{ $f['clause'] }}</span>
            @if (! empty($f['edited']))<span class="chip accent">edited</span>@endif
          </div>
          <div class="row2">
            <textarea class="inp" name="findings[{{ $i }}][en]" style="min-height:70px">{{ $f['comment']['en'] ?? '' }}</textarea>
            <textarea class="inp" name="findings[{{ $i }}][ar]" dir="rtl" style="min-height:70px">{{ $f['comment']['ar'] ?? '' }}</textarea>
          </div>
        </div>
      @empty
        <p class="mute">No findings: every check that could be applied complies.</p>
      @endforelse
      <details style="border:1px dashed var(--line);border-radius:10px;padding:10px 12px">
        <summary style="cursor:pointer;font-weight:600">+ Add an engineer comment</summary>
        <div style="display:grid;gap:10px;margin-top:10px">
          <select class="inp" name="add_status" style="max-width:220px"><option value="warn">Clarify</option><option value="fail">Non-compliant</option></select>
          <div class="row2"><textarea class="inp" name="add_en" placeholder="Comment (English)"></textarea><textarea class="inp" name="add_ar" dir="rtl" placeholder="الملاحظة (عربي)"></textarea></div>
        </div>
      </details>
      <h3 style="margin:8px 0 0">Decision</h3>
      <div class="row2">
        <label class="f">Action<select class="inp" name="decision"><option value="">— choose —</option>@foreach (\App\Studies\Analyzer::DECISIONS as $d)<option value="{{ $d }}" @selected(old('decision', $s->decision) === $d)>{{ __("studies.decision.$d", [], 'en') }}{{ ($a['suggested'] ?? null) === $d ? ' (suggested)' : '' }}</option>@endforeach</select></label>
        <label class="f">Engineer<input class="inp" name="engineer" value="{{ old('engineer', $s->engineer ?: auth()->user()->name) }}"></label>
      </div>
      <label class="f">Remarks to the client <small>(optional)</small><textarea class="inp" name="remarks">{{ old('remarks', $s->remarks) }}</textarea></label>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn" name="action" value="save"><x-v2.icon name="check"/>Save</button>
        <button class="btn primary" name="action" value="issue"><x-v2.icon name="send"/>{{ $s->isIssued() ? 'Save (issued)' : 'Save & issue to the client' }}</button>
      </div>
    </form>

    <div class="card pad">
      <h2>Values entered</h2>
      @if ($def)
        @foreach ($def['sections'] as $sec)
          @php $cols = array_merge($sec['fields'], array_map(fn ($c) => $c + ['type' => 'number', 'computed' => true], $def['computed'][$sec['key']] ?? [])); @endphp
          <h3 style="margin:14px 0 8px">{{ T::t($sec['title'], 'en') }}</h3>
          @if (empty($sec['repeat']))
            <dl class="kv">@foreach ($cols as $f)<dt>{{ T::t($f['label'], 'en') }}</dt><dd>{{ T::display($f, $values[$sec['key']][$f['key']] ?? null, 'en') }}</dd>@endforeach</dl>
          @else
            <div class="tbl"><table class="t">
              <tr>@foreach ($cols as $f)<th style="{{ ! empty($f['computed']) ? 'background:#eef2ff' : '' }}">{{ T::t($f['label'], 'en') }}</th>@endforeach</tr>
              @foreach ($values[$sec['key']] ?? [] as $row)<tr>@foreach ($cols as $f)<td style="white-space:nowrap;{{ ! empty($f['computed']) ? 'background:#f8faff' : '' }}">{{ T::display($f, $row[$f['key']] ?? null, 'en') }}</td>@endforeach</tr>@endforeach
            </table></div>
          @endif
        @endforeach
      @else
        <p class="mute">The study type "{{ $s->type }}" no longer exists.</p>
      @endif
    </div>
  </div>

  <div class="grid" style="align-content:start">
    <div class="card pad">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px"><h2 style="margin:0">Study</h2><span class="chip {{ $s->statusColor() }}"><span class="dot"></span>{{ ucfirst($s->status) }}</span></div>
      <dl class="kv">
        <dt>Code</dt><dd class="mono">{{ $s->code }}</dd>
        <dt>Type</dt><dd>{{ $s->typeName('en') }}</dd>
        <dt>Received</dt><dd>{{ $s->created_at->format('d M Y, H:i') }}</dd>
        <dt>Reference</dt><dd>{{ $s->reference ?: '–' }}</dd>
        <dt>File</dt><dd>@if ($s->filePath())<a href="{{ route('v2.admin.studies.file', $s) }}">{{ $s->file_name }}</a> <span class="mute">· {{ $s->sizeLabel() }}{{ $s->page_count ? ' · ' . $s->page_count . ' pages' : '' }}</span>@else{{ $s->file_name ?: '–' }}@endif</dd>
        @if (! empty($a['crossCheck']))<dt>Form vs file</dt><dd>{{ ($a['crossCheck']['panels'] ?? 0) ? $a['crossCheck']['matched'] . ' of ' . $a['crossCheck']['panels'] . ' panels in the file matched' : 'File layout not recognised — check by hand' }}</dd>@endif
        @if ($s->issued_at)<dt>Issued</dt><dd>{{ $s->issued_at->format('d M Y, H:i') }}</dd>@endif
      </dl>
      @if ($s->notes)<h3 style="margin-top:14px">Notes from the client</h3><p style="white-space:pre-line;margin:0">{{ $s->notes }}</p>@endif
      <form method="post" action="{{ route('v2.admin.studies.reanalyse', $s) }}" style="margin-top:14px">@csrf<button class="btn"><x-v2.icon name="chart"/>Run the rules again</button></form>
    </div>
    <div class="card pad">
      <h2>Client</h2>
      <dl class="kv">
        <dt>Name</dt><dd>{{ $s->client_name }}</dd>
        <dt>Company</dt><dd>{{ $s->client_company ?: '–' }}</dd>
        <dt>Email</dt><dd><a href="mailto:{{ $s->client_email }}">{{ $s->client_email }}</a></dd>
        <dt>Phone</dt><dd>{{ $s->client_phone ?: '–' }}</dd>
        <dt>Language</dt><dd>{{ $s->locale === 'ar' ? 'Arabic' : 'English' }}</dd>
      </dl>
    </div>
    <div class="card pad">
      <h2>Send to the client</h2>
      @if ($s->isIssued())
        <form class="form" method="post" action="{{ route('v2.admin.studies.email', $s) }}">
          @csrf
          <label class="f">To<input class="inp" type="email" name="to" value="{{ $s->client_email }}"></label>
          <label class="f">Message <small>(optional)</small><textarea class="inp" name="note"></textarea></label>
          <div><button class="btn primary"><x-v2.icon name="send"/>Email the review</button> @if ($s->emailed_at)<span class="mute" style="font-size:12.5px">Last sent {{ $s->emailed_at->diffForHumans() }}.</span>@endif</div>
        </form>
      @else
        <p class="mute" style="margin:0">Available once the review is issued (choose the action and press <b>Save &amp; issue</b>).</p>
      @endif
    </div>
    <div class="card pad">
      <h2>Status</h2>
      <form method="post" action="{{ route('v2.admin.studies.update', $s) }}" style="display:flex;gap:8px">@csrf @method('patch')
        @foreach ($findings as $i => $f)<input type="hidden" name="findings[{{ $i }}][include]" value="{{ ($f['include'] ?? true) ? 1 : 0 }}">@endforeach
        <input type="hidden" name="decision" value="{{ $s->decision }}"><input type="hidden" name="engineer" value="{{ $s->engineer }}"><input type="hidden" name="remarks" value="{{ $s->remarks }}">
        <select class="inp" name="status">@foreach (\App\Models\V2\Study::STATUSES as $st)<option value="{{ $st }}" @selected($s->status === $st)>{{ ucfirst($st) }}</option>@endforeach</select>
        <button class="btn">Save</button>
      </form>
      <form method="post" action="{{ route('v2.admin.studies.destroy', $s) }}" onsubmit="return confirm('Delete this study and its file?')" style="margin-top:12px">@csrf @method('delete')<button class="btn danger"><x-v2.icon name="trash"/>Delete</button></form>
    </div>
  </div>
</div>
@endsection
