@extends('v2.layouts.admin', ['title' => __('Company profile')])
@section('actions')<a class="btn" href="{{ route('v2.about') }}" target="_blank"><x-v2.icon name="eye"/>{{ __('View “About us”') }}</a>@endsection
@section('content')
<form class="form" method="post" action="{{ route('v2.admin.company.save') }}" enctype="multipart/form-data" style="max-width:1000px">
  @csrf @method('put')
  <div class="card pad form">
    <div class="sec">{{ __('Identity') }}</div>
    <div class="lang-pair">
      <label class="f">{{ __('Company name') }} *<input class="inp" name="co[name_en]" value="{{ old('co.name_en', $co['name_en'] ?? '') }}" required></label>
      <label class="f">اسم الشركة *<input class="inp" name="co[name_ar]" dir="rtl" value="{{ old('co.name_ar', $co['name_ar'] ?? '') }}" required></label>
      <label class="f">{{ __('Tagline') }} <small>({{ __('home page headline text') }})</small><textarea class="inp" name="co[tagline_en]" style="min-height:64px">{{ old('co.tagline_en', $co['tagline_en'] ?? '') }}</textarea></label>
      <label class="f">الشعار النصي<textarea class="inp" name="co[tagline_ar]" dir="rtl" style="min-height:64px">{{ old('co.tagline_ar', $co['tagline_ar'] ?? '') }}</textarea></label>
    </div>
    <div class="sec">{{ __('About us') }}</div>
    <div class="lang-pair">
      <label class="f">{{ __('About') }} <small>({{ __('blank line = new paragraph') }})</small><textarea class="inp" name="co[about_en]" style="min-height:170px">{{ old('co.about_en', $co['about_en'] ?? '') }}</textarea></label>
      <label class="f">من نحن<textarea class="inp" name="co[about_ar]" dir="rtl" style="min-height:170px">{{ old('co.about_ar', $co['about_ar'] ?? '') }}</textarea></label>
      <label class="f">{{ __('Mission') }}<textarea class="inp" name="co[mission_en]" style="min-height:64px">{{ old('co.mission_en', $co['mission_en'] ?? '') }}</textarea></label>
      <label class="f">الرسالة<textarea class="inp" name="co[mission_ar]" dir="rtl" style="min-height:64px">{{ old('co.mission_ar', $co['mission_ar'] ?? '') }}</textarea></label>
    </div>
    <label class="f" style="max-width:200px">{{ __('Founded (year)') }}<input class="inp" type="number" name="co[founded]" value="{{ old('co.founded', $co['founded'] ?? '') }}"></label>
  </div>
  <div class="card pad form">
    <div class="sec">{{ __('Contact details') }}</div>
    <div class="row2">
      <label class="f">{{ __('Email') }}<input class="inp" type="email" name="c[email]" value="{{ old('c.email', $c['email'] ?? '') }}"></label>
      <label class="f">{{ __('Phone') }}<input class="inp" name="c[phone]" value="{{ old('c.phone', $c['phone'] ?? '') }}"></label>
    </div>
    <div class="lang-pair">
      <label class="f">{{ __('Address') }}<input class="inp" name="c[address_en]" value="{{ old('c.address_en', $c['address_en'] ?? '') }}"></label>
      <label class="f">العنوان<input class="inp" name="c[address_ar]" dir="rtl" value="{{ old('c.address_ar', $c['address_ar'] ?? '') }}"></label>
      <label class="f">{{ __('Working hours') }}<input class="inp" name="c[hours_en]" value="{{ old('c.hours_en', $c['hours_en'] ?? '') }}"></label>
      <label class="f">ساعات العمل<input class="inp" name="c[hours_ar]" dir="rtl" value="{{ old('c.hours_ar', $c['hours_ar'] ?? '') }}"></label>
    </div>
    <div class="row2">
      <label class="f">{{ __('WhatsApp') }}<input class="inp" name="c[whatsapp]" value="{{ old('c.whatsapp', $c['whatsapp'] ?? '') }}"></label>
      <label class="f">{{ __('LinkedIn URL') }}<input class="inp" type="url" name="c[linkedin]" value="{{ old('c.linkedin', $c['linkedin'] ?? '') }}"></label>
    </div>
  </div>
  <div class="card pad form">
    <div class="sec">{{ __('Numbers') }} <small class="mute" style="text-transform:none;letter-spacing:0;font-weight:400">— {{ __('shown on the home and about pages; leave the value empty to hide a row') }}</small></div>
    @foreach ($stats as $i => $st)
      <div style="display:grid;grid-template-columns:120px 1fr 1fr;gap:10px">
        <input class="inp" name="stats[{{ $i }}][value]" value="{{ $st['value'] ?? '' }}" placeholder="15+">
        <input class="inp" name="stats[{{ $i }}][label_en]" value="{{ $st['label_en'] ?? '' }}" placeholder="Years of practice">
        <input class="inp" name="stats[{{ $i }}][label_ar]" dir="rtl" value="{{ $st['label_ar'] ?? '' }}" placeholder="سنة خبرة">
      </div>
    @endforeach
  </div>
  <div class="card pad form">
    <div class="sec">{{ __('Banner slides') }}</div>
    <p class="mute" style="margin:0">{{ __('Wide photos (at least 1600 px, JPG/PNG/WebP, up to 6 MB) shown as the moving banner on the home page. With no slide, the built-in engineering artwork is shown. Empty texts use the default ones.') }}</p>
    @foreach ($slides as $i => $sl)
      <div class="slide-row">
        <div class="slide-img">
          @if (! empty($sl['image']))<img src="{{ route('v2.media', ['path' => $sl['image']]) }}" alt="">@else<span class="ph"><x-v2.icon name="image"/>{{ $i + 1 }}</span>@endif
        </div>
        <div class="form" style="gap:10px">
          <div class="row2">
            <label class="f">{{ __('Banner image') }}<input class="inp" type="file" name="slides[{{ $i }}][image]" accept="image/jpeg,image/png,image/webp"></label>
            @if (! empty($sl['image']))<label class="check" style="align-self:end;padding-bottom:10px"><input type="checkbox" name="slides[{{ $i }}][remove]" value="1"> {{ __('Remove this slide') }}</label>@endif
          </div>
          <div class="lang-pair">
            @foreach (['eyebrow' => __('Small heading'), 'title' => __('Title'), 'text' => __('Text')] as $k => $lbl)
              <label class="f">{{ $lbl }} (EN)<input class="inp" name="slides[{{ $i }}][{{ $k }}_en]" value="{{ $sl[$k . '_en'] ?? '' }}"></label>
              <label class="f">{{ $lbl }} (AR)<input class="inp" dir="rtl" name="slides[{{ $i }}][{{ $k }}_ar]" value="{{ $sl[$k . '_ar'] ?? '' }}"></label>
            @endforeach
          </div>
        </div>
      </div>
    @endforeach
  </div>
  <div><button class="btn primary">{{ __('Save company profile') }}</button></div>
</form>
@endsection
