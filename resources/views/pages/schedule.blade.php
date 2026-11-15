@extends('layouts.app', ['title' => 'Class schedule'])
@section('content')
@php
  $user = auth()->user();
  $here = route('schedule', $week === 2 ? ['week' => 2] : [], false);
@endphp
<section class="wrap page-head">
  <h1 class="display-sm">Class schedule</h1>
  <p class="lede">Book up to two weeks ahead. You can cancel any time before the class starts.</p>
  @guest
    <p class="note"><a href="{{ route('login') }}">Log in</a> or <a href="{{ route('register') }}">create an account</a> to book a spot.</p>
  @else
    @if(!$user->planActive())
      <p class="note">You don't have an active plan yet. <a href="{{ route('dashboard') }}#plan">Choose a plan</a> to start booking.</p>
    @elseif($user->plan->class_limit !== null)
      <p class="note">Your {{ $user->plan->name }} plan includes {{ $user->plan->class_limit }} classes. You've booked {{ $user->classesUsed() }} so far.</p>
    @endif
  @endguest
</section>

<div class="wrap sched-tools">
  <nav class="week-tabs" aria-label="Week">
    <a href="{{ route('schedule') }}" @if($week === 1) aria-current="page" @endif>This week</a>
    <a href="{{ route('schedule', ['week' => 2] + (request('cat') ? ['cat' => request('cat')] : [])) }}" @if($week === 2) aria-current="page" @endif>Next week</a>
  </nav>
  <div class="filters" role="group" aria-label="Filter by type">
    <button type="button" class="chip" data-filter="all" aria-pressed="true">All</button>
    @php $usedCats = collect($days)->flatten(1)->pluck('category')->unique(); @endphp
    @foreach(\App\Models\GymClass::CATEGORIES as $key => $c)
      @continue(!$usedCats->contains($key))
      <button type="button" class="chip plate-{{ $c['color'] }}" data-filter="{{ $key }}" aria-pressed="false">{{ $c['label'] }}</button>
    @endforeach
  </div>
</div>

<section class="wrap week">
  @foreach($days as $date => $list)
    @php $isToday = $date === now()->toDateString(); @endphp
    <div class="day {{ $isToday ? 'is-today' : '' }}">
      <h2 class="day-head"><span class="day-name">{{ $isToday ? 'Today' : date('l', strtotime($date)) }}</span><span class="day-date">{{ date('M j', strtotime($date)) }}</span></h2>
      @if(!$list)<p class="muted small">{{ date('N', strtotime($date)) == 7 ? 'Closed on Sundays' : 'Freestyle only' }}</p>@endif
      @foreach($list as $s)
        <article class="slot plate-{{ $s['color'] }} {{ $s['started'] ? 'is-past' : '' }}" data-cat="{{ $s['category'] }}">
          <p class="slot-time">{{ $s['time_range'] }}</p>
          <h3 class="slot-name">{{ $s['class'] }}</h3>
          <p class="slot-meta">{{ $s['coach'] }}<br>{{ $s['room'] }}</p>
          <div class="spots" aria-label="{{ $s['spots_left'] }} of {{ $s['capacity'] }} spots left"><span style="width: {{ round($s['booked'] / max(1, $s['capacity']) * 100) }}%"></span></div>
          <p class="slot-left">{{ $s['spots_left'] }} of {{ $s['capacity'] }} spots left</p>
          @if($s['started'])
            <p class="slot-status">{{ $s['ended'] ? 'Finished' : 'In progress' }}</p>
          @elseif($s['my_booking_id'])
            <form method="post" action="{{ route('bookings.destroy', $s['my_booking_id']) }}">
              @csrf @method('DELETE')
              <input type="hidden" name="back" value="{{ $here }}">
              <p class="slot-status is-booked">You're booked</p>
              <button class="link-button small" type="submit">Cancel</button>
            </form>
          @elseif($s['spots_left'] === 0)
            <p class="slot-status">Full</p>
          @elseif($user)
            <form method="post" action="{{ route('bookings.store') }}">
              @csrf
              <input type="hidden" name="schedule_id" value="{{ $s['id'] }}">
              <input type="hidden" name="date" value="{{ $date }}">
              <input type="hidden" name="back" value="{{ $here }}">
              <button class="btn btn-small btn-block" type="submit">Book</button>
            </form>
          @else
            <a class="btn btn-small btn-ghost btn-block" href="{{ route('login') }}">Log in</a>
          @endif
        </article>
      @endforeach
    </div>
  @endforeach
</section>
@endsection
