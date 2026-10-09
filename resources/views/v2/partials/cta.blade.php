<section class="block" style="padding-top:clamp(40px, 6vw, 70px)">
  <div class="wrap">
    <div class="cta-band reveal">
      <div><h2>{{ __('site.band_title') }}</h2><p>{{ __('site.band_text') }}</p></div>
      <div style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn accent" href="{{ route('v2.studies') }}"><x-v2.icon name="clipboard"/>{{ __('site.cta') }}</a><a class="btn ghost" href="{{ route('v2.contact') }}">{{ __('v2.nav.contact') }}</a></div>
    </div>
  </div>
</section>
