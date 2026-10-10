@extends('v2.layouts.site')
@php use App\V2\Site; $co = Site::company(); $slides = Site::slides(); $types = \App\Studies\StudyTypes::all(); @endphp
@section('content')
{{-- animated banner: slides fade with a slow zoom; the text of each slide rises in --}}
<section class="slider" id="slider" aria-roledescription="carousel" aria-label="{{ Site::name() }}">
  @foreach ($slides as $i => $s)
    <div class="slide {{ $i === 0 ? 'on' : '' }}" aria-hidden="{{ $i === 0 ? 'false' : 'true' }}">
      <img src="{{ $s['img'] }}" alt="" class="{{ $s['mirror'] ? 'mirror' : '' }}" @if ($i) loading="lazy" @endif>
      <div class="shade"></div>
      <div class="wrap cap">
        <span class="eyebrow">{{ __('v2.hero.eyebrow') }}</span>
        @if ($i === 0)<h1>{{ $s['title'] }}</h1>@else<h2 class="h1">{{ $s['title'] }}</h2>@endif
        <p class="lead">{{ $s['sub'] }}</p>
        <div class="ctas">
          <a class="btn glow" href="{{ route('v2.studies') }}"><x-v2.icon name="clipboard"/>{{ __('v2.hero.cta') }}</a>
          <a class="btn ghost" href="{{ route('v2.projects') }}">{{ __('v2.hero.cta2') }} <x-v2.icon name="arrow" class="i arrow"/></a>
        </div>
      </div>
    </div>
  @endforeach
  @if (count($slides) > 1)
    <button class="nav-btn prev" type="button" aria-label="Previous" data-go="-1"><x-v2.icon name="arrow" class="i arrow"/></button>
    <button class="nav-btn next" type="button" aria-label="Next" data-go="1"><x-v2.icon name="arrow" class="i arrow"/></button>
    <div class="dots">@foreach ($slides as $i => $s)<button type="button" class="{{ $i === 0 ? 'on' : '' }}" aria-label="{{ $i + 1 }}" data-to="{{ $i }}"><i></i></button>@endforeach</div>
  @endif
</section>

{{-- the study types, right under the banner: the one way to send work --}}
<section class="block types-band">
  <div class="wrap">
    <div class="head reveal"><div><span class="eyebrow">{{ __('studies.nav') }}</span><h2 style="margin-top:10px">{{ __('studies.index.title') }}</h2><p>{{ __('studies.index.sub') }}</p></div><a href="{{ route('v2.studies') }}">{{ __('studies.form.back') }} →</a></div>
    <div class="grid g3">
      @foreach ($types as $key => $def)
        <a class="card svc tcard reveal" href="{{ route('v2.studies.form', $key) }}" style="--d: {{ $loop->index * 90 }}ms">
          <div class="ic"><x-v2.icon :name="$def['icon'] ?? 'clipboard'"/></div>
          <h3>{{ \App\Studies\StudyTypes::t($def['name']) }}</h3>
          <p>{{ \App\Studies\StudyTypes::t($def['summary'] ?? '') }}</p>
          <span class="go">{{ __('studies.index.start') }} <x-v2.icon name="arrow" class="i arrow"/></span>
        </a>
      @endforeach
    </div>
  </div>
</section>

@if ($stats)
<section class="block" style="padding-top:0">
  <div class="wrap"><div class="stats reveal">@foreach ($stats as $s)<div><b data-count="{{ $s['value'] }}">{{ $s['value'] }}</b><span>{{ Site::t($s, 'label') }}</span></div>@endforeach</div></div>
</section>
@endif

