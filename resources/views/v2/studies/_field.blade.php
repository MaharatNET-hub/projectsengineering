@php
  use App\Studies\StudyTypes as T;
  $req = ! empty($f['required']);
  $default = $f['default'] ?? null;
@endphp
<label class="f" data-f="{{ $f['key'] }}">
  <span>{{ T::t($f['label']) }}@if ($f['type'] === 'select' && ! empty($f['unit'])) <span dir="ltr">({{ $f['unit'] }})</span>@endif @if ($req)*@endif</span>
  @switch($f['type'])
    @case('select')
      <select class="inp" data-v>
        <option value="">{{ __('studies.form.choose') }}</option>
        @foreach ($f['options'] as $o)@if ((string) $o['value'] !== '')<option value="{{ $o['value'] }}" @selected($default !== null && (string) $default === (string) $o['value'])>{{ T::t($o['label']) }}</option>@endif @endforeach
      </select>
      @break
    @case('bool')
      <select class="inp" data-v>
        <option value="">{{ __('studies.form.choose') }}</option>
        <option value="1" @selected($default === true)>{{ __('studies.form.yes') }}</option>
        <option value="0" @selected($default === false)>{{ __('studies.form.no') }}</option>
      </select>
      @break
    @case('textarea')
      <textarea class="inp" data-v maxlength="{{ $f['max'] ?? 3000 }}" style="min-height:80px"></textarea>
      @break
    @case('number')
      <span class="u"><input class="inp" data-v inputmode="decimal" dir="ltr" autocomplete="off" value="{{ $default }}" @if (isset($f['min'])) data-min="{{ $f['min'] }}" @endif @if (isset($f['max'])) data-max="{{ $f['max'] }}" @endif placeholder="{{ isset($f['min'], $f['max']) ? $f['min'] . ' – ' . $f['max'] : '' }}">@if (! empty($f['unit']))<em>{{ $f['unit'] }}</em>@endif</span>
      @break
    @default
      <input class="inp" data-v maxlength="{{ $f['max'] ?? 255 }}" value="{{ $default }}" placeholder="{{ $f['placeholder'] ?? '' }}" autocomplete="off">
  @endswitch
  @if (! empty($f['help']))<small>{{ T::t($f['help']) }}</small>@endif
  <span class="flag-note" data-flag hidden></span>
  <span class="live" data-live></span>
  <span class="err" data-err></span>
</label>
