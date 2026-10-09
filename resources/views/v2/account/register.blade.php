@extends('v2.layouts.site', ['title' => __('studies.account.register')])
@section('content')
<section class="page-head"><div class="wrap"><h1>{{ __('studies.account.register') }}</h1><p>{{ __('studies.account.why') }}</p></div></section>
<section class="block"><div class="wrap" style="max-width:640px">
  <form class="card form" method="post" action="{{ route('v2.account.register.post') }}">
    @csrf
    @if ($errors->any())<div class="alert bad">{{ $errors->first() }}</div>@endif
    <div class="row2">
      <label class="f">{{ __('studies.account.name') }} *<input class="inp" name="name" value="{{ old('name') }}" required maxlength="120"></label>
      <label class="f">{{ __('studies.account.company') }}<input class="inp" name="company" value="{{ old('company') }}" maxlength="160"></label>
    </div>
    <div class="row2">
      <label class="f">{{ __('studies.account.email') }} *<input class="inp" type="email" name="email" value="{{ old('email') }}" required dir="ltr" autocomplete="email"></label>
      <label class="f">{{ __('studies.account.phone') }}<input class="inp" name="phone" value="{{ old('phone') }}" dir="ltr" maxlength="40"></label>
    </div>
    <div class="row2">
      <label class="f">{{ __('studies.account.password') }} * <small class="mute">(8+)</small><input class="inp" type="password" name="password" required minlength="8" autocomplete="new-password"></label>
      <label class="f">{{ __('studies.account.password2') }} *<input class="inp" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
    </div>
    <div><button class="btn primary">{{ __('studies.account.register') }}</button></div>
    <p class="mute" style="margin:0">{{ __('studies.account.have') }} <a href="{{ route('v2.account.login') }}">{{ __('studies.account.login') }}</a></p>
  </form>
</div></section>
@endsection
