@extends('v2.layouts.site')
@php use App\V2\Site; use App\Studies\StudyTypes as T; @endphp
@section('content')
<section class="slider" id="slider" aria-roledescription="carousel" aria-label="{{ Site::name() }}">
  @foreach ($slides as $i => $s)
    <div class="slide {{ $i === 0 ? 'on' : '' }}" role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} / {{ count($slides) }}" @if ($i) aria-hidden="true" @endif>
      <div class="bg" style="background-image:url('{{ $s['image'] }}')"></div>
      <div class="wrap">
        <div class="txt">
          @if ($s['eyebrow'])<span class="eyebrow">{{ $s['eyebrow'] }}</span>@endif
          @if ($i === 0)<h1>{{ $s['title'] }}</h1>@else<h2 class="h1">{{ $s['title'] }}</h2>@endif
          @if ($s['text'])<p class="lead">{{ $s['text'] }}</p>@endif
          <div class="ctas">
            <a class="btn accent" href="{{ route('v2.studies') }}"><x-v2.icon name="clipboard"/>{{ __('site.cta') }}</a>
            <a class="btn ghost" href="{{ route('v2.projects') }}">{{ __('v2.hero.cta2') }} <x-v2.icon name="arrow" class="i arrow"/></a>
          </div>
        </div>
      </div>
    </div>
  @endforeach
  @if (count($slides) > 1)
    <div class="ctrl wrap">
      <div class="dots">@foreach ($slides as $i => $s)<button type="button" class="{{ $i === 0 ? 'on' : '' }}" aria-label="{{ __('site.goto', ['n' => $i + 1]) }}"><i></i></button>@endforeach</div>
      <div class="arrows"><button type="button" class="prev" aria-label="{{ __('site.prev') }}"><x-v2.icon name="arrow"/></button><button type="button" class="next" aria-label="{{ __('site.next') }}"><x-v2.icon name="arrow"/></button></div>
    </div>
  @endif
  <div class="scroll-cue" aria-hidden="true"><span></span></div>
</section>

@if ($stats)
<section class="stats-strip">
  <div class="wrap"><div class="stats reveal">@foreach ($stats as $s)<div><b>{{ $s['value'] }}</b><span>{{ Site::t($s, 'label') }}</span></div>@endforeach</div></div>
</section>
@endif

<section class="block">
  <div class="wrap">
    <div class="head reveal"><div><span class="eyebrow">{{ __('site.studies_eyebrow') }}</span><h2 style="margin-top:10px">{{ __('site.studies_title') }}</h2><p>{{ __('site.studies_sub') }}</p></div><a class="more" href="{{ route('v2.studies') }}">{{ __('site.all_studies') }} <x-v2.icon name="arrow" class="i arrow"/></a></div>
    <div class="grid g3">
      @foreach ($types as $key => $def)
        <a class="card svc study reveal" style="--d: {{ $loop->index }}" href="{{ route('v2.studies.form', $key) }}">
          <div class="ic"><x-v2.icon :name="$def['icon'] ?? 'clipboard'"/></div>
          <h3>{{ T::t($def['name']) }}</h3>
          <p>{{ T::t($def['summary'] ?? '') }}</p>
          <span class="go">{{ __('studies.index.start') }} <x-v2.icon name="arrow" class="i arrow"/></span>
        </a>
      @endforeach
    </div>
  </div>
</section>

<section class="block alt how-sec">
  <div class="wrap">
    <div class="head reveal" style="justify-content:center;text-align:center"><div><span class="eyebrow">{{ __('site.how_eyebrow') }}</span><h2 style="margin-top:10px">{{ __('site.how_title') }}</h2></div></div>
    <ol class="how">
      @foreach (__('site.how') as $i => $h)
        <li class="reveal" style="--d: {{ $i }}"><span class="n">{{ $i + 1 }}</span><x-v2.icon :name="['clipboard', 'upload', 'shield'][$i] ?? 'check'" class="i big"/><h3>{{ $h['t'] }}</h3><p>{{ $h['d'] }}</p></li>
      @endforeach
    </ol>
  </div>
