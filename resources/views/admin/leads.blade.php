@extends('layouts.app', ['title' => 'Leads'])
@section('content')
<section class="wrap page-head">
  <h1 class="display-sm">Leads</h1>
  <p class="lede">People who asked to be contacted, from Athena AI (website and Messenger) and the contact form.</p>
</section>
<div class="wrap">
  @include('admin._nav')
  <div class="filters" role="group" aria-label="Filter by status" style="margin-bottom:16px">
    <a class="chip" href="{{ route('admin.leads') }}" @if(!$status) aria-pressed="true" @endif>All</a>
    @foreach(\App\Models\Lead::STATUSES as $k => $label)
      <a class="chip" href="{{ route('admin.leads', ['status' => $k]) }}" @if($status === $k) aria-pressed="true" @endif>{{ $label }}</a>
    @endforeach
  </div>
  <section class="panel">
    @if($leads->isEmpty())<p class="empty">No leads here yet.</p>@else
    <div class="table-wrap"><table>
      <thead><tr><th>When</th><th>Name</th><th>Contact</th><th>Interest</th><th>Summary</th><th>Source</th><th>Status</th><th></th></tr></thead>
      <tbody>@foreach($leads as $lead)
        <tr>
          <td>{{ $lead->created_at->format('M j, g:i A') }}</td>
          <td>{{ $lead->name }}</td>
          <td>{{ $lead->phone }}@if($lead->phone && $lead->email)<br>@endif{{ $lead->email }}</td>
          <td class="wrap-cell">{{ $lead->interest }}</td>
          <td class="wrap-cell">{{ $lead->summary ?: $lead->message }}</td>
          <td><span class="badge badge-{{ $lead->source }}">{{ str_replace('_', ' ', $lead->source) }}</span></td>
          <td>@include('admin._lead_status', ['lead' => $lead])</td>
          <td>@if($lead->conversation_id)<a href="{{ route('admin.conversations.show', $lead->conversation_id) }}">Chat</a>@endif</td>
        </tr>
      @endforeach</tbody>
    </table></div>
    {{ $leads->links() }}
    @endif
  </section>
</div>
@endsection
