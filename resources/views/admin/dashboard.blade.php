@extends('layouts.app', ['title' => 'Front desk'])
@section('content')
<section class="wrap page-head">
  <h1 class="display-sm">Front desk</h1>
  <p class="lede">Leads from Athena AI and the website, plus class rosters for the next seven days.</p>
</section>
<div class="wrap">
  @include('admin._nav')
  <dl class="stats">@foreach($stats as $k => $v)<div><dt>{{ $k }}</dt><dd>{{ $v }}</dd></div>@endforeach</dl>

  <section class="panel">
    <div class="panel-head"><h2>Latest leads</h2><a href="{{ route('admin.leads') }}">All leads</a></div>
    @if($leads->isEmpty())<p class="empty">No leads yet. They appear here when Athena AI or the contact form captures someone's details.</p>@else
    <div class="table-wrap"><table>
      <thead><tr><th>When</th><th>Name</th><th>Contact</th><th>Interest</th><th>Source</th><th>Status</th></tr></thead>
      <tbody>@foreach($leads as $lead)
        <tr><td>{{ $lead->created_at->format('M j, g:i A') }}</td><td>{{ $lead->name }}</td>
        <td>{{ $lead->phone }}@if($lead->phone && $lead->email)<br>@endif{{ $lead->email }}</td>
        <td class="wrap-cell">{{ $lead->interest }}</td>
        <td><span class="badge badge-{{ $lead->source }}">{{ str_replace('_', ' ', $lead->source) }}</span></td>
        <td><span class="badge badge-{{ $lead->status }}">{{ \App\Models\Lead::STATUSES[$lead->status] ?? $lead->status }}</span></td></tr>
      @endforeach</tbody>
    </table></div>@endif
  </section>

  <section class="panel">
    <h2>Class rosters, next 7 days</h2>
    @if($roster->isEmpty())<p class="empty">No bookings in the next seven days.</p>@else
    <div class="table-wrap"><table>
      <thead><tr><th>Date</th><th>Time</th><th>Class</th><th>Coach</th><th>Member</th><th>Mobile</th></tr></thead>
      <tbody>@foreach($roster as $b)
        <tr><td>{{ $b->session_date->format('D M j') }}</td><td>{{ date('g:i A', strtotime($b->schedule->start_time)) }}</td>
        <td>{{ $b->schedule->gymClass->name }}</td><td>{{ $b->schedule->coachLabel() }}</td><td>{{ $b->user->name }}</td><td>{{ $b->user->phone }}</td></tr>
      @endforeach</tbody>
    </table></div>@endif
  </section>
</div>
@endsection