</section>

@if ($services->isNotEmpty())
<section class="block">
  <div class="wrap">
    <div class="head reveal"><div><span class="eyebrow">{{ __('v2.nav.services') }}</span><h2 style="margin-top:10px">{{ __('v2.home.services_title') }}</h2><p>{{ __('v2.home.services_sub') }}</p></div><a class="more" href="{{ route('v2.services') }}">{{ __('v2.home.all_services') }} <x-v2.icon name="arrow" class="i arrow"/></a></div>
    <div class="grid g3">
      @foreach ($services as $s)
        <div class="card svc reveal" style="--d: {{ $loop->index % 3 }}"><div class="ic"><x-v2.icon :name="$s->icon"/></div><h3>{{ $s->t('title') }}</h3><p>{{ $s->t('summary') }}</p></div>
      @endforeach
    </div>
  </div>
</section>
@endif

@if ($projects->isNotEmpty())
<section class="block alt">
  <div class="wrap">
    <div class="head reveal"><div><span class="eyebrow">{{ __('v2.nav.projects') }}</span><h2 style="margin-top:10px">{{ __('v2.home.projects_title') }}</h2><p>{{ __('v2.home.projects_sub') }}</p></div><a class="more" href="{{ route('v2.projects') }}">{{ __('v2.home.all_projects') }} <x-v2.icon name="arrow" class="i arrow"/></a></div>
    <div class="grid g3">@foreach ($projects as $p) <div class="reveal" style="--d: {{ $loop->index }}">@include('v2.partials.project-card')</div> @endforeach</div>
  </div>
</section>
@endif

@include('v2.partials.cta')
@endsection
@push('scripts')
<script>
// banner: cross-fade with a slow zoom, autoplay that pauses on hover / hidden tab, arrows, dots, swipe and keys
(function () {
  var root = document.getElementById('slider'); if (!root) return;
  var slides = root.querySelectorAll('.slide'), dots = root.querySelectorAll('.dots button'), n = slides.length; if (n < 2) return;
  var i = 0, timer = null, DELAY = 6500, rtl = document.documentElement.dir === 'rtl';
  root.style.setProperty('--delay', DELAY + 'ms');
  function go(k) {
    slides[i].classList.remove('on'); slides[i].setAttribute('aria-hidden', 'true'); dots[i] && dots[i].classList.remove('on');
    i = (k + n) % n;
    slides[i].classList.add('on'); slides[i].removeAttribute('aria-hidden');
    if (dots[i]) { dots[i].classList.remove('on'); void dots[i].offsetWidth; dots[i].classList.add('on'); }
    play();
  }
  function play() { clearTimeout(timer); if (!root.classList.contains('paused')) timer = setTimeout(function () { go(i + 1); }, DELAY); }
  root.querySelector('.next').addEventListener('click', function () { go(i + 1); });
  root.querySelector('.prev').addEventListener('click', function () { go(i - 1); });
  dots.forEach(function (d, k) { d.addEventListener('click', function () { go(k); }); });
  root.addEventListener('mouseenter', function () { root.classList.add('paused'); clearTimeout(timer); });
  root.addEventListener('mouseleave', function () { root.classList.remove('paused'); play(); });
  document.addEventListener('visibilitychange', function () { document.hidden ? clearTimeout(timer) : play(); });
  root.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight') go(rtl ? i - 1 : i + 1); if (e.key === 'ArrowLeft') go(rtl ? i + 1 : i - 1); });
  var x0 = null;
  root.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
  root.addEventListener('touchend', function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; x0 = null; if (Math.abs(dx) > 50) go((dx < 0) !== rtl ? i + 1 : i - 1); });
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) DELAY = 10000;
  play();
})();
</script>
@endpush
