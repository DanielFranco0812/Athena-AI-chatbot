@extends('layouts.app')
@section('content')
@php
  $img = fn ($id, $w = 1000) => "https://images.unsplash.com/photo-$id?auto=format&fit=crop&w=$w&q=70";
  $minPrice = $plans->where('period', 'month')->min('price');
  $planData = $plans->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => $p->priceLabel(), 'period' => $p->period])->values();
  $classCards = [
      ['Booty Workout', 'strength', 'Mon 8:00 AM with Coach Elvie', '1541534741688-6078c6bfb5c5'],
      ['Functional Circuit', 'cardio', 'Mon & Sat 6:00 PM', '1599058917212-d750089bc07e'],
      ['CrossFit', 'strength', 'Tue 6:00 PM with Coach Mark & Alvin', '1574680096145-d05b474e2155'],
      ['Bootcamp', 'cardio', 'Wed & Fri 6:00 PM with Coach Mark & Alvin', '1583454110551-21f2fa2afe61'],
      ['Legs & Glutes', 'strength', 'Thu 6:00 PM with Coach Mark & Alvin', '1521804906057-1df8fdb718b7'],
      ['Freestyle', null, 'Open gym all day, even during classes', '1689877020200-403d8542d95d'],
  ];
  $usedCats = collect($board)->pluck('category')->merge(['strength', 'cardio'])->unique();
@endphp

<div class="wrap hero-shell">
  <section class="hero-photo" aria-labelledby="hero-title">
    <div class="hero-inner">
      <p class="eyebrow-dot">Infliction Fitness Center, Magalang, Pampanga</p>
      <h1 class="display" id="hero-title">Train hard at the foot of Mount Arayat.</h1>
      <p class="lede">Group exercises every evening, certified coaches and freestyle training from 6 AM to 11 PM. Book a class in seconds, or ask Athena AI anything, any time.</p>
      <div class="hero-actions">
        <a class="btn btn-arrow" href="{{ route('register') }}">Join now</a>
        <a class="btn btn-ghost" href="{{ route('schedule') }}">See the schedule</a>
      </div>
      <div class="hero-chips">
        <span class="hero-chip {{ $openNow ? 'is-open' : '' }}"><span class="open-dot"></span>{{ $openNow ? 'Open now' : 'Closed now' }}, {{ now()->format('N') == 7 ? $gym['hours_sunday'] : $gym['hours_weekdays'] }}</span>
        @if($next)
          <span class="hero-chip" data-countdown="{{ $next['starts'] }}">Next: <strong>{{ $next['class'] }}</strong> <span class="countdown-time">soon</span></span>
        @endif
      </div>
    </div>
  </section>
</div>

@if($gym['promo'] || $gym['promo_teaser'])
<div class="wrap"><div class="promo-strip"><span class="promo-gift" aria-hidden="true">🎁</span><p>{{ $gym['promo'] ?: $gym['promo_teaser'] }}</p>@if($gym['facebook_url'])<a class="pill-link" href="{{ $gym['facebook_url'] }}" target="_blank" rel="noopener">Follow on Facebook</a>@endif</div></div>
@endif

<section class="section wrap" aria-labelledby="classes-title">
  <div class="section-head">
    <div>
      <h2 id="classes-title">Group exercises</h2>
      <p>Coached group classes Monday to Saturday, plus freestyle training any time the gym is open.</p>
    </div>
    <a class="pill-link" href="{{ route('schedule') }}">View all classes</a>
  </div>
  <div class="class-cards">
    @foreach($classCards as [$cardTitle, $cat, $when, $photo])
      <a class="class-card {{ $cat ? 'plate-' . \App\Models\GymClass::CATEGORIES[$cat]['color'] : 'plate-lime' }}" href="{{ $cat ? route('schedule', ['cat' => $cat]) : route('membership') }}" style="--img: url('{{ $img($photo, 800) }}')">
        <span class="class-tag">{{ $cat ? \App\Models\GymClass::CATEGORIES[$cat]['label'] : 'Open gym' }}</span>
        <h3>{{ $cardTitle }}</h3>
        <p>{{ $when }}</p>
        <span class="class-go" aria-hidden="true"></span>
      </a>
    @endforeach
  </div>
