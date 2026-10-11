@php use App\Studies\StudyTypes as T; $spec = $category?->spec_title ?: __('studies.up.spec_default'); @endphp
@extends('v2.layouts.site', ['title' => T::t($def['name'])])
@push('head')
<style>
.up { max-width: 860px; margin: 0 auto; }
.up .proc, .up .done-card { scroll-margin-top: 90px; }
.up .card { padding: clamp(20px, 3.4vw, 34px); }
.up .note { display: flex; gap: 10px; align-items: flex-start; font-size: 14.5px; color: var(--mute); margin: 0; }
.up .note .i { color: var(--ok); margin-top: 3px; }
/* processing — the same steps as the original review tool */
.proc .label, .done-card .label { font-size: 12.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--mute); }
html[dir="rtl"] .proc .label { letter-spacing: 0; }
.proc h2 { margin: 6px 0 18px; }
.proc .bar { height: 8px; background: var(--soft2); border-radius: 99px; overflow: hidden; }
.proc .bar i { display: block; height: 100%; width: 0; background: var(--glow); border-radius: 99px; transition: width .4s ease; }
.proc .detail { font-size: 14px; color: var(--mute); margin: 10px 0 8px; min-height: 1.6em; overflow-wrap: anywhere; }
.psteps { list-style: none; margin: 0; padding: 0; }
.psteps li { display: flex; gap: 14px; align-items: flex-start; padding: 15px 0; border-bottom: 1px solid var(--line); color: var(--mute); }
.psteps li:last-child { border: 0; }
.psteps .st { width: 26px; height: 26px; border-radius: 50%; border: 2px solid #cbd5e1; flex: none; display: grid; place-items: center; margin-top: 1px; }
.psteps .st .i { width: 15px; height: 15px; }
.psteps b { display: block; font-size: 16px; } .psteps small { display: block; font-size: 14px; }
.psteps li.active { color: var(--ink); } .psteps li.active .st { border-color: var(--brand2); border-inline-end-color: transparent; animation: spin .8s linear infinite; }
.psteps li.done { color: var(--ink); } .psteps li.done small { color: var(--mute); } .psteps li.done .st { background: var(--ok); border-color: var(--ok); color: #fff; }
@keyframes spin { to { transform: rotate(360deg); } }
/* sent */
.done-card { text-align: center; padding-block: clamp(34px, 6vw, 56px) !important; }
.tick { width: 84px; height: 84px; margin: 0 auto 22px; border-radius: 50%; background: #dcfce7; display: grid; place-items: center; animation: pop .5s cubic-bezier(.2,1.4,.4,1) both; }
.tick svg { width: 44px; height: 44px; stroke: var(--ok); stroke-width: 3; fill: none; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 40; stroke-dashoffset: 40; animation: draw .5s .35s ease forwards; }
@keyframes pop { from { transform: scale(.4); opacity: 0; } } @keyframes draw { to { stroke-dashoffset: 0; } }
.done-card h2 { font-size: clamp(26px, 3.6vw, 36px); margin-bottom: 8px; }
.done-card .lead { font-size: 17px; color: var(--mute); max-width: 52ch; margin: 0 auto; }
.done-card .next { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 30px 0 26px; text-align: start; }
.done-card .next div { background: var(--soft); border-radius: 12px; padding: 14px 16px; font-size: 14.5px; color: var(--ink2); }
.done-card .next b { display: block; color: var(--brand2); font-size: 13px; margin-bottom: 2px; }
@media (max-width: 640px) { .done-card .next { grid-template-columns: 1fr; } }
@media (prefers-reduced-motion: reduce) { .psteps li.active .st, .tick, .tick svg { animation: none; stroke-dashoffset: 0; } }
</style>
@endpush
@section('content')
<section class="page-head"><div class="wrap"><div class="crumb"><a href="{{ route('v2.studies') }}">← {{ __('studies.form.back') }}</a></div><h1>{{ T::t($def['name']) }}</h1><p>{{ __('studies.up.sub') }}</p></div></section>
<section class="block">
  <div class="wrap up">
    <form class="card form" id="sf" novalidate>
      <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
      <input type="hidden" name="study_type" value="{{ $def['key'] }}">
      <div class="legend">{{ __('studies.up.step1') }}</div>
      <div class="row2">
        <label class="f">{{ __('studies.form.name') }} *<input class="inp" name="client_name" required maxlength="120" value="{{ $client?->name }}"></label>
        <label class="f">{{ __('studies.form.email') }} *<input class="inp" type="email" name="client_email" required maxlength="160" dir="ltr" value="{{ $client?->email }}"></label>
      </div>
      <div class="row2">
        <label class="f">{{ __('studies.form.project') }} *<input class="inp" name="project_name" required maxlength="200"></label>
        <label class="f">{{ __('studies.form.phone') }}<input class="inp" name="client_phone" maxlength="40" dir="ltr" value="{{ $client?->phone }}"></label>
      </div>
      <div class="legend">{{ __('studies.up.step2') }}</div>
      <div>
        <label class="drop" id="drop">
          <input type="file" id="file" accept="application/pdf,.pdf" hidden>
          <span class="ic"><x-v2.icon name="upload"/></span>
          <b>{{ __('studies.form.drop') }}</b>
          <span class="mute" style="font-size:14px">{{ __('studies.up.hint', ['mb' => $maxMb]) }}</span>
        </label>
        <div class="picked" id="picked" hidden><span class="fi">PDF</span><div style="min-width:0"><b id="pname" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></b><span class="mute" id="psize" style="font-size:13px"></span></div><button type="button" class="btn line" id="clear" style="margin-inline-start:auto;padding:8px 10px" aria-label="Remove"><x-v2.icon name="x"/></button></div>
      </div>
      <details><summary class="mute" style="cursor:pointer;font-size:14.5px">{{ __('studies.up.more') }}</summary>
        <div class="row2" style="margin-top:12px">
          <label class="f">{{ __('studies.form.company') }}<input class="inp" name="client_company" maxlength="160" value="{{ $client?->company }}"></label>
          <label class="f">{{ __('studies.form.reference') }}<input class="inp" name="submittal_no" maxlength="80" dir="ltr"></label>
        </div>
        <label class="f" style="margin-top:12px">{{ __('studies.form.notes') }}<textarea class="inp" name="notes" maxlength="3000" style="min-height:80px"></textarea></label>
      </details>
      <div id="msg" class="alert bad" hidden></div>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
        <p class="note"><x-v2.icon name="shield"/><span>{{ __('studies.up.privacy') }}</span></p>
        <button class="btn primary" id="go"><x-v2.icon name="send"/>{{ __('studies.up.send') }}</button>
      </div>
    </form>

    <div class="card proc" id="proc" hidden aria-live="polite">
      <div class="label">{{ __('studies.up.processing') }}</div>
      <h2>{{ __('studies.up.reviewing') }}</h2>
      <div class="bar"><i id="pbar"></i></div>
      <div class="detail" id="pdetail"></div>
      <ul class="psteps" id="steps">
        @foreach ([['receive', []], ['read', []], ['extract', []], ['check', ['spec' => $spec]], ['markup', []]] as [$k, $args])
          <li><span class="st"></span><div><b>{{ __("studies.up.steps.$k.0", $args) }}</b><small>{{ __("studies.up.steps.$k.1", $args) }}</small></div></li>
        @endforeach
      </ul>
    </div>

    <div class="card done-card" id="done" hidden>
      <div class="tick"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
      <div class="label">{{ __('studies.up.sent_label') }}</div>
      <h2>{{ __('studies.up.sent') }}</h2>
      <p class="lead" id="doneLead"></p>
      <div class="next">
        <div><b>1</b>{{ __('studies.up.next1') }}</div>
        <div><b>2</b>{{ __('studies.up.next2') }}</div>
        <div><b>3</b>{{ __('studies.up.next3') }}</div>
      </div>
      <p class="mute" style="margin:0 0 6px;font-size:14px">{{ __('v2.submit.code') }}</p>
      <div class="code" id="doneCode" dir="ltr"></div>
      <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:26px">
        <a class="btn primary" href="{{ route('v2.studies') }}">{{ __('v2.submit.another') }}</a>
        <a class="btn line" href="{{ route('v2.home') }}">{{ __('v2.nav.home') }}</a>
      </div>
    </div>
  </div>
</section>
@push('scripts')
@php $texts = [
  'pdf' => __('v2.submit.err_pdf'), 'size' => __('v2.submit.err_size', ['mb' => $maxMb]), 'required' => __('v2.errors.required'),
  'uploading' => __('studies.up.d.uploading'), 'opening' => __('studies.up.d.opening'), 'page' => __('studies.up.d.page'),
  'checking' => __('studies.up.d.checking', ['spec' => $spec]), 'marking' => __('studies.up.d.marking'), 'lead' => __('studies.up.lead'),
]; @endphp
<script>
(() => {
  const T = {!! json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!};
  const MAX = {{ (int) $maxMb }} * 1048576, CHUNK = {{ (int) $chunk }};
  const U = { create: @json(route('v2.submit.create')), chunk: @json(url('v2/submit/__C__/chunk')), step: @json(url('v2/submit/__C__/step')) };
  const csrf = document.querySelector('meta[name=csrf-token]').content;
  const $ = id => document.getElementById(id);
  const fmt = (s, o) => s.replace(/:(\w+)/g, (m, k) => k in o ? o[k] : m);
  const tick = '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
  let file = null;
  const showErr = m => { $('msg').textContent = m; $('msg').hidden = !m; };
  const pick = f => {
    if (!f) return;
    if (f.type !== 'application/pdf' && !/\.pdf$/i.test(f.name)) return showErr(T.pdf);
    if (f.size > MAX) return showErr(T.size);
    file = f; showErr('');
    $('pname').textContent = f.name; $('psize').textContent = (f.size / 1048576).toFixed(1) + ' MB';
    $('picked').hidden = false; $('drop').hidden = true;
  };
  $('file').addEventListener('change', e => pick(e.target.files[0]));
  $('clear').addEventListener('click', () => { file = null; $('file').value = ''; $('picked').hidden = true; $('drop').hidden = false; });
  const drop = $('drop');
  drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('over'); });
  drop.addEventListener('dragleave', () => drop.classList.remove('over'));
  drop.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('over'); pick(e.dataTransfer.files[0]); });

  // step n is running (earlier ones done), the bar at pct, the line under it says exactly what is happening
  const steps = [...$('steps').children];
  const stage = (n, pct, detail) => {
    steps.forEach((li, i) => { li.className = i < n ? 'done' : i === n ? 'active' : ''; li.querySelector('.st').innerHTML = i < n ? tick : ''; });
    $('pbar').style.width = pct + '%'; $('pdetail').textContent = detail;
  };
  const post = async (url, body, json = true) => {
    const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', ...(json ? { 'Content-Type': 'application/json' } : {}) }, body: json ? JSON.stringify(body) : body });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) { const first = data.errors ? Object.values(data.errors)[0][0] : null; throw new Error(first || data.error || data.message || ('Error ' + res.status)); }
    return data;
  };
  const wait = ms => new Promise(r => setTimeout(r, ms));

  $('sf').addEventListener('submit', async e => {
    e.preventDefault(); showErr('');
    const form = e.target;
    for (const el of form.querySelectorAll('[required]')) if (!el.value.trim()) { el.focus(); return showErr(T.required); }
    if (!form.client_email.checkValidity()) { form.client_email.focus(); return showErr(form.client_email.validationMessage); }
    if (!file) return showErr(T.pdf);
    $('go').disabled = true;
    let code;
    try {
      const fields = Object.fromEntries(new FormData(form)); fields.file_name = file.name; fields.file_size = file.size;
      ({ code } = await post(U.create, fields));
      form.hidden = true; $('proc').hidden = false; $('proc').scrollIntoView({ block: 'start' });
      const total = Math.max(1, Math.ceil(file.size / CHUNK)); let res;
      for (let i = 0; i < total; i++) {
        stage(0, 2 + 8 * i / total, fmt(T.uploading, { name: file.name, pct: Math.round(100 * i / total) }));
        res = await post(U.chunk.replace('__C__', code) + `?index=${i}&total=${total}`, file.slice(i * CHUNK, (i + 1) * CHUNK), false);
      }
      stage(1, 10, fmt(T.opening, { n: res.pages }));
    } catch (err) {
      form.hidden = false; $('proc').hidden = true; showErr(err.message); $('go').disabled = false; return;
    }
    // the analysis prepares the engineer's draft; if it stops part-way the file is already with the team
    try {
      let panel = '';
      for (let n = 0; n < 600; n++) {
        const m = await post(U.step.replace('__C__', code), { phase: 'read' });
        if (m.panel) panel = m.panel;
        if (m.total) stage(m.page / m.total > .15 ? 2 : 1, 10 + 62 * m.page / m.total, fmt(T.page, { page: m.page, total: m.total }) + (panel ? ' · ' + panel : ''));
        if (m.done || m.error) break;
      }
      stage(3, 76, T.checking);
      const slow = setTimeout(() => stage(4, 88, T.marking), 1200);
      await post(U.step.replace('__C__', code), { phase: 'review' });
      clearTimeout(slow);
      stage(4, 96, T.marking); await wait(500);
      stage(5, 100, '');
    } catch (err) { /* the engineer can run it again from the dashboard */ }
    await wait(500);
    $('proc').hidden = true; $('done').hidden = false;
    $('doneCode').textContent = code;
    $('doneLead').textContent = fmt(T.lead, { email: form.client_email.value });
    $('done').scrollIntoView({ block: 'start' });
  });
})();
</script>
@endpush
@endsection
