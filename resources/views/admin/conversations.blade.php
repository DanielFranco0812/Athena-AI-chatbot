@extends('layouts.app', ['title' => 'Conversations'])
@section('content')
<section class="wrap page-head">
  <h1 class="display-sm">Conversations</h1>
  <p class="lede">Every Athena AI chat from the website and Facebook Messenger.</p>
</section>
<div class="wrap">
  @include('admin._nav')
  <section class="panel">
    @if($conversations->isEmpty())<p class="empty">No conversations yet.</p>@else
    <div class="table-wrap"><table>
      <thead><tr><th>Last message</th><th>Channel</th><th>Who</th><th>First question</th><th>Messages</th><th></th></tr></thead>
      <tbody>@foreach($conversations as $c)
        <tr>
          <td>{{ $c->last_message_at?->format('M j, g:i A') }}</td>
          <td><span class="badge badge-{{ $c->channel }}">{{ $c->channel === 'web' ? 'website' : 'messenger' }}</span></td>
          <td>{{ $c->user?->name ?? 'Visitor' }}</td>
          <td class="wrap-cell">{{ \Illuminate\Support\Str::limit($previews[$c->id] ?? '', 90) }}</td>
          <td>{{ $c->messages_count }}</td>
          <td><a href="{{ route('admin.conversations.show', $c) }}">Open</a></td>
        </tr>
      @endforeach</tbody>
    </table></div>
    {{ $conversations->links() }}
    @endif
  </section>
</div>
@endsection