</section>

<section class="section wrap" aria-labelledby="board-title">
  <div class="today-grid">
    <div class="board">
      <div class="board-head">
        <h2 id="board-title">{{ $isToday ? 'Today' : 'Tomorrow' }}, {{ date('l, F j', strtotime($boardDate)) }}</h2>
        <a href="{{ route('schedule') }}">Full week</a>
      </div>
      @if(!$board)
        <p class="board-empty">No group classes that day. The gym floor is open as usual.</p>
      @else
      <ol class="board-list">
        @foreach($board as $s)
          <li class="board-row plate-{{ $s['color'] }} {{ $s['ended'] ? 'is-past' : '' }} {{ $s['id'] === $nextId ? 'is-next' : '' }}">
            <span class="board-time">{{ date('g:i A', strtotime($s['start_time'])) }}</span>
            <span class="board-class"><strong>{{ $s['class'] }}</strong><span>{{ $s['coach'] }}, {{ $s['room'] }}</span></span>
            <span class="board-spots">
              @if($s['ended']) Done
              @elseif($s['started']) In progress
              @elseif($s['my_booking_id']) You're in
              @elseif($s['spots_left'] === 0) Full
              @else<span class="spot-meter" style="--fill: {{ round($s['booked'] / max(1, $s['capacity']) * 100) }}%"></span>{{ $s['spots_left'] }} spots
              @endif
              @if($s['id'] === $nextId)<em class="up-next">Up next</em>@endif
            </span>
          </li>
        @endforeach
      </ol>
      @endif
      <div class="legend" aria-label="Class colors">
        @foreach(\App\Models\GymClass::CATEGORIES as $k => $c)@if($usedCats->contains($k))<span class="legend-item plate-{{ $c['color'] }}">{{ $c['label'] }}</span>@endif @endforeach
      </div>
    </div>
    <div class="stats-grid" aria-label="Infliction Gym at a glance">
      <div class="stat is-lime"><span class="stat-num">6<small>days</small></span><span>a week, Monday to Saturday</span></div>
      <div class="stat"><span class="stat-num">{{ $coaches->count() }}</span><span>coaches; personal training rates set per coach</span></div>
      <div class="stat"><span class="stat-num">17<small>hrs</small></span><span>open daily, 6 AM to 11 PM</span></div>
      <div class="stat"><span class="stat-num">₱{{ number_format((float) $minPrice) }}</span><span>a month to start, no lock-in</span></div>
    </div>
  </div>
</section>

<section class="section wrap" aria-labelledby="apart-title">
  <div class="apart">
    <div class="apart-photos">
      <div class="photo" style="background-image: url('{{ $img('1689877020200-403d8542d95d', 900) }}')" role="img" aria-label="Gym floor with machines and weights"></div>
      <div class="photo" style="background-image: url('{{ $img('1583454110551-21f2fa2afe61', 600) }}')" role="img" aria-label="Person holding a dumbbell"></div>
      <div class="photo" style="background-image: url('{{ $img('1599058917212-d750089bc07e', 600) }}')" role="img" aria-label="Battle rope training"></div>
    </div>
    <div class="apart-copy">
      <h2 id="apart-title">What sets us apart?</h2>
      <p class="muted">A neighborhood gym that takes training seriously, without the big-city prices.</p>
      <ul class="apart-list">
        <li><span class="apart-icon">24</span><div><strong>Athena AI, awake 24/7</strong><span>Ask about classes at midnight, get a booking in seconds, here or on Messenger.</span></div></li>
        <li><span class="apart-icon">6</span><div><strong>Coaches who know your name</strong><span>Strength, boxing, yoga, spin and nutrition, all under one roof.</span></div></li>
        <li><span class="apart-icon">∞</span><div><strong>Freestyle all day</strong><span>Train on your own any time the gym is open, even while a group class is running.</span></div></li>
        <li><span class="apart-icon">₱0</span><div><strong>No joining fee, no lock-in</strong><span>Pay per day or per month. Students get their own plan.</span></div></li>
      </ul>
    </div>
  </div>
