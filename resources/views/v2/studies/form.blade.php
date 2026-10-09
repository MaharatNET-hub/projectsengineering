@php use App\Studies\StudyTypes as T; @endphp
@extends('v2.layouts.site', ['title' => T::t($def['name'])])
@push('head')<link rel="stylesheet" href="{{ asset('lib/v2/studies.css') }}?v={{ filemtime(public_path('lib/v2/studies.css')) }}">@endpush
@section('content')
<section class="page-head"><div class="wrap"><div class="crumb"><a href="{{ route('v2.studies') }}">← {{ __('studies.form.back') }}</a></div><h1>{{ T::t($def['name']) }}</h1><p>{{ T::t($def['summary'] ?? '') }}</p></div></section>
<section class="block">
  <div class="wrap">
    <form class="form sf" id="sf" novalidate>
      <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>

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
            <div style="margin-top:14px"><button type="button" class="btn line" data-add><x-v2.icon name="plus"/>{{ T::t($sec['repeat']['add'] ?? __('studies.form.row')) }}</button></div>
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
    'file' => __('studies.err.file'), 'required' => __('studies.err.required'), 'number' => __('studies.err.number'), 'fix' => __('studies.form.fix'), 'row' => __('studies.form.row')];
  $cfg = ['texts' => $texts, 'max' => $maxMb * 1048576, 'chunk' => $chunk, 'accept' => $accept, 'extract' => ! empty($def['extract']), 'fileRequired' => (bool) ($def['file']['required'] ?? true),
    'urls' => ['upload' => route('v2.studies.upload'), 'chunk' => url('v2/studies/upload/__T__/chunk'), 'extract' => url('v2/studies/upload/__T__/extract'), 'create' => route('v2.studies.create', $def['key'])]];
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
    if (C.fileRequired && !file) errs.file = T.file;
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
        // replace the rows nobody has typed in yet
        const initial = i => i.tagName === 'SELECT' ? ([...i.options].find(o => o.defaultSelected)?.value ?? '') : i.defaultValue;
        sec.querySelectorAll('.row').forEach(r => { if ([...r.querySelectorAll('[data-v]')].every(i => i.value === initial(i))) r.remove(); });
        rows.forEach(r => addRow(sec, r));
        $('prefillMsg').textContent = T.filled.replace(':n', rows.length);
        sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } else $('prefillMsg').textContent = T.none;
    } catch (err) { showMsg(err.message); }
    $('prog').hidden = true; btn.disabled = false;
  });

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
      const res = await post(C.urls.create, { ...fields, values, upload_token: token });
      prog(T.analysing, 100);
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