<section class="block alt">
  <div class="wrap feature">
    <div class="reveal">
      <span class="eyebrow">{{ __('v2.nav.submit') }}</span>
      <h2 style="margin-top:10px">{{ __('v2.home.review_title') }}</h2>
      <p class="mute" style="font-size:17px">{{ __('v2.home.review_sub') }}</p>
      <ul class="ticks">@foreach (__('studies.index.why') as $pt)<li><x-v2.icon name="check"/><span>{{ $pt }}</span></li>@endforeach</ul>
      <a class="btn primary" href="{{ route('v2.studies') }}"><x-v2.icon name="clipboard"/>{{ __('v2.hero.cta') }}</a>
    </div>
    <div class="mock reveal" dir="ltr" aria-hidden="true" style="--d:120ms">
      <div class="bar"><i></i><i></i><i></i></div>
      <div class="ln"><span class="no">1</span><b>EMDB-A-1 · Degree of protection IP43 — min. IP54</b><span class="tag fail">Fail</span></div>
      <div class="ln"><span class="no">2</span><b>C2 · Cable capacity 197 A &lt; device 250 A</b><span class="tag fail">Fail</span></div>
      <div class="ln"><span class="no">3</span><b>AHU-01 · Offered 110 kW ≥ design 100 kW</b><span class="tag ok">OK</span></div>
      <div class="ln"><span class="no">4</span><b>Form ≠ file · aux wiring 2.5 vs 1.5 mm² (p.2)</b><span class="tag warn">Clarify</span></div>
    </div>
  </div>
</section>

<section class="block">
  <div class="wrap">
    <div class="head reveal"><div><span class="eyebrow">{{ __('v2.nav.services') }}</span><h2 style="margin-top:10px">{{ __('v2.home.services_title') }}</h2><p>{{ __('v2.home.services_sub') }}</p></div><a href="{{ route('v2.services') }}">{{ __('v2.home.all_services') }} →</a></div>
    <div class="grid g3">
      @foreach ($services as $s)
        <div class="card svc reveal" style="--d: {{ $loop->index * 70 }}ms"><div class="ic"><x-v2.icon :name="$s->icon"/></div><h3>{{ $s->t('title') }}</h3><p>{{ $s->t('summary') }}</p></div>
      @endforeach
    </div>
  </div>
</section>

<section class="block alt">
  <div class="wrap">
    <div class="head reveal"><div><span class="eyebrow">{{ __('v2.nav.projects') }}</span><h2 style="margin-top:10px">{{ __('v2.home.projects_title') }}</h2><p>{{ __('v2.home.projects_sub') }}</p></div><a href="{{ route('v2.projects') }}">{{ __('v2.home.all_projects') }} →</a></div>
    <div class="grid g3">@foreach ($projects as $p)<div class="reveal" style="--d: {{ $loop->index * 90 }}ms">@include('v2.partials.project-card')</div>@endforeach</div>
  </div>
</section>

@include('v2.partials.cta')

@push('scripts')
<script>
(() => {
  const root = document.getElementById('slider'); if (!root) return;
  const slides = [...root.querySelectorAll('.slide')], dots = [...root.querySelectorAll('.dots button')];
  if (slides.length < 2) return;
  const still = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let at = 0, timer = null;
  const show = n => {
    at = (n + slides.length) % slides.length;
    slides.forEach((s, i) => { s.classList.toggle('on', i === at); s.setAttribute('aria-hidden', i === at ? 'false' : 'true'); });
    dots.forEach((d, i) => d.classList.toggle('on', i === at));
  };
  const play = () => { clearInterval(timer); if (!still) timer = setInterval(() => show(at + 1), 6500); };
  root.addEventListener('click', e => {
    const b = e.target.closest('[data-go],[data-to]'); if (!b) return;
    show(b.dataset.to !== undefined ? +b.dataset.to : at + (document.dir === 'rtl' ? -1 : 1) * +b.dataset.go); play();
  });
  root.addEventListener('mouseenter', () => clearInterval(timer));
  root.addEventListener('mouseleave', play);
  // swipe on phones
  let x0 = null;
  root.addEventListener('touchstart', e => x0 = e.touches[0].clientX, { passive: true });
  root.addEventListener('touchend', e => { if (x0 === null) return; const dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) { show(at + ((dx < 0) !== (document.dir === 'rtl') ? 1 : -1)); play(); } x0 = null; });
  document.addEventListener('visibilitychange', () => document.hidden ? clearInterval(timer) : play());
  play();
})();
</script>
@endpush
@endsection
