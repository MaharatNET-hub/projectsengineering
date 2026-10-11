@php use App\V2\Site; $c = Site::contact(); $nav = ['home' => 'v2.home', 'about' => 'v2.about', 'services' => 'v2.services', 'projects' => 'v2.projects', 'contact' => 'v2.contact'];
  // the drawing behind each page's banner (the home page has its own slider)
  $art = match (true) { request()->routeIs('v2.services', 'v2.track*') => 'mechanical', request()->routeIs('v2.about', 'v2.project*', 'v2.account*') => 'buildings', default => 'electrical' }; @endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ Site::ar() ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ isset($title) ? $title . ' · ' : '' }}{{ Site::name() }}</title>
<meta name="description" content="{{ Site::t(Site::company(), 'tagline') }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('lib/v2/site.css') }}?v={{ filemtime(public_path('lib/v2/site.css')) }}">
@stack('head')
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%2312306b'/%3E%3Ccircle cx='25' cy='25' r='6' fill='%23f2a516'/%3E%3C/svg%3E">
</head>
<body>
<header class="top">
  <div class="wrap">
    <a class="logo" href="{{ route('v2.home') }}"><span class="mark">{{ Site::initial() }}</span><span>{{ Site::name() }}</span></a>
    <button class="burger" type="button" aria-label="Menu" onclick="document.getElementById('nav').classList.toggle('open')"><x-v2.icon name="menu"/></button>
    <nav class="nav" id="nav">
      @foreach ($nav as $k => $r)
        <a href="{{ route($r) }}" class="{{ request()->routeIs($r) || ($k === 'projects' && request()->routeIs('v2.project')) ? 'on' : '' }}">{{ __("v2.nav.$k") }}</a>
      @endforeach
      <a href="{{ route('v2.track') }}" class="{{ request()->routeIs('v2.track*') ? 'on' : '' }}">{{ __('v2.nav.track') }}</a>
      <a class="m-only" href="{{ auth('client')->check() ? route('v2.account') : route('v2.account.login') }}">{{ auth('client')->check() ? __('studies.account.menu') : __('studies.account.login') }}</a>
    </nav>
    <div class="tools">
      <a class="lang acct" href="{{ auth('client')->check() ? route('v2.account') : route('v2.account.login') }}">{{ auth('client')->check() ? __('studies.account.menu') : __('studies.account.login') }}</a>
      <a class="lang" href="{{ Site::switchUrl() }}" hreflang="{{ Site::ar() ? 'en' : 'ar' }}">{{ __('v2.lang_switch') }}</a>
      <a class="btn glow" href="{{ route('v2.studies') }}"><x-v2.icon name="clipboard"/>{{ __('v2.nav.submit_short') }}</a>
    </div>
  </div>
</header>

<main style="--art: url('{{ asset("lib/v2/banner/$art.svg") }}')">@yield('content')</main>

<footer class="foot">
  <div class="wrap">
    <div class="cols">
      <div class="brand">
        <a class="logo" href="{{ route('v2.home') }}"><span class="mark">{{ Site::initial() }}</span><span>{{ Site::name() }}</span></a>
        <p>{{ Site::t(Site::company(), 'tagline') }}</p>
        <a class="btn glow" href="{{ route('v2.studies') }}"><x-v2.icon name="clipboard"/>{{ __('v2.nav.submit_short') }}</a>
      </div>
      <div>
        <h4>{{ __('v2.footer.quick') }}</h4>
        <div class="links">
          @foreach ($nav as $k => $r)<a href="{{ route($r) }}">{{ __("v2.nav.$k") }}</a>@endforeach
          <a href="{{ route('v2.studies') }}">{{ __('studies.nav') }}</a>
          <a href="{{ route('v2.track') }}">{{ __('v2.nav.track') }}</a>
        </div>
      </div>
      <div>
        <h4>{{ __('v2.footer.reach') }}</h4>
        @if (!empty($c['email']))<a class="reach" href="mailto:{{ $c['email'] }}"><x-v2.icon name="mail"/><span dir="ltr">{{ $c['email'] }}</span></a>@endif
        @if (!empty($c['phone']))<a class="reach" href="tel:{{ preg_replace('/[^+\d]/', '', $c['phone']) }}"><x-v2.icon name="phone"/><span dir="ltr">{{ $c['phone'] }}</span></a>@endif
        @if (Site::t($c, 'address'))<span class="reach"><x-v2.icon name="pin"/><span>{{ Site::t($c, 'address') }}</span></span>@endif
      </div>
    </div>
    <div class="bottom"><span>© {{ date('Y') }} {{ Site::name() }}. {{ __('v2.footer.rights') }}</span><a href="{{ route('v2.admin.dashboard') }}">{{ __('v2.nav.admin') }}</a></div>
  </div>
</footer>
<script>
// cards and sections rise in as they scroll into view; numbers count up once
(() => {
  const still = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const els = document.querySelectorAll('.reveal');
  if (still || !('IntersectionObserver' in window)) { els.forEach(e => e.classList.add('in')); return; }
  const count = el => {
    const m = String(el.dataset.count).match(/^([^\d]*)([\d,.]+)(.*)$/); if (!m) return;
    const end = parseFloat(m[2].replace(/,/g, '')), dec = (m[2].split('.')[1] || '').length, t0 = performance.now();
    const step = t => { const k = Math.min(1, (t - t0) / 1200), v = end * (1 - Math.pow(1 - k, 3));
      el.textContent = m[1] + v.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }) + m[3]; if (k < 1) requestAnimationFrame(step); };
    requestAnimationFrame(step);
  };
  const io = new IntersectionObserver(es => es.forEach(e => { if (!e.isIntersecting) return; e.target.classList.add('in'); e.target.querySelectorAll('[data-count]').forEach(count); io.unobserve(e.target); }), { threshold: .12 });
  els.forEach(e => io.observe(e));
})();
</script>
@stack('scripts')
</body>
</html>