</section>

<section class="section wrap" aria-label="Interactive tools">
  <div class="section-head">
    <div>
      <h2>Plan your training</h2>
      <p>Two quick tools to get you started before your first visit.</p>
    </div>
  </div>
  <div class="tools-grid">
    <article class="tool-card" id="plan-finder" aria-labelledby="pf-title">
      <p class="tool-kicker">Plan finder</p>
      <h2 id="pf-title">Which plan fits you?</h2>
      <p class="muted">Three quick questions.</p>
      <div class="pf-steps">
        <fieldset class="pf-q" data-q="freq">
          <legend>How often will you train?</legend>
          <div class="seg">
            <button type="button" data-v="once">Just once or rarely</button>
            <button type="button" data-v="some">1–2 times a week</button>
            <button type="button" data-v="lots">3+ times a week</button>
          </div>
        </fieldset>
        <fieldset class="pf-q" data-q="classes">
          <legend>Do you want group classes?</legend>
          <div class="seg">
            <button type="button" data-v="none">Gym floor is enough</button>
            <button type="button" data-v="few">A few a month</button>
            <button type="button" data-v="many">As many as I can</button>
          </div>
        </fieldset>
        <fieldset class="pf-q" data-q="student">
          <legend>Are you a student with a school ID?</legend>
          <div class="seg">
            <button type="button" data-v="yes">Yes</button>
            <button type="button" data-v="no">No</button>
          </div>
        </fieldset>
      </div>
      <div class="pf-result" aria-live="polite" hidden>
        <p class="pf-label">Your match</p>
        <p class="pf-name"></p>
        <p class="pf-price"></p>
        <p class="pf-why"></p>
        <div class="pf-actions">
          <a class="btn pf-join" href="{{ route('register') }}">Join with this plan</a>
          <button type="button" class="btn btn-ghost pf-ask" data-open-chat>Ask Athena AI</button>
        </div>
      </div>
    </article>

    <article class="tool-card" id="plate-calc" aria-labelledby="pc-title">
      <p class="tool-kicker">Plate calculator</p>
      <h2 id="pc-title">What do I load on the bar?</h2>
      <div class="pc-controls">
        <label class="pc-target">Target weight
          <span class="pc-input">
            <button type="button" data-step="-2.5" aria-label="Minus 2.5 kilograms">−</button>
            <input type="number" id="pc-weight" min="20" max="300" step="2.5" value="60" inputmode="decimal">
            <span class="pc-unit">kg</span>
            <button type="button" data-step="2.5" aria-label="Plus 2.5 kilograms">+</button>
          </span>
        </label>
        <div class="seg seg-small" role="group" aria-label="Bar weight">
          <button type="button" data-bar="20" aria-pressed="true">20 kg bar</button>
          <button type="button" data-bar="15" aria-pressed="false">15 kg bar</button>
        </div>
      </div>
      <input type="range" id="pc-range" min="20" max="200" step="2.5" value="60" aria-label="Target weight slider">
      <svg class="pc-bar" viewBox="0 0 560 150" role="img" aria-labelledby="pc-summary"></svg>
      <p id="pc-summary" class="pc-summary"></p>
    </article>
  </div>
</section>

