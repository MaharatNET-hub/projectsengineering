@php use App\V2\Site; $ar = Site::ar(); @endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $ar ? 'rtl' : 'ltr' }}" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>{{ __('Sign in') }} · {{ Site::name() }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('lib/v2/admin.css') }}?v={{ filemtime(public_path('lib/v2/admin.css')) }}"></head>
<body>
<div class="auth">
  <section class="art">
    <a class="logo" href="{{ route('v2.home') }}"><span class="mark">{{ Site::initial() }}</span><span><b>{{ Site::name() }}</b><small>{{ __('Control centre') }}</small></span></a>
    <div class="pitch">
      <span class="tag"><x-v2.icon name="route"/>{{ __('Engineering review workspace') }}</span>
      <h1>{{ __('Keep every study moving.') }}</h1>
      <p>{{ __('Receive study requests, check them against the criteria, assign engineers and issue reviews — from one focused workspace.') }}</p>
    </div>
    <div class="sec-note">{{ __('Secure access for authorised team members only') }}</div>
  </section>
  <section class="panel">
    <a class="lang" href="{{ Site::switchUrl() }}" hreflang="{{ $ar ? 'en' : 'ar' }}"><x-v2.icon name="globe"/>{{ $ar ? 'English' : 'العربية' }}</a>
    <div class="box">
      <h2>{{ __('Welcome back') }}</h2>
      <p>{{ __('Sign in with your administration account to continue.') }}</p>
      <form method="post" action="{{ route('v2.admin.login.post') }}">
        @csrf
        @if ($errors->any())<div class="alert bad">{{ $errors->first() }}</div>@endif
        <label class="fld">{{ __('Email') }}
          <span class="ctl"><span class="ad"><x-v2.icon name="mail"/></span><input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" dir="ltr" placeholder="name@company.com"></span>
        </label>
        <label class="fld">{{ __('Password') }}
          <span class="ctl"><span class="ad"><x-v2.icon name="lock"/></span><input type="password" name="password" id="pw" required autocomplete="current-password" dir="ltr" placeholder="••••••••">
            <button type="button" id="pwBtn" aria-label="{{ __('Show password') }}"><x-v2.icon name="eyeoff" class="i off"/><x-v2.icon name="eye" class="i on"/></button></span>
        </label>
        <label class="remember"><input type="checkbox" name="remember" value="1"> {{ __('Keep me signed in') }}</label>
        <button class="go">{{ __('Login') }} <x-v2.icon name="arrow"/></button>
      </form>
      <p class="hint">{!! __('First run: the admin account comes from :a / :b. If no password was set, a random one is in :c and the server log.', ['a' => '<code>V2_ADMIN_EMAIL</code>', 'b' => '<code>V2_ADMIN_PASSWORD</code>', 'c' => '<code>storage/app/v2/ADMIN-PASSWORD.txt</code>']) !!}</p>
      <a class="back" href="{{ route('v2.home') }}">{{ $ar ? '→' : '←' }} {{ __('Back to the website') }}</a>
    </div>
  </section>
</div>
<script>
document.getElementById('pwBtn').addEventListener('click', function () {
  var p = document.getElementById('pw'), show = p.type === 'password';
  p.type = show ? 'text' : 'password'; this.classList.toggle('show', show);
});
</script>
</body></html>
