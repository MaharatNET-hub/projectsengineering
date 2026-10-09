@extends('v2.layouts.admin', ['title' => 'Users'])
@section('content')
<p class="mute" style="margin-top:0"><b>Admin</b>: everything. <b>Engineer</b>: reviews studies and submittals (no website content, settings, users or study types). A deactivated user cannot log in; their open studies are unassigned.</p>
<div class="grid g21">
  <div class="card">
    <div class="tbl"><table class="t">
      <tr><th>Name / email</th><th>Role</th><th class="num">Open</th><th class="num">Issued</th><th>Active</th><th></th></tr>
      @foreach ($users as $u)
        <tr style="{{ $u->active ? '' : 'opacity:.55' }}">
          @php $f = 'u' . $u->id; @endphp
          <td><form id="{{ $f }}" method="post" action="{{ route('v2.admin.users.update', $u) }}">@csrf @method('patch')</form><input form="{{ $f }}" class="inp" name="name" value="{{ $u->name }}" style="padding:6px 8px"><div class="mute" style="font-size:12.5px;margin-top:3px">{{ $u->email }}@if ($u->is(auth()->user())) · you @endif</div></td>
          <td><select form="{{ $f }}" class="inp" name="role" style="padding:6px 8px">@foreach (\App\Models\User::ROLES as $r)<option value="{{ $r }}" @selected($u->role === $r)>{{ ucfirst($r) }}</option>@endforeach</select></td>
          <td class="num">{{ $open[$u->id] ?? 0 }}</td><td class="num">{{ $issued[$u->id] ?? 0 }}</td>
          <td><input form="{{ $f }}" type="hidden" name="active" value="0"><label class="check"><input form="{{ $f }}" type="checkbox" name="active" value="1" @checked($u->active)></label></td>
          <td style="white-space:nowrap"><input form="{{ $f }}" class="inp" type="password" name="password" placeholder="new password (optional)" autocomplete="new-password" style="padding:6px 8px;width:190px"> <button form="{{ $f }}" class="btn">Save</button></td>
        </tr>
      @endforeach
    </table></div>
  </div>
  <form class="card pad form" method="post" action="{{ route('v2.admin.users.store') }}" style="align-self:start">
    @csrf
    <h2>Add a user</h2>
    <label class="f">Name<input class="inp" name="name" value="{{ old('name') }}" required></label>
    <label class="f">Email<input class="inp" type="email" name="email" value="{{ old('email') }}" required></label>
    <label class="f">Role<select class="inp" name="role"><option value="engineer">Engineer</option><option value="admin">Admin</option></select></label>
    <label class="f">Password <small>(10+ characters — give it to them; they can change it under My account)</small><input class="inp" type="text" name="password" required minlength="10" autocomplete="off"></label>
    <div><button class="btn primary"><x-v2.icon name="plus"/>Add user</button></div>
  </form>
</div>
@endsection
