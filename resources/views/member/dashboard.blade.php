@extends('layouts.app', ['title' => 'My account'])
@section('content')
@php $active = $user->planActive(); @endphp
<section class="wrap page-head">
  <h1 class="display-sm">Hi, {{ $user->firstName() }}</h1>
  <p class="lede">Your classes and membership in one place.</p>
</section>
<div class="wrap dash">
  <section class="panel" aria-labelledby="up-title">
    <div class="panel-head"><h2 id="up-title">Upcoming classes</h2><a href="{{ route('schedule') }}">Book another</a></div>
    @if($upcoming->isEmpty())
      <p class="empty">No classes booked. <a href="{{ route('schedule') }}">Pick one from the schedule</a> or ask Athena AI to book one for you.</p>
    @else
      <ul class="booking-list">
        @foreach($upcoming as $b)
          <li class="booking plate-{{ $b->schedule->gymClass->color() }}">
            <div>
              <p class="booking-when">{{ $b->session_date->isToday() ? 'Today' : $b->session_date->format('D, M j') }}, {{ date('g:i A', strtotime($b->schedule->start_time)) }}</p>
              <p class="booking-what"><strong>{{ $b->schedule->gymClass->name }}</strong> with {{ $b->schedule->coachLabel() }}, {{ $b->schedule->room }}</p>
            </div>
            @if($b->startsAt() > time())
              <form method="post" action="{{ route('bookings.destroy', $b->id) }}">
                @csrf @method('DELETE')
                <input type="hidden" name="back" value="{{ route('dashboard', [], false) }}">
                <button class="link-button" type="submit">Cancel</button>
              </form>
            @else<span class="muted small">In progress</span>@endif
          </li>
        @endforeach
      </ul>
    @endif
    @if($past->isNotEmpty())
      <h3 class="sub-head">Recent classes</h3>
      <ul class="booking-list is-past">
        @foreach($past as $b)
          <li class="booking plate-{{ $b->schedule->gymClass->color() }}"><div>
            <p class="booking-when">{{ $b->session_date->format('D, M j') }}</p>
            <p class="booking-what">{{ $b->schedule->gymClass->name }} with {{ $b->schedule->coachLabel() }}</p>
          </div></li>
        @endforeach
      </ul>
    @endif
  </section>

  <section class="panel" id="plan" aria-labelledby="plan-title">
    <h2 id="plan-title">Membership</h2>
    @if($user->plan)
      <div class="member-card {{ $active ? '' : 'is-expired' }}">
        <p class="member-plan">{{ $user->plan->name }}</p>
        <p>{{ $active ? 'Active until' : 'Expired on' }} {{ $user->plan_expires->format('F j, Y') }}</p>
        <p class="muted">{{ $user->plan->class_limit === null ? 'Unlimited classes' : $user->classesUsed() . ' of ' . $user->plan->class_limit . ' classes booked this period' }}</p>
        <p class="member-id">Member no. {{ $user->memberNo() }}</p>
      </div>
    @else
      <p class="empty">You don't have a plan yet. Choose one below to start booking classes.</p>
    @endif
    <form method="post" action="{{ route('dashboard.plan') }}" class="form">
      @csrf
      <label>{{ $active ? 'Switch or renew plan (starts today)' : 'Choose a plan (starts today)' }}
        <select name="plan_id">
          @foreach($plans as $p)<option value="{{ $p->id }}" @selected($p->id === $user->plan_id)>{{ $p->name }}, {{ $p->priceLabel() }}/{{ $p->period }}</option>@endforeach
        </select>
      </label>
      <button class="btn btn-ghost" type="submit">Start plan</button>
    </form>
  </section>

  <section class="panel" id="profile" aria-labelledby="profile-title">
    <h2 id="profile-title">Profile</h2>
    <form method="post" action="{{ route('dashboard.profile') }}" class="form">
      @csrf
      <label>Full name <input name="name" value="{{ old('name', $user->name) }}" required></label>
      <label>Mobile number <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}"></label>
      <p class="muted small">Email: {{ $user->email }}</p>
      <button class="btn btn-ghost" type="submit">Save profile</button>
    </form>
    <form method="post" action="{{ route('dashboard.password') }}" class="form form-sep">
      @csrf
      <label>Current password <input type="password" name="current_password" autocomplete="current-password" required></label>
      <label>New password <input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
      <button class="btn btn-ghost" type="submit">Change password</button>
    </form>
  </section>
</div>
@endsection
