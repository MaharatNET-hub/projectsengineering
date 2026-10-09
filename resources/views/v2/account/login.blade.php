@extends('v2.layouts.site', ['title' => __('studies.account.login')])
@section('content')
<section class="page-head"><div class="wrap"><h1>{{ __('studies.account.login') }}</h1><p>{{ __('studies.account.why') }}</p></div></section>
<section class="block"><div class="wrap" style="max-width:520px">
  <form class="card form" method="post" action="{{ route('v2.account.login.post') }}">
    @csrf
    @if ($errors->any())<div class="alert bad">{{ $errors->first() }}</div>@endif
    <label class="f">{{ __('studies.account.email') }}<input class="inp" type="email" name="email" value="{{ old('email') }}" required dir="ltr" autocomplete="email"></label>
    <label class="f">{{ __('studies.account.password') }}<input class="inp" type="password" name="password" required autocomplete="current-password"></label>
    <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="remember" value="1"> {{ __('studies.account.remember') }}</label>
    <div><button class="btn primary">{{ __('studies.account.login') }}</button></div>
    <p class="mute" style="margin:0">{{ __('studies.account.new') }} <a href="{{ route('v2.account.register') }}">{{ __('studies.account.register') }}</a></p>
  </form>
</div></section>
@endsection
