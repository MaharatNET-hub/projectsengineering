@php
  use App\V2\Site;
  $unread = \App\Models\V2\Message::whereNull('read_at')->count();
  $me = auth()->user();
  $waiting = \App\Http\Controllers\V2\Admin\SubmissionController::visible($me)->whereIn('status', ['received', 'analysing', 'review'])->count();
  $studies = \App\Models\V2\Study::whereIn('status', ['submitted', 'review'])->when(! $me->isAdmin(), fn ($q) => $q->where(fn ($w) => $w->whereNull('assigned_to')->orWhere('assigned_to', $me->id)))->count();
  $items = [
    ['v2.admin.dashboard', 'dash', __('Dashboard'), null, 'v2.admin.dashboard'],
    ['v2.admin.submissions', 'inbox', $me->isAdmin() ? __('Submissions') : __('My requests'), $waiting, 'v2.admin.submissions*'],
    ['v2.admin.studies', 'clipboard', __('Studies (form)'), $studies, 'v2.admin.studies', 'v2.admin.studies.show'],
    ['v2.admin.studies.stats', 'chart', __('Statistics'), null, 'v2.admin.studies.stats'],
  ];
  if ($me->isAdmin()) {
    $items[] = ['v2.admin.messages', 'mail', __('Messages'), $unread, 'v2.admin.messages*'];
    $items[] = ['v2.admin.clients', 'user', __('Clients'), null, 'v2.admin.clients'];
  }
  $site = ! $me->isAdmin() ? [] : [
    ['v2.admin.categories', 'clipboard', __('Categories & criteria'), 'v2.admin.categories*'],
    ['v2.admin.study-types', 'edit', __('Study types'), 'v2.admin.study-types*'],
    ['v2.admin.users', 'users', __('Users'), 'v2.admin.users'],
    ['v2.admin.banner', 'layers', __('Home banner'), 'v2.admin.banner'],
    ['v2.admin.company', 'building', __('Company profile'), 'v2.admin.company'],
    ['v2.admin.services.index', 'star', __('Services'), 'v2.admin.services.*'],
    ['v2.admin.projects.index', 'file', __('Projects'), 'v2.admin.projects.*'],
    ['v2.admin.settings', 'cog', __('Settings'), 'v2.admin.settings'],
  ];
  $ar = app()->getLocale() === 'ar';
  $icon ??= null;
@endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? __('Dashboard') }} · {{ Site::name() }}</title>
<script>try { document.documentElement.dataset.theme = localStorage.getItem('admin-theme') || 'dark'; } catch (e) {}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('lib/v2/studies.css') }}?v={{ filemtime(public_path('lib/v2/studies.css')) }}">
<link rel="stylesheet" href="{{ asset('lib/v2/admin.css') }}?v={{ filemtime(public_path('lib/v2/admin.css')) }}">
@stack('head')
</head>
<body>
<div class="shell">
  <aside class="sidebar" id="sb">
    <a class="brand" href="{{ route('v2.admin.dashboard') }}"><span class="mark">{{ Site::initial() }}</span><span>{{ \Illuminate\Support\Str::limit(Site::t(Site::company(), 'name') ?: 'Admin', 22) }}<small>{{ __('Control center') }}</small></span></a>
    <div class="grp">{{ __('Operations') }}</div>
    @foreach ($items as $it)
      @php [$r, $ic, $label, $badge] = $it; $pats = array_slice($it, 4); @endphp
      <a class="nav {{ request()->routeIs(...$pats) ? 'on' : '' }}" href="{{ route($r) }}"><x-v2.icon :name="$ic"/>{{ $label }}@if ($badge)<span class="badge">{{ $badge }}</span>@endif</a>
    @endforeach
    @if ($site)<div class="grp">{{ __('Administration') }}</div>@endif
    @foreach ($site as [$r, $ic, $label, $pat])
      <a class="nav {{ request()->routeIs($pat) ? 'on' : '' }}" href="{{ route($r) }}"><x-v2.icon :name="$ic"/>{{ $label }}</a>
    @endforeach
    <div class="bottom">
      <a class="nav {{ request()->routeIs('v2.admin.me') ? 'on' : '' }}" href="{{ route('v2.admin.me') }}"><x-v2.icon name="user"/>{{ __('My account') }}</a>
      <a class="nav" href="{{ route('v2.home') }}" target="_blank"><x-v2.icon name="globe"/>{{ __('View website') }}</a>
      <form method="post" action="{{ route('v2.admin.logout') }}">@csrf<button><x-v2.icon name="out"/>{{ __('Log out') }}</button></form>
    </div>
  </aside>
  <div class="main">
    <header class="topbar">
      <div class="ttl">
        <button class="btn burger" type="button" onclick="document.getElementById('sb').classList.toggle('open')" aria-label="Menu"><x-v2.icon name="menu"/></button>
        <span class="pic"><x-v2.icon :name="$icon ?? 'dash'"/></span>
        <div style="min-width:0">@yield('crumb')<h1>{{ $title ?? __('Dashboard') }}</h1></div>
      </div>
      <div class="tools">
        <a class="tool" href="{{ route('v2.admin.lang', $ar ? 'en' : 'ar') }}" title="{{ $ar ? 'English' : 'العربية' }}"><x-v2.icon name="globe"/>{{ $ar ? 'English' : 'العربية' }}</a>
        <button class="tool" type="button" id="themeBtn" title="{{ __('Light / dark') }}"><x-v2.icon name="sun" class="i t-sun"/><x-v2.icon name="moon" class="i t-moon"/></button>
        <div class="who"><div><b>{{ \Illuminate\Support\Str::limit($me->name, 22) }}</b><small>{{ $me->title ?: ($me->isAdmin() ? __('Administrator') : __('Engineer')) }}</small></div><span class="av">{{ mb_strtoupper(mb_substr($me->name, 0, 1)) }}</span></div>
      </div>
    </header>
    @hasSection('actions')<div class="acts" style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;margin-top:14px">@yield('actions')</div>@endif
    <div class="content">
      @if (session('ok'))<div class="alert ok">{{ session('ok') }}</div>@endif
      @if (session('bad'))<div class="alert bad">{{ session('bad') }}</div>@endif
      @if ($errors->any())<div class="alert bad">{{ $errors->first() }}</div>@endif
      @yield('content')
    </div>
  </div>
</div>
<style>[data-theme="dark"] .t-sun { display: none; } [data-theme="light"] .t-moon { display: none; }</style>
<script>
document.getElementById('themeBtn').addEventListener('click', () => {
  const t = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
  document.documentElement.dataset.theme = t;
  try { localStorage.setItem('admin-theme', t); } catch (e) {}
});
// KPI numbers count up once
document.querySelectorAll('.kpi b').forEach(el => {
  const m = el.textContent.trim().match(/^(\d+)$/); if (!m || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const end = +m[1], t0 = performance.now(); if (!end) return;
  const step = t => { const k = Math.min(1, (t - t0) / 900); el.textContent = Math.round(end * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(step); };
  requestAnimationFrame(step);
});
</script>
@stack('scripts')
</body>
</html>
