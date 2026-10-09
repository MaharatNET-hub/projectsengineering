@extends('v2.layouts.admin', ['title' => 'My account'])
@section('content')
<div class="grid g2">
  <div class="card pad">
    <h2>{{ auth()->user()->name }}</h2>
    <dl class="kv"><dt>Email</dt><dd>{{ auth()->user()->email }}</dd><dt>Role</dt><dd>{{ ucfirst(auth()->user()->role) }}</dd></dl>
  </div>
  <form class="card pad form" method="post" action="{{ route('v2.admin.account.password') }}">
    @csrf @method('put')
    <h2>Your password</h2>
    <label class="f">Current password<input class="inp" type="password" name="current" required autocomplete="current-password"></label>
    <div class="row2"><label class="f">New password <small>(10+ characters)</small><input class="inp" type="password" name="password" required minlength="10" autocomplete="new-password"></label>
    <label class="f">Repeat<input class="inp" type="password" name="password_confirmation" required autocomplete="new-password"></label></div>
    <div><button class="btn primary">Change password</button></div>
  </form>
</div>
@endsection