<section class="section wrap">
  <div class="athena-band">
    <div class="athena-grid">
      <div class="athena-copy">
        <img src="{{ asset('images/athena-mark.png') }}" alt="Athena AI logo" class="athena-logo">
        <h2>Questions at 11 PM? Athena AI answers.</h2>
        <p>Our assistant knows the schedule, prices and house rules, here and on our Facebook page. It checks open spots, books your class, gives workout tips, or passes your number to the front desk.</p>
      </div>
      <div class="ask-chips" aria-label="Try asking Athena AI">
        <p class="ask-title">Tap a question to ask</p>
        <button type="button" data-ask="Anong klase ang meron mamayang gabi?">Anong klase ang meron mamayang gabi?</button>
        <button type="button" data-ask="May Christmas promo ba kayo?">May Christmas promo ba kayo?</button>
        <button type="button" data-ask="I'm a beginner and want to lose weight. Where do I start?">I'm a beginner and want to lose weight. Where do I start?</button>
        <button type="button" data-ask="Give me a quick dumbbell workout for arms">Give me a quick dumbbell workout for arms</button>
        <button type="button" data-ask="Which coach is best if I want to learn MMA?">Which coach is best if I want to learn MMA?</button>
      </div>
    </div>
  </div>
</section>

<section class="section wrap">
  <div class="section-head">
    <div><h2>Meet the coaches</h2><p>Certified coaches for strength, combat, women's fitness and rehab.</p></div>
    <a class="pill-link" href="{{ route('coaches') }}">All coaches</a>
  </div>
  <ul class="coach-cards">
    @foreach($coaches as $c)
      <li class="coach-card plate-{{ $c->color }}">
        @if($c->photoUrl())<img class="coach-face" src="{{ $c->photoUrl() }}" alt="" loading="lazy">@else<span class="avatar avatar-{{ $c->color }}" aria-hidden="true">{{ $c->initials() }}</span>@endif
        <strong>Coach {{ $c->name }}</strong>
        @if($c->nickname)<span class="coach-years">“{{ $c->nickname }}”</span>@endif
        <span class="muted">{{ $c->specialty }}</span>
      </li>
    @endforeach
  </ul>
</section>

<section class="section wrap">
  <div class="section-head">
    <div><h2>Membership</h2><p>No joining fee. Pay at the front desk on your first visit.</p></div>
    <a class="pill-link" href="{{ route('membership') }}">Compare plans</a>
  </div>
  @if($gym['promo'])<p class="note">{{ $gym['promo'] }}</p>@endif
  <div class="plan-row">
    @foreach($plans as $p)
      <a class="plan-mini {{ $p->is_featured ? 'is-featured' : '' }}" href="{{ route('register', ['plan' => $p->id]) }}">
        @if($p->is_featured)<span class="plan-badge">Most popular</span>@endif
        <span class="plan-name">{{ $p->name }}</span>
        <span class="plan-price">{{ $p->priceLabel() }}<span>/{{ $p->period }}</span></span>
        <span class="muted">{{ $p->description }}</span>
      </a>
    @endforeach
  </div>
</section>

<section class="section wrap">
  <div class="cta-band" style="--img: url('{{ $img('1574680096145-d05b474e2155', 1600) }}')">
    <div>
      <h2>Start your training this week.</h2>
      <p>Create an account in a minute, pick a plan, and book your first class tonight.</p>
    </div>
    <a class="cta-circle" href="{{ route('register') }}">Join<br>now</a>
  </div>
</section>

<section class="section wrap visit">
  <div>
    <h2>Find us</h2>
    <p class="lede-sm">{{ $gym['address'] }}</p>
    <p class="muted">{{ $gym['landmark'] }}. {{ $gym['parking'] }}.</p>
    <p class="muted">Pay with {{ $gym['payment_methods'] }}.</p>
    @if($gym['facebook_url'])<a class="pill-link" href="{{ $gym['facebook_url'] }}" target="_blank" rel="noopener">Message us on Facebook</a>@endif
  </div>
  <iframe class="map" title="Map of Magalang, Pampanga" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
    src="https://www.google.com/maps?q=Magalang,+Pampanga&z=15&output=embed"></iframe>
</section>

<script>window.GYM_PLANS = @json($planData); window.REGISTER_URL = @json(route('register'));</script>
@endsection
