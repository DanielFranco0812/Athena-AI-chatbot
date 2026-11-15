@extends('layouts.app', ['title' => 'Members'])
@section('content')
<section class="wrap page-head"><h1 class="display-sm">Members</h1></section>
<div class="wrap">
  @include('admin._nav')
  <section class="panel">
    @if($members->isEmpty())<p class="empty">No members yet.</p>@else
    <div class="table-wrap"><table>
      <thead><tr><th>No.</th><th>Name</th><th>Email</th><th>Mobile</th><th>Plan</th><th>Valid until</th><th>Joined</th></tr></thead>
      <tbody>@foreach($members as $m)
        <tr><td>{{ $m->memberNo() }}</td><td>{{ $m->name }}</td><td>{{ $m->email }}</td><td>{{ $m->phone }}</td>
        <td>{{ $m->plan?->name ?? 'None' }}</td>
        <td class="{{ $m->planActive() ? '' : 'is-bad' }}">{{ $m->plan_expires?->format('M j, Y') }}</td>
        <td>{{ $m->created_at->format('M j, Y') }}</td></tr>
      @endforeach</tbody>
    </table></div>
    {{ $members->links() }}
    @endif
  </section>
</div>
@endsection
