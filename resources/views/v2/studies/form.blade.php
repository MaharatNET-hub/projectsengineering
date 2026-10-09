@php use App\Studies\StudyTypes as T; @endphp
@extends('v2.layouts.site', ['title' => T::t($def['name'])])
@push('head')<link rel="stylesheet" href="{{ asset('lib/v2/studies.css') }}?v={{ filemtime(public_path('lib/v2/studies.css')) }}">@endpush
@section('content')
<section class="page-head"><div class="wrap"><div class="crumb"><a href="{{ route('v2.studies') }}">← {{ __('studies.form.back') }}</a></div><h1>{{ T::t($def['name']) }}</h1><p>{{ T::t($def['summary'] ?? '') }}</p></div></section>
<section class="block">
  <div class="wrap">
    <form class="form sf" id="sf" novalidate>
      <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
      @if ($parent)
        <input type="hidden" name="parent_code" value="{{ $parent->code }}">
        <div class="banner">{{ __('studies.rev.banner', ['rev' => 'Rev ' . ($parent->revision + 1), 'code' => $parent->code]) }}</div>
      @elseif ($client)
        <div class="banner soft">{{ $client->name }} · <a href="{{ route('v2.account') }}">{{ __('studies.account.title') }}</a></div>
      @else
        <div class="banner soft no-print" id="acctHint"><a href="{{ route('v2.account.login') }}">{{ __('studies.account.login') }}</a> / <a href="{{ route('v2.account.register') }}">{{ __('studies.account.register') }}</a> — {{ __('studies.account.why') }}</div>
      @endif
      <div class="banner soft" id="draftNote" hidden><span>{{ __('studies.draft.restored') }}</span> <button type="button" class="btn line" id="draftClear" style="padding:5px 10px;font-size:13px;margin-inline-start:8px">{{ __('studies.draft.clear') }}</button></div>

      <div class="card">
        <div class="legend">{{ __('studies.form.you') }}</div>
        <div class="grid-f" style="margin-top:14px">
          @foreach ([['client_name', 'name', true, 120, 'text'], ['client_company', 'company', false, 160, 'text'], ['client_email', 'email', true, 160, 'email'], ['client_phone', 'phone', false, 40, 'tel'], ['project_name', 'project', true, 200, 'text'], ['reference', 'reference', false, 120, 'text']] as [$n, $l, $req, $max, $type])
            <label class="f" data-c="{{ $n }}"><span>{{ __("studies.form.$l") }}@if ($req) *@endif</span><input class="inp" name="{{ $n }}" type="{{ $type }}" maxlength="{{ $max }}" @if (in_array($type, ['email', 'tel'])) dir="ltr" @endif @required($req)><span class="err" data-err></span></label>
          @endforeach
        </div>
      </div>

      @foreach ($def['sections'] as $sec)
        <div class="card" data-sec="{{ $sec['key'] }}" @if (! empty($sec['repeat'])) data-repeat="1" data-min="{{ $sec['repeat']['min'] ?? 1 }}" data-max="{{ $sec['repeat']['max'] ?? 100 }}" data-title="{{ $sec['repeat']['title'] ?? '' }}" @endif>
          <div class="legend">{{ T::t($sec['title']) }}</div>
          @if (empty($sec['repeat']))
            <div class="grid-f" style="margin-top:14px">@foreach ($sec['fields'] as $f)@include('v2.studies._field', ['f' => $f])@endforeach</div>
          @else
            <div class="rows" style="margin-top:14px"></div>
            <template><div class="row"><div class="row-head"><span><span data-n></span> <span class="mute" data-name></span></span><button type="button" class="btn line" data-del>{{ __('studies.form.remove_row') }}</button></div><div class="grid-f">@foreach ($sec['fields'] as $f)@include('v2.studies._field', ['f' => $f])@endforeach</div></div></template>
            <div class="err sec-err" data-sec-err></div>
            <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
              <button type="button" class="btn line" data-add><x-v2.icon name="plus"/>{{ T::t($sec['repeat']['add'] ?? __('studies.form.row')) }}</button>
              <a class="btn line" href="{{ route('v2.studies.template', [$def['key'], $sec['key']]) }}"><x-v2.icon name="down"/>{{ __('studies.excel.template') }}</a>
              <label class="btn line" style="cursor:pointer"><x-v2.icon name="upload"/>{{ __('studies.excel.import') }}<input type="file" data-import accept=".csv,.xlsx" hidden></label>
              <span class="note">{{ __('studies.excel.hint') }}</span>
            </div>
            <div class="note" data-import-msg style="margin-top:8px"></div>
          @endif
        </div>
      @endforeach

      <div class="card">
        <div class="legend">{{ T::t($def['file']['label'] ?? __('studies.form.file')) }}@if ($def['file']['required'] ?? true) *@endif</div>
        @if (! empty($def['file']['hint']))<p class="note" style="margin:10px 0 0">{{ T::t($def['file']['hint']) }}</p>@endif
        <label class="drop" id="drop" style="margin-top:12px">
          <input type="file" id="file" accept="{{ implode(',', array_map(fn ($e) => '.' . $e, $accept)) }}" hidden>
          <span class="ic"><x-v2.icon name="upload"/></span>
          <b>{{ __('studies.form.drop') }}</b>
          <span class="mute" style="font-size:14px">{{ __('studies.form.file_hint', ['mb' => $maxMb]) }}</span>
        </label>
        <div class="picked" id="picked" hidden style="margin-top:12px"><span class="fi" id="pext">PDF</span><div style="min-width:0"><b id="pname" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></b><span class="mute" id="psize" style="font-size:13px"></span></div><button type="button" class="btn line" id="clear" style="margin-inline-start:auto;padding:8px 10px" aria-label="Remove"><x-v2.icon name="x"/></button></div>
        <div class="err" id="ferr" style="margin-top:8px"></div>
        @if ($parent && $parent->filePath())
          <label class="check" style="margin-top:10px;display:flex;gap:8px;align-items:center;font-weight:500"><input type="checkbox" name="keep_file" value="1" id="keepFile" checked> {{ __('studies.rev.keep_file', ['name' => $parent->file_name]) }}</label>
        @endif
        @if (! empty($def['extract']))
          <div class="prefill" id="prefill" hidden><button type="button" class="btn line" id="prefillBtn"><x-v2.icon name="file"/>{{ __('studies.form.prefill') }}</button><span class="mute" id="prefillMsg">{{ __('studies.form.prefill_hint') }}</span></div>
        @endif
        <label class="f" style="margin-top:18px"><span>{{ __('studies.form.notes') }}</span><textarea class="inp" name="notes" maxlength="3000" style="min-height:80px"></textarea></label>
      </div>

      <div class="card">
        <div id="msg" class="alert bad" hidden></div>
        <div id="prog" hidden style="margin-bottom:14px"><div style="display:flex;justify-content:space-between;font-size:14px;margin-bottom:6px"><b id="ptext"></b><span id="ppct" class="mute"></span></div><div class="progress"><i id="pbar"></i></div></div>
        <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap"><button class="btn primary" id="go"><x-v2.icon name="send"/>{{ __('studies.form.send') }}</button><span class="note">{{ __('studies.form.required_note') }}</span></div>
      </div>
    </form>
  </div>
