@extends('v2.layouts.admin', ['title' => __('Settings')])
@section('content')
<div class="grid g2" style="max-width:1100px">
  <form class="card pad form" method="post" action="{{ route('v2.admin.settings.save') }}">
    @csrf @method('put')
    <h2>{{ __('Submittal review') }}</h2>
    <label class="check"><input type="checkbox" name="hide_default" value="1" @checked($rv['hide_default'])> {{ __('Hide client details by default in new reviews') }} <span class="mute" style="font-weight:400">({{ __('client disclosure') }})</span></label>
    <label class="check"><input type="checkbox" name="auto_analyse" value="1" @checked($rv['auto_analyse'])> {{ __('Analyse automatically right after the client uploads') }} <span class="mute" style="font-weight:400">({{ __('off: the engineer starts the check') }})</span></label>
    <label class="f">{{ __('Notify this address of new submissions') }} <small>({{ __('blank = the company email') }})</small><input class="inp" type="email" name="notify_email" value="{{ $rv['notify_email'] }}"></label>
    <div><button class="btn primary">{{ __('Save') }}</button></div>
  </form>
  <div class="card pad form">
    <h2>{{ __('Email') }}</h2>
    <dl class="kv"><dt>{{ __('Mailer') }}</dt><dd class="mono">{{ $mailer }}</dd><dt>{{ __('From') }}</dt><dd class="mono">{{ $from }}</dd></dl>
    @if (in_array($mailer, ['log', 'array'], true))
      <div class="alert bad" style="margin:0">{!! __('Emails are not sent yet: they are written to :file. Set :vars in :env (or the host\'s environment).', ['file' => '<code>storage/logs/mail.log</code>', 'vars' => '<code>MAIL_MAILER=smtp</code>, <code>MAIL_HOST</code>, <code>MAIL_PORT</code>, <code>MAIL_USERNAME</code>, <code>MAIL_PASSWORD</code> ' . e(__('and')) . ' <code>MAIL_FROM_ADDRESS</code>', 'env' => '<code>.env</code>']) !!}</div>
    @endif
    <form method="post" action="{{ route('v2.admin.settings.test-mail') }}">@csrf<button class="btn"><x-v2.icon name="send"/>{{ __('Send a test email to :email', ['email' => auth()->user()->email]) }}</button></form>
  </div>
  <form class="card pad form" method="post" action="{{ route('v2.admin.account.password') }}">
    @csrf @method('put')
    <h2>{{ __('Your password') }}</h2>
    <label class="f">{{ __('Current password') }}<input class="inp" type="password" name="current" required autocomplete="current-password"></label>
    <div class="row2"><label class="f">{{ __('New password') }} <small>({{ __('10+ characters') }})</small><input class="inp" type="password" name="password" required minlength="10" autocomplete="new-password"></label>
    <label class="f">{{ __('Repeat') }}<input class="inp" type="password" name="password_confirmation" required autocomplete="new-password"></label></div>
    <div><button class="btn primary">{{ __('Change password') }}</button></div>
  </form>
  <div class="card pad">
    <h2>{{ __('Versions') }}</h2>
    <p class="mute" style="margin:0 0 10px">{{ __('v1 (the original demo) is still at') }} <a href="{{ url('/') }}" target="_blank">{{ url('/') }}</a> {{ __('and is not affected by anything here. v2 is the website at') }} <a href="{{ route('v2.home') }}" target="_blank">{{ route('v2.home') }}</a> {{ __('and this dashboard.') }}</p>
    <dl class="kv"><dt>{{ __('Submissions folder') }}</dt><dd class="mono" style="font-size:12px">storage/app/v2/submissions</dd><dt>{{ __('Database') }}</dt><dd class="mono">{{ config('database.default') }}</dd></dl>
  </div>
</div>
@endsection
