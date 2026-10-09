@extends('v2.layouts.site', ['title' => __('studies.account.title')])
@push('head')<link rel="stylesheet" href="{{ asset('lib/v2/studies.css') }}?v={{ filemtime(public_path('lib/v2/studies.css')) }}">@endpush
@section('content')
<section class="page-head"><div class="wrap"><h1>{{ __('studies.account.title') }}</h1><p>{{ $client->name }}{{ $client->company ? ' · ' . $client->company : '' }}</p></div></section>
<section class="block">
  <div class="wrap sr-page" style="max-width:1080px">
    @if (session('ok'))<div class="alert ok">{{ session('ok') }}</div>@endif
    @if ($errors->any())<div class="alert bad">{{ $errors->first() }}</div>@endif
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px">
        <h2 style="margin:0">{{ __('studies.account.title') }}</h2>
        <a class="btn primary" href="{{ route('v2.studies') }}"><x-v2.icon name="plus"/>{{ __('studies.account.start') }}</a>
      </div>
      @if ($studies->isEmpty())
        <p class="mute">{{ __('studies.account.none') }}</p>
      @else
        <div class="tbl-wrap"><table class="vals">
          <tr><th>{{ __('studies.account.col_date') }}</th><th>{{ __('studies.account.col_study') }}</th><th>{{ __('studies.account.col_project') }}</th><th>{{ __('studies.account.col_status') }}</th><th>{{ __('studies.account.col_action') }}</th></tr>
          @foreach ($studies as $s)
            <tr>
              <td dir="ltr" style="text-align:start">{{ $s->created_at->format('d M Y') }}</td>
              <td><a href="{{ route('v2.studies.show', $s->code) }}" class="mono" dir="ltr">{{ $s->code }}</a> <span class="mute" dir="ltr">{{ $s->revLabel() }}</span><div class="mute" style="font-size:12.5px;white-space:normal">{{ $s->typeName() }}</div></td>
              <td style="white-space:normal">{{ $s->project_name }}</td>
              <td>{{ __("studies.status.{$s->status}") }}</td>
              <td>@if ($s->isIssued()){{ __('studies.decision.' . $s->decision) }}@if ($s->canResubmit()) · <a href="{{ route('v2.studies.form', [$s->type, 'from' => $s->code]) }}"><b>{{ __('studies.account.needs_rev') }}</b></a>@endif @else – @endif</td>
            </tr>
          @endforeach
        </table></div>
      @endif
      <form method="post" action="{{ route('v2.account.claim') }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:18px">@csrf
        <span class="mute">{{ __('studies.account.claim') }}</span>
        <input class="inp" name="code" placeholder="SXXX-XXXXXX" dir="ltr" style="max-width:200px" required>
        <button class="btn line">{{ __('studies.account.claim_btn') }}</button>
      </form>
    </div>
    <form class="card form" method="post" action="{{ route('v2.account.profile') }}">
      @csrf @method('put')
      <h3 style="margin:0">{{ __('studies.account.profile') }}</h3>
      <div class="row2">
        <label class="f">{{ __('studies.account.name') }}<input class="inp" name="name" value="{{ old('name', $client->name) }}" required></label>
        <label class="f">{{ __('studies.account.company') }}<input class="inp" name="company" value="{{ old('company', $client->company) }}"></label>
      </div>
      <div class="row2">
        <label class="f">{{ __('studies.account.email') }}<input class="inp" value="{{ $client->email }}" disabled dir="ltr"></label>
        <label class="f">{{ __('studies.account.phone') }}<input class="inp" name="phone" value="{{ old('phone', $client->phone) }}" dir="ltr"></label>
      </div>
      <div class="legend" style="font-size:14px">{{ __('studies.account.change_pw') }}</div>
      <div class="row2">
        <label class="f">{{ __('studies.account.current') }}<input class="inp" type="password" name="current" autocomplete="current-password"></label>
        <label class="f">{{ __('studies.account.password') }}<input class="inp" type="password" name="password" minlength="8" autocomplete="new-password"></label>
      </div>
      <label class="f" style="max-width:50%">{{ __('studies.account.password2') }}<input class="inp" type="password" name="password_confirmation" autocomplete="new-password"></label>
      <div style="display:flex;gap:10px"><button class="btn primary">{{ __('studies.account.save') }}</button></div>
    </form>
    <form method="post" action="{{ route('v2.account.logout') }}">@csrf<button class="btn line">{{ __('studies.account.logout') }}</button></form>
  </div>
</section>
@endsection