</section>
@push('scripts')
@php
  $texts = ['uploading' => __('studies.form.uploading'), 'checking' => __('studies.form.checking'), 'analysing' => __('studies.form.analysing'), 'reading' => __('studies.form.prefill_reading'),
    'filled' => __('studies.form.prefill_done'), 'none' => __('studies.form.prefill_none'), 'type' => __('studies.err.file_type'), 'size' => __('studies.err.file_size', ['mb' => $maxMb]),
    'file' => __('studies.err.file'), 'required' => __('studies.err.required'), 'number' => __('studies.err.number'), 'fix' => __('studies.form.fix'), 'row' => __('studies.form.row'),
    'imported' => __('studies.excel.done'), 'badImport' => __('studies.excel.bad_file'), 'expected' => __('studies.live.expected'), 'flagged' => __('studies.rev.flagged'), 'yes' => __('studies.form.yes'), 'no' => __('studies.form.no')];
  // the rules the browser can check while typing (calculated values are only checked on the server)
  $computed = [];
  foreach ($def['computed'] ?? [] as $secKey => $list) {
      foreach ($list as $c) {
          $computed[] = $secKey . '.' . $c['key'];
      }
  }
  $live = [];
  foreach ($def['rules'] ?? [] as $rule) {
      $ref = is_array($rule['value'] ?? null) && isset($rule['value']['ref']) ? (str_contains($rule['value']['ref'], '.') ? $rule['value']['ref'] : $rule['section'] . '.' . $rule['value']['ref']) : null;
      if (in_array($rule['section'] . '.' . $rule['field'], $computed, true) || ($ref && in_array($ref, $computed, true))) {
          continue;
      }
      $live[] = ['section' => $rule['section'], 'field' => $rule['field'], 'op' => $rule['op'], 'value' => $rule['value'], 'when' => $rule['when'] ?? [], 'label' => T::t($rule['label'] ?? $rule['field'])];
  }
  $cfg = ['texts' => $texts, 'max' => $maxMb * 1048576, 'chunk' => $chunk, 'accept' => $accept, 'extract' => ! empty($def['extract']), 'fileRequired' => (bool) ($def['file']['required'] ?? true),
    'urls' => ['upload' => route('v2.studies.upload'), 'chunk' => url('v2/studies/upload/__T__/chunk'), 'extract' => url('v2/studies/upload/__T__/extract'), 'create' => route('v2.studies.create', $def['key']), 'import' => url('v2/studies/' . $def['key'] . '/import/__S__')],
    'prefill' => $prefill, 'flags' => $flags, 'parent' => (bool) $parent, 'rules' => $live, 'draftKey' => 'study-draft-' . $def['key']];
