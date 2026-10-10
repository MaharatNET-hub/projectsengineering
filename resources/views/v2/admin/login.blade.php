@php use App\V2\Site; $ar = app()->getLocale() === 'ar'; @endphp
<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}" data-theme="dark">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>{{ __('Sign in') }} · {{ Site::name() }}</title>
<script>try { document.documentElement.dataset.theme = localStorage.getItem('admin-theme') || 'dark'; } catch (e) {}</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('lib/v2/admin.css') }}?v={{ filemtime(public_path('lib/v2/admin.css')) }}"></head>
<body>
<div class="auth">
  <section class="art">
    <span class="ring"></span><span class="ring r2"></span>
    <div class="logo"><span class="mark">{{ Site::initial() }}</span><div><b>{{ Site::name() }}</b><small>{{ __('Control center') }}</small></div></div>
    <div>
      <span class="eyebrow"><x-v2.icon name="layers"/>{{ __('Engineering review workspace') }}</span>
      <h1>{{ __('Every study reviewed, on time.') }}</h1>
      <p>{{ __('Follow the requests of your categories, run the check against the specification, write the comments and issue the signed review — from one focused workspace.') }}</p>
    </div>
    <div class="foot">{{ __('Secure access for authorised team members only') }}</div>
  </section>
  <section class="side" style="position:relative">
    <div class="tops">
      <a class="tool" href="{{ route('v2.admin.lang', $ar ? 'en' : 'ar') }}"><x-v2.icon name="globe"/>{{ $ar ? 'English' : 'العربية' }}</a>
      <button class="tool" type="button" id="themeBtn" aria-label="{{ __('Light / dark') }}"><x-v2.icon name="sun" class="i t-sun"/><x-v2.icon name="moon" class="i t-moon"/></button>
    </div>
    <form method="post" action="{{ route('v2.admin.login.post') }}">
      @csrf
      <h2>{{ __('Welcome back') }}</h2>
      <p class="sub">{{ __('Sign in with your office account to continue.') }}</p>
      @if ($errors->any())<div class="alert bad" style="margin:0">{{ __($errors->first()) }}</div>@endif
      <label class="f">{{ __('Email') }}
        <span class="field"><span class="ic"><x-v2.icon name="mail"/></span><input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" dir="ltr"></span>
      </label>
      <label class="f">{{ __('Password') }}
        <span class="field"><span class="ic"><x-v2.icon name="lock"/></span><input type="password" name="password" id="pw" required autocomplete="current-password" dir="ltr"><button class="eye" type="button" id="eye" aria-label="{{ __('Show password') }}"><x-v2.icon name="eyeoff"/></button></span>
      </label>
      <label class="check"><input type="checkbox" name="remember" value="1"> {{ __('Keep me signed in') }}</label>
      <button class="go">{{ __('Sign in') }} <x-v2.icon name="arrow" class="i" style="{{ $ar ? 'transform:scaleX(-1)' : '' }}"/></button>
      <p style="font-size:13px;margin:6px 0 0"><a href="{{ route('v2.home') }}">{{ $ar ? '→' : '←' }} {{ __('Back to the website') }}</a></p>
    </form>
  </section>
</div>
<style>[data-theme="dark"] .t-sun { display: none; } [data-theme="light"] .t-moon { display: none; }</style>
<script>
document.getElementById('eye').addEventListener('click', () => { const p = document.getElementById('pw'); p.type = p.type === 'password' ? 'text' : 'password'; });
document.getElementById('themeBtn').addEventListener('click', () => { const t = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'; document.documentElement.dataset.theme = t; try { localStorage.setItem('admin-theme', t); } catch (e) {} });
</script>
</body></html>
