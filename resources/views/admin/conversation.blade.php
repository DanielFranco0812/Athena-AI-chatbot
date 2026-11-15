@extends('layouts.app', ['title' => 'Conversation'])
@section('content')
<section class="wrap page-head">
  <h1 class="display-sm">Conversation</h1>
  <p class="lede">{{ $conversation->channel === 'web' ? 'Website chat' : 'Facebook Messenger' }} with {{ $conversation->user?->name ?? 'a visitor' }}, started {{ $conversation->created_at->format('M j, Y g:i A') }}.</p>
  @foreach($conversation->leads as $lead)
    <p class="note">Saved as a lead: <strong>{{ $lead->name }}</strong>, {{ $lead->phone ?: $lead->email }}. {{ $lead->interest }}</p>
  @endforeach
</section>
<div class="wrap">
  @include('admin._nav')
  <section class="panel">
    <div class="transcript">
      @foreach($messages as $m)
        <div class="msg {{ $m->role === 'user' ? 'msg-user' : 'msg-bot' }}" title="{{ $m->created_at->format('M j, g:i A') }}">{!! nl2br(e($m->content)) !!}</div>
      @endforeach
    </div>
    <p style="margin-top:16px"><a href="{{ route('admin.conversations') }}">Back to conversations</a></p>
  </section>
</div>
@endsection