@endphp
<script>
(() => {
  const C = {!! json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) !!}, T = C.texts;
  const csrf = document.querySelector('meta[name=csrf-token]').content;
  const $ = id => document.getElementById(id), form = $('sf');
  let file = null, token = null, uploaded = false, extracted = null;

  // ---- repeated sections (panels, circuits, units…)
  const renumber = sec => sec.querySelectorAll('.row').forEach((r, i) => {
    r.querySelector('[data-n]').textContent = T.row + ' ' + (i + 1);
    const t = sec.dataset.title && r.querySelector(`[data-f="${sec.dataset.title}"] [data-v]`);
    r.querySelector('[data-name]').textContent = t && t.value ? '· ' + t.value : '';
  });
  const addRow = (sec, data) => {
    const rows = sec.querySelector('.rows');
    if (rows.children.length >= +sec.dataset.max) return null;
    const row = sec.querySelector('template').content.firstElementChild.cloneNode(true);
    if (data) for (const [k, v] of Object.entries(data)) {
      const el = row.querySelector(`[data-f="${k}"] [data-v]`);
      if (el && v !== null && v !== undefined) el.value = typeof v === 'boolean' ? (v ? '1' : '0') : String(v);
    }
    row.querySelector('[data-del]').addEventListener('click', () => { if (rows.children.length > +sec.dataset.min) { row.remove(); renumber(sec); } });
    row.addEventListener('input', () => renumber(sec));
    rows.appendChild(row); renumber(sec);
    return row;
  };
  document.querySelectorAll('[data-repeat]').forEach(sec => {
    for (let i = 0; i < Math.max(1, +sec.dataset.min); i++) addRow(sec);
    sec.querySelector('[data-add]').addEventListener('click', () => { const r = addRow(sec); r && r.querySelector('[data-v]').focus(); });
  });
  const initial = i => i.tagName === 'SELECT' ? ([...i.options].find(o => o.defaultSelected)?.value ?? '') : i.defaultValue;
  const untouched = r => [...r.querySelectorAll('[data-v]')].every(i => i.value === initial(i));
  // replace the rows nobody has typed in, then add the given ones
  const fillRows = (sec, rows) => {
    sec.querySelectorAll('.row').forEach(r => { if (untouched(r)) r.remove(); });
    rows.forEach(r => addRow(sec, r));
    if (!sec.querySelector('.row')) addRow(sec);
    renumber(sec);
  };

  // ---- fill everything from saved data (previous revision, account, draft)
  const setValues = data => {
    if (!data) return;
    for (const [k, v] of Object.entries(data)) { const el = form.querySelector(`[data-c] [name="${k}"], textarea[name="${k}"]`); if (el && v !== null && v !== undefined && k !== 'values') el.value = v; }
    for (const [k, v] of Object.entries(data.values || {})) {
      const sec = form.querySelector(`[data-sec="${k}"]`);
      if (!sec || !v) continue;
      if (sec.dataset.repeat) { sec.querySelectorAll('.row').forEach(r => r.remove()); (Array.isArray(v) ? v : []).forEach(r => addRow(sec, r)); if (!sec.querySelector('.row')) addRow(sec); renumber(sec); }
      else for (const [f, x] of Object.entries(v)) { const el = sec.querySelector(`[data-f="${f}"] [data-v]`); if (el && x !== null && x !== undefined) el.value = typeof x === 'boolean' ? (x ? '1' : '0') : String(x); }
    }
  };
  // fields the engineer commented on in the previous revision
  const showFlags = () => (C.flags || []).forEach(fl => {
    const sec = form.querySelector(`[data-sec="${fl.section}"]`); if (!sec) return;
    const boxes = sec.dataset.repeat ? [...sec.querySelectorAll('.row')].filter(r => {
      const t = sec.dataset.title && r.querySelector(`[data-f="${sec.dataset.title}"] [data-v]`);
      return !fl.rows.length || (t && fl.rows.map(x => String(x).toUpperCase()).includes(t.value.trim().toUpperCase()));
    }) : [sec];
    boxes.forEach(b => { const f = b.querySelector(`[data-f="${fl.field}"]`); if (!f) return; f.classList.add('flag'); const n = f.querySelector('[data-flag]'); n.hidden = false; n.textContent = '⚑ ' + fl.text; n.title = T.flagged; });
  });

  // ---- live checks: the specification limits, shown next to the field while typing (not blocking)
  const read = el => { if (!el) return null; const v = el.value.trim(); if (v === '') return null; return v; };
  const num = v => +String(v).replace(',', '.').replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
  const fieldVal = (box, sec, ref) => {
    if (ref.includes('.')) { const [s, f] = ref.split('.'); return read(form.querySelector(`[data-sec="${s}"] [data-f="${f}"] [data-v]`)); }
    return read(box.querySelector(`[data-f="${ref}"] [data-v]`));
  };
  const shown = (box, field, v) => { const el = box.querySelector(`[data-f="${field}"] select`); const o = el && [...el.options].find(o => o.value === String(v)); return o ? o.textContent : String(v); };
  const check = (op, a, b) => {
    const n = !isNaN(num(a)) && !isNaN(num(b)) && a !== '' && b !== '';
    switch (op) {
      case 'gte': return num(a) >= num(b) - 1e-9; case 'lte': return num(a) <= num(b) + 1e-9;
      case 'gt': return num(a) > num(b); case 'lt': return num(a) < num(b);
      case 'eq': return n ? Math.abs(num(a) - num(b)) < 1e-9 : String(a) === String(b);
      case 'neq': return !(n ? Math.abs(num(a) - num(b)) < 1e-9 : String(a) === String(b));
      case 'in': return [].concat(b).map(String).includes(String(a)); case 'notIn': return ![].concat(b).map(String).includes(String(a));
      case 'between': return num(a) >= num(b[0]) && num(a) <= num(b[1]);
    } return true;
  };
  const sym = { gte: '≥', lte: '≤', gt: '>', lt: '<', eq: '=', neq: '≠', in: '∈', notIn: '∉', between: '' };
  const live = () => {
    form.querySelectorAll('[data-live]').forEach(e => e.textContent = '');
    for (const r of C.rules) {
      const sec = form.querySelector(`[data-sec="${r.section}"]`); if (!sec) continue;
      const boxes = sec.dataset.repeat ? [...sec.querySelectorAll('.row')] : [sec];
      for (const box of boxes) {
        if (Object.entries(r.when || {}).some(([k, allowed]) => { const v = fieldVal(box, sec, k); return v === null || ![].concat(allowed).map(String).includes(v); })) continue;
        const a = fieldVal(box, sec, r.field);
        const isRef = r.value && typeof r.value === 'object' && !Array.isArray(r.value) && r.value.ref;
        let b = isRef ? fieldVal(box, sec, r.value.ref) : r.value;
        if (a === null || b === null || b === undefined) continue;
        if (!check(r.op, a, typeof b === 'boolean' ? (b ? '1' : '0') : b)) {
          const exp = r.op === 'between' ? `${b[0]} – ${b[1]}` : `${sym[r.op] || ''} ${[].concat(b).map(x => typeof x === 'boolean' ? (x ? T.yes : T.no) : shown(box, isRef ? r.value.ref.split('.').pop() : r.field, x)).join(' / ')}`;
          const out = box.querySelector(`[data-f="${r.field}"] [data-live]`);
          if (out && !out.textContent) out.textContent = '⚠ ' + r.label + ' — ' + T.expected.replace(':exp', exp.trim());
        }
      }
    }
  };

  // ---- draft on this device (not for a revision: that starts from the previous values)
  const contactNames = ['client_name', 'client_company', 'client_email', 'client_phone', 'project_name', 'reference', 'notes'];
  const snapshot = () => { const o = { values: collect() }; contactNames.forEach(n => { const el = form.querySelector(`[name="${n}"]`); if (el) o[n] = el.value; }); return o; };
  const store = { get: () => { try { return JSON.parse(localStorage.getItem(C.draftKey) || 'null'); } catch (e) { return null; } },
    set: v => { try { localStorage.setItem(C.draftKey, JSON.stringify(v)); } catch (e) {} }, clear: () => { try { localStorage.removeItem(C.draftKey); } catch (e) {} } };
  let saveTimer = null;
  form.addEventListener('input', () => { live(); if (!C.parent) { clearTimeout(saveTimer); saveTimer = setTimeout(() => store.set(snapshot()), 600); } });
  form.addEventListener('change', () => { live(); if (!C.parent) { clearTimeout(saveTimer); saveTimer = setTimeout(() => store.set(snapshot()), 300); } });
  $('draftClear').addEventListener('click', () => { store.clear(); location.reload(); });

  // ---- import a filled Excel template into a table
  form.querySelectorAll('[data-repeat]').forEach(sec => {
    const input = sec.querySelector('[data-import]'), msg = sec.querySelector('[data-import-msg]');
    input.addEventListener('change', async () => {
      const f = input.files[0]; if (!f) return;
      const fd = new FormData(); fd.append('file', f);
      msg.textContent = '…';
      try {
        const res = await fetch(C.urls.import.replace('__S__', sec.dataset.sec), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || T.badImport);
        if (data.rows.length) fillRows(sec, data.rows);
        msg.textContent = (data.rows.length ? T.imported.replace(':n', data.rows.length) : '') + (data.warnings.length ? ' ' + data.warnings.join(' · ') : '');
        live(); store.set(snapshot());
      } catch (err) { msg.textContent = err.message; }
      input.value = '';
    });
  });

  // ---- values → JSON (and the path of each field, to show server errors next to it)
  const collect = () => {
    const values = {};
    document.querySelectorAll('[data-sec]').forEach(sec => {
      const k = sec.dataset.sec;
      const read = (box, prefix) => { const o = {}; box.querySelectorAll('[data-f]').forEach(f => { f.dataset.path = prefix + '.' + f.dataset.f; o[f.dataset.f] = f.querySelector('[data-v]').value; }); return o; };
      values[k] = sec.dataset.repeat ? [...sec.querySelectorAll('.row')].map((r, i) => read(r, `${k}.${i}`)) : read(sec, k);
    });
    return values;
  };
  const clearErrors = () => { form.querySelectorAll('.f.bad').forEach(f => f.classList.remove('bad')); form.querySelectorAll('[data-err],[data-sec-err]').forEach(e => e.textContent = ''); $('ferr').textContent = ''; };
  const showErrors = errs => {
    let first = null;
    for (const [k, m] of Object.entries(errs)) {
      const box = form.querySelector(`[data-path="${k}"]`) || form.querySelector(`[data-c="${k}"]`);
      if (box) { box.classList.add('bad'); box.querySelector('[data-err]').textContent = m; first ??= box; }
      else if (k === 'file') { $('ferr').textContent = m; first ??= $('drop'); }
      else { const s = form.querySelector(`[data-sec="${k}"] [data-sec-err]`); if (s) { s.textContent = m; first ??= s; } }
    }
    first && first.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };
  // light checks before sending (the server checks everything again)
  const precheck = () => {
    const errs = {};
    form.querySelectorAll('[data-c] [required]').forEach(i => { if (!i.value.trim()) errs[i.name] = T.required; });
    form.querySelectorAll('[data-path]').forEach(f => {
      const v = f.querySelector('[data-v]').value.trim();
      if (!v && /\*\s*$/.test(f.querySelector('span').textContent)) errs[f.dataset.path] = T.required;
      else if (v && f.querySelector('[inputmode=decimal]') && isNaN(+v.replace(',', '.').replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)))) errs[f.dataset.path] = T.number;
    });
    if (C.fileRequired && !file && !($('keepFile') && $('keepFile').checked)) errs.file = T.file;
    return errs;
  };

  // ---- the supporting file
  const showMsg = m => { $('msg').textContent = m; $('msg').hidden = !m; };
  const prog = (label, pct) => { $('prog').hidden = false; $('ptext').textContent = label; $('ppct').textContent = Math.round(pct) + '%'; $('pbar').style.width = pct + '%'; };
  const ext = n => (n.split('.').pop() || '').toLowerCase();
  const pick = f => {
    if (!f) return;
    $('ferr').textContent = '';
    if (!C.accept.includes(ext(f.name))) return $('ferr').textContent = T.type;
    if (f.size > C.max) return $('ferr').textContent = T.size;
    file = f; token = null; uploaded = false; extracted = null;
    $('pname').textContent = f.name; $('psize').textContent = (f.size / 1048576).toFixed(1) + ' MB'; $('pext').textContent = ext(f.name).toUpperCase();
    $('picked').hidden = false; $('drop').hidden = true;
    if ($('prefill')) $('prefill').hidden = ext(f.name) !== 'pdf';
  };
  $('file').addEventListener('change', e => pick(e.target.files[0]));
  $('clear').addEventListener('click', () => { file = null; token = null; uploaded = false; extracted = null; $('file').value = ''; $('picked').hidden = true; $('drop').hidden = false; if ($('prefill')) $('prefill').hidden = true; });
  const drop = $('drop');
  drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('over'); });
  drop.addEventListener('dragleave', () => drop.classList.remove('over'));
  drop.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('over'); pick(e.dataTransfer.files[0]); });

  const post = async (url, body, json = true) => {
    const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', ...(json ? { 'Content-Type': 'application/json' } : {}) }, body: json ? JSON.stringify(body) : body });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) { const e = new Error(data.error || data.message || ('Error ' + res.status)); e.data = data; throw e; }
    return data;
  };
  const upload = async (from, to) => {
    if (uploaded) return;
    const start = await post(C.urls.upload, { file_name: file.name, file_size: file.size });
    token = start.token;
    const total = Math.max(1, Math.ceil(file.size / C.chunk));
    for (let i = 0; i < total; i++) {
      prog(T.uploading, from + (to - from) * i / total);
      await post(C.urls.chunk.replace('__T__', token) + `?index=${i}&total=${total}`, file.slice(i * C.chunk, (i + 1) * C.chunk), false);
    }
    uploaded = true;
  };
  const extract = async (from, to, label) => {
    if (extracted) return extracted;
    for (let n = 0; n < 300; n++) {
      const m = await post(C.urls.extract.replace('__T__', token), {});
      if (m.total) prog(`${label} · ${m.page || 0} / ${m.total}`, from + (to - from) * (m.page || 0) / m.total);
      if (m.done) return extracted = m.rows || [];
    }
    return extracted = [];
  };

  $('prefillBtn')?.addEventListener('click', async () => {
    if (!file) return;
    const btn = $('prefillBtn'); btn.disabled = true; showMsg('');
    try {
      await upload(0, 40);
      const rows = await extract(40, 100, T.reading);
      const sec = form.querySelector('[data-sec="panels"]');
      if (rows.length && sec) {
        fillRows(sec, rows); live();
        $('prefillMsg').textContent = T.filled.replace(':n', rows.length);
        sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } else $('prefillMsg').textContent = T.none;
    } catch (err) { showMsg(err.message); }
    $('prog').hidden = true; btn.disabled = false;
  });

  // start: previous revision / account details, else a saved draft
  if (C.prefill) setValues(C.prefill);
  if (!C.parent) { const d = store.get(); if (d && (Object.values(d).some(v => typeof v === 'string' && v.trim()) || JSON.stringify(d.values || {}).match(/"[^"]+":"[^"]+"/))) { setValues(d); $('draftNote').hidden = false; } }
  showFlags(); live();

  form.addEventListener('submit', async e => {
    e.preventDefault(); showMsg(''); clearErrors();
    const values = collect();
    const pre = precheck();
    if (Object.keys(pre).length) { showMsg(T.fix); return showErrors(pre); }
    const btn = $('go'); btn.disabled = true;
    try {
      if (file) {
        await upload(0, 60);
        if (C.extract && ext(file.name) === 'pdf') await extract(60, 90, T.checking);
      }
      prog(T.analysing, 95);
      const fields = Object.fromEntries(new FormData(form));
      const res = await post(C.urls.create, { ...fields, values, upload_token: token, keep_file: !file && $('keepFile') && $('keepFile').checked ? 1 : 0 });
      prog(T.analysing, 100);
      store.clear();
      location.href = res.url;
    } catch (err) {
      $('prog').hidden = true; btn.disabled = false;
      showMsg(err.data && err.data.errors ? T.fix : err.message);
      if (err.data && err.data.errors) showErrors(err.data.errors);
    }
  });
})();
</script>
@endpush
@endsection
