@extends('v2.layouts.admin', ['title' => 'Clients'])
@section('content')
<form class="filters" method="get">
  <input class="inp" type="search" name="q" value="{{ request('q') }}" placeholder="Search name, company, email…" style="max-width:320px">
  <button class="btn"><x-v2.icon name="search"/>Search</button>
  <span class="mute" style="font-size:13px">Clients create their account at <a href="{{ route('v2.account.register') }}" target="_blank">/v2/account/register</a>.</span>
</form>
<div class="card">
  @if ($clients->isEmpty())<div class="empty">No client accounts yet.</div>@else
  <div class="tbl"><table class="t">
    <tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th class="num">Studies</th><th>Since</th></tr>
    @foreach ($clients as $c)
      <tr class="click" onclick="location.href='{{ route('v2.admin.studies', ['q' => $c->email]) }}'"><td><b>{{ $c->name }}</b></td><td>{{ $c->company ?: '–' }}</td><td>{{ $c->email }}</td><td>{{ $c->phone ?: '–' }}</td><td class="num">{{ $c->studies_count }}</td><td>{{ $c->created_at->format('d M Y') }}</td></tr>
    @endforeach
  </table></div>
  {{ $clients->links() }}
  @endif
</div>
@endsection
