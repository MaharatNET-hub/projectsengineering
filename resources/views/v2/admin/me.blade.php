@extends('v2.layouts.admin', ['title' => __('My account')])
@section('content')
<div class="grid g2">
  <div class="card pad">
    <h2>{{ auth()->user()->name }}</h2>
    <dl class="kv"><dt>{{ __('Email') }}</dt><dd>{{ auth()->user()->email }}</dd><dt>{{ __('Role') }}</dt><dd>{{ __(ucfirst(auth()->user()->role)) }}</dd></dl>
  </div>
  <form class="card pad form" method="post" action="{{ route('v2.admin.account.password') }}">
    @csrf @method('put')
    <h2>{{ __('Your password') }}</h2>
    <label class="f">{{ __('Current password') }}<input class="inp" type="password" name="current" required autocomplete="current-password"></label>
    <div class="row2"><label class="f">{{ __('New password') }} <small>({{ __('10+ characters') }})</small><input class="inp" type="password" name="password" required minlength="10" autocomplete="new-password"></label>
    <label class="f">{{ __('Repeat') }}<input class="inp" type="password" name="password_confirmation" required autocomplete="new-password"></label></div>
    <div><button class="btn primary">{{ __('Change password') }}</button></div>
  </form>
</div>
@endsection
