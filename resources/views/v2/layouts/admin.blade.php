@php
  use App\V2\Site;
  $ar = Site::ar();
  $unread = \App\Models\V2\Message::whereNull('read_at')->count();
  $me = auth()->user();
  $waiting = \App\Http\Controllers\V2\Admin\SubmissionController::visible($me)->whereIn('status', ['received', 'analysing', 'review'])->count();
  $studies = \App\Models\V2\Study::whereIn('status', ['submitted', 'review'])->when(! $me->isAdmin(), fn ($q) => $q->where(fn ($w) => $w->whereNull('assigned_to')->orWhere('assigned_to', $me->id)))->count();
  // the file-upload intake is optional: its menu entry shows only while there is something in it
  $hasSubmissions = $waiting || \App\Models\V2\Submission::exists();
  $groups = [
    '' => [['v2.admin.dashboard', 'home', __('Home'), null, 'v2.admin.dashboard']],
    __('Review centre') => array_values(array_filter([
      ['v2.admin.studies', 'clipboard', __('Studies'), $studies, 'v2.admin.studies', 'v2.admin.studies.show'],
      ['v2.admin.studies.stats', 'chart', __('Statistics'), null, 'v2.admin.studies.stats'],
      $hasSubmissions ? ['v2.admin.submissions', 'inbox', $me->isAdmin() ? __('Submissions') : __('My requests'), $waiting, 'v2.admin.submissions*'] : null,
      $me->isAdmin() ? ['v2.admin.messages', 'mail', __('Messages'), $unread, 'v2.admin.messages*'] : null,
      $me->isAdmin() ? ['v2.admin.clients', 'users', __('Clients'), null, 'v2.admin.clients'] : null,
    ])),
  ];
  if ($me->isAdmin()) {
    $groups[__('Administration')] = [
      ['v2.admin.categories', 'layers', __('Categories & criteria'), null, 'v2.admin.categories*'],
      ['v2.admin.study-types', 'edit', __('Study types'), null, 'v2.admin.study-types*'],
      ['v2.admin.users', 'helmet', __('Users'), null, 'v2.admin.users'],
    ];
    $groups[__('Website')] = [
      ['v2.admin.company', 'building', __('Company profile'), null, 'v2.admin.company'],
      ['v2.admin.services.index', 'star', __('Services'), null, 'v2.admin.services.*'],
      ['v2.admin.projects.index', 'image', __('Projects'), null, 'v2.admin.projects.*'],
      ['v2.admin.settings', 'cog', __('Settings'), null, 'v2.admin.settings'],
    ];
  }
  $brand = Site::t(Site::company(), 'name') ?: 'Admin';
  // the page's icon tile follows the menu entry it belongs to
  if (! isset($icon)) {
    $icon = 'home';
    foreach (array_merge(...array_values($groups)) as $it) {
      if (request()->routeIs(...array_slice($it, 4))) { $icon = $it[1]; break; }
    }
    if (request()->routeIs('v2.admin.me')) $icon = 'user';
  }
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? __('Dashboard') }} · {{ __('Control centre') }} · {{ Site::name() }}</title>
<script>try { var t = localStorage.getItem('v2-admin-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('lib/v2/admin.css') }}?v={{ filemtime(public_path('lib/v2/admin.css')) }}">
<link rel="stylesheet" href="{{ asset('lib/v2/studies.css') }}?v={{ filemtime(public_path('lib/v2/studies.css')) }}">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230e1838'/%3E%3Ccircle cx='16' cy='16' r='7' fill='%2338bdf8'/%3E%3C/svg%3E">
</head>
<body>
<div class="shell">
  <aside class="sidebar" id="sb">
    <a class="brand" href="{{ route('v2.admin.dashboard') }}"><span class="mark">{{ Site::initial() }}</span><span class="nm"><b>{{ \Illuminate\Support\Str::limit($brand, 22) }}</b><small>{{ __('Control centre') }}</small></span></a>
    <nav class="menu">
      @foreach ($groups as $grp => $items)
        @if ($items)
          @if ($grp !== '')<div class="grp">{{ $grp }}</div>@endif
          @foreach ($items as $it)
            @php [$r, $ic, $label, $badge] = $it; $pats = array_slice($it, 4); @endphp
            <a class="nav {{ request()->routeIs(...$pats) ? 'on' : '' }}" href="{{ route($r) }}"><x-v2.icon :name="$ic"/><span>{{ $label }}</span>@if ($badge)<span class="badge">{{ $badge }}</span>@endif</a>
          @endforeach
        @endif
      @endforeach
    </nav>
    <div class="bottom">
      <a class="nav {{ request()->routeIs('v2.admin.me') ? 'on' : '' }}" href="{{ route('v2.admin.me') }}"><x-v2.icon name="user"/><span>{{ __('My account') }}</span></a>
      <a class="nav" href="{{ route('v2.home') }}" target="_blank"><x-v2.icon name="globe"/><span>{{ __('View website') }}</span></a>
      <form method="post" action="{{ route('v2.admin.logout') }}">@csrf<button><x-v2.icon name="out"/><span>{{ __('Log out') }}</span></button></form>
    </div>
  </aside>
  <div class="scrim" onclick="document.getElementById('sb').classList.remove('open')"></div>
  <div class="main">
    <header class="topbar">
      <button class="icon-btn burger" type="button" onclick="document.getElementById('sb').classList.toggle('open')" aria-label="{{ __('Menu') }}"><x-v2.icon name="menu"/></button>
      <span class="where">{{ __('Control centre') }}</span>
      <form class="search" method="get" action="{{ route('v2.admin.studies') }}" role="search">
        <x-v2.icon name="search"/>
        <input type="search" name="q" id="gq" value="{{ request()->routeIs('v2.admin.studies') ? request('q') : '' }}" placeholder="{{ __('Code, project, client or email…') }}" aria-label="{{ __('Search studies') }}">
        <kbd>Ctrl K</kbd>
      </form>
      <div class="tools">
        @if ($studies)<a class="pill warn" href="{{ route('v2.admin.studies', ['status' => 'submitted']) }}" title="{{ __('Studies waiting for review') }}"><x-v2.icon name="clipboard"/><span>{{ $studies }}</span></a>@endif
        @if ($me->isAdmin() && $unread)<a class="pill bad" href="{{ route('v2.admin.messages') }}" title="{{ __('Unread messages') }}"><x-v2.icon name="mail"/><span>{{ $unread }}</span></a>@endif
        <button class="icon-btn" type="button" id="themeBtn" aria-label="{{ __('Light / dark theme') }}" title="{{ __('Light / dark theme') }}"><x-v2.icon name="sun" class="i sun"/><x-v2.icon name="moon" class="i moon"/></button>
        <a class="icon-btn lang" href="{{ Site::switchUrl() }}" hreflang="{{ $ar ? 'en' : 'ar' }}"><x-v2.icon name="globe"/><span>{{ $ar ? 'English' : 'العربية' }}</span></a>
        <a class="me" href="{{ route('v2.admin.me') }}"><span class="who"><b>{{ \Illuminate\Support\Str::limit($me->name ?: $me->email, 20) }}</b><small>{{ $me->isAdmin() ? __('Administrator') : __('Engineer') }}</small></span><span class="av">{{ mb_strtoupper(mb_substr($me->name ?: $me->email, 0, 1)) }}</span></a>
      </div>
    </header>
    <div class="page-head">
      <div class="ttl">
        <span class="tile"><x-v2.icon :name="$icon ?? 'home'"/></span>
        <div style="min-width:0">@yield('crumb')<h1>{{ $title ?? __('Dashboard') }}</h1>@isset($subtitle)<p>{{ $subtitle }}</p>@endisset</div>
      </div>
      <div class="acts">@yield('actions')</div>
    </div>
    <div class="content">
      @if (session('ok'))<div class="alert ok">{{ session('ok') }}</div>@endif
      @if (session('bad'))<div class="alert bad">{{ session('bad') }}</div>@endif
      @if ($errors->any())<div class="alert bad">{{ $errors->first() }}</div>@endif
      @yield('content')
    </div>
    <footer class="foot"><span>© {{ date('Y') }} {{ Site::name() }} · {{ __('Control centre') }}</span><span class="mono">{{ now()->format('Y-m-d H:i') }}</span></footer>
  </div>
</div>
<script>
(function () {
  var root = document.documentElement, btn = document.getElementById('themeBtn');
  btn.addEventListener('click', function () {
    var dark = root.dataset.theme ? root.dataset.theme === 'dark' : true;
    root.dataset.theme = dark ? 'light' : 'dark';
    try { localStorage.setItem('v2-admin-theme', root.dataset.theme); } catch (e) {}
  });
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); document.getElementById('gq').focus(); }
  });
})();
</script>
@stack('scripts')
</body>
</html>
