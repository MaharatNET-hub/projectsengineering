@extends('v2.layouts.admin', ['title' => __('Home banner'), 'icon' => 'layers'])
@section('actions')<a class="btn" href="{{ route('v2.home') }}" target="_blank"><x-v2.icon name="eye"/>{{ __('View website') }}</a>@endsection
@section('content')
<p class="mute" style="margin-top:0">{{ __('The slides turn every few seconds with a slow zoom. Upload a photo for a slide, or keep one of the built-in drawings. Wide photos (1600 × 700 px or more) look best.') }}</p>
<form method="post" action="{{ route('v2.admin.banner.update') }}" enctype="multipart/form-data" class="grid" id="bf">
  @csrf @method('put')
  @foreach (array_merge($slides, [[]]) as $i => $s)
    @php $blank = $s === []; @endphp
    @if ($blank)<template id="slideTpl">@endif
    <div class="card pad form" data-slide>
      <div style="display:grid;grid-template-columns:260px 1fr;gap:18px" class="slide-row">
        <div>
          <div style="aspect-ratio:16/7;border-radius:12px;overflow:hidden;background:#0a1430;border:1px solid var(--line)">
            <img data-prev src="{{ ! $blank && ! empty($s['image']) ? route('v2.media', $s['image']) : asset('lib/v2/banner/' . ($s['art'] ?? 'electrical') . '.svg') }}" alt="" style="width:100%;height:100%;object-fit:cover">
          </div>
          <input type="hidden" name="slides[{{ $blank ? '__I__' : $i }}][image]" value="{{ $s['image'] ?? '' }}">
          <label class="f" style="margin-top:10px">{{ __('Photo') }}<input class="inp" type="file" name="slides[{{ $blank ? '__I__' : $i }}][photo]" accept="image/jpeg,image/png,image/webp" data-file></label>
          <label class="f" style="margin-top:8px">{{ __('Drawing (when there is no photo)') }}<select class="inp" name="slides[{{ $blank ? '__I__' : $i }}][art]" data-art>@foreach (\App\Http\Controllers\V2\Admin\BannerController::ARTS as $a)<option value="{{ $a }}" @selected(($s['art'] ?? 'electrical') === $a)>{{ __(ucfirst($a)) }}</option>@endforeach</select></label>
          @if (! empty($s['image']))<label class="check" style="margin-top:8px"><input type="checkbox" name="slides[{{ $i }}][drop_photo]" value="1"> {{ __('Remove the photo') }}</label>@endif
        </div>
        <div class="form">
          <div class="lang-pair">
            <label class="f">{{ __('Title (English)') }}<input class="inp" name="slides[{{ $blank ? '__I__' : $i }}][title_en]" value="{{ $s['title_en'] ?? '' }}" maxlength="120"></label>
            <label class="f">{{ __('Title (Arabic)') }}<input class="inp" dir="rtl" name="slides[{{ $blank ? '__I__' : $i }}][title_ar]" value="{{ $s['title_ar'] ?? '' }}" maxlength="120"></label>
            <label class="f">{{ __('Line under the title (English)') }}<textarea class="inp" name="slides[{{ $blank ? '__I__' : $i }}][sub_en]" style="min-height:64px" maxlength="240">{{ $s['sub_en'] ?? '' }}</textarea></label>
            <label class="f">{{ __('Line under the title (Arabic)') }}<textarea class="inp" dir="rtl" name="slides[{{ $blank ? '__I__' : $i }}][sub_ar]" style="min-height:64px" maxlength="240">{{ $s['sub_ar'] ?? '' }}</textarea></label>
          </div>
          <label class="check"><input type="checkbox" name="slides[{{ $blank ? '__I__' : $i }}][remove]" value="1"> {{ __('Delete this slide') }}</label>
        </div>
      </div>
    </div>
    @if ($blank)</template>@endif
  @endforeach
  <div style="display:flex;gap:10px"><button type="button" class="btn line" id="addSlide"><x-v2.icon name="plus"/>{{ __('Add a slide') }}</button><button class="btn primary"><x-v2.icon name="check"/>{{ __('Save the banner') }}</button></div>
</form>
<style>@media (max-width: 760px) { .slide-row { grid-template-columns: 1fr !important; } }</style>
@endsection
@push('scripts')
<script>
(() => {
  const form = document.getElementById('bf'), art = '{{ asset('lib/v2/banner') }}/';
  let n = 50;
  const wire = el => {
    el.querySelector('[data-file]').addEventListener('change', e => { const f = e.target.files[0]; if (f) el.querySelector('[data-prev]').src = URL.createObjectURL(f); });
    el.querySelector('[data-art]').addEventListener('change', e => { if (!el.querySelector('[data-file]').files.length && !el.querySelector('input[type=hidden]').value) el.querySelector('[data-prev]').src = art + e.target.value + '.svg'; });
  };
  form.querySelectorAll('[data-slide]').forEach(wire);
  document.getElementById('addSlide').addEventListener('click', () => {
    if (form.querySelectorAll('[data-slide]').length >= 6) return;
    const t = document.createElement('div'); t.innerHTML = document.getElementById('slideTpl').innerHTML.replaceAll('__I__', n++);
    const el = t.firstElementChild; form.insertBefore(el, document.getElementById('addSlide').parentNode); wire(el);
  });
})();
</script>
@endpush
