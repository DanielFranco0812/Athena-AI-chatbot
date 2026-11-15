@extends('layouts.app', ['title' => 'Coaches'])
@section('content')
<section class="wrap page-head">
  <h1 class="display-sm">Meet the coaches</h1>
  <p class="lede">Certified coaches for strength, combat sports, women's fitness and rehab. Personal training is separate from membership: each coach sets their rate after a consultation where they assess you and your goals.</p>
</section>
<section class="wrap coach-list">
  @foreach($coaches->filter(fn ($c) => ! $c->isStaff() && $c->certifications) as $c)
    <article class="coach coach-full plate-{{ $c->color }}">
      <div class="coach-photo">
        @if($c->photoUrl())
          <img src="{{ $c->photoUrl() }}" alt="Coach {{ $c->name }}" loading="lazy">
        @else
          <span class="avatar avatar-lg avatar-{{ $c->color }}" aria-hidden="true">{{ $c->initials() }}</span>
        @endif
      </div>
      <div class="coach-body">
        @if($c->nickname)<p class="coach-title">“{{ $c->nickname }}”</p>@endif
        <h2>{{ $c->isStaff() ? $c->name : 'Coach ' . $c->name }}</h2>
        <p class="coach-bio">{{ $c->bio }}</p>
        @if($c->specialtyList())
          <h3 class="coach-sub">{{ $c->isStaff() ? 'Responsibilities' : 'Specialties' }}</h3>
          <ul class="spec-list">
            @foreach($c->specialtyList() as [$name, $desc])<li><strong>{{ $name }}</strong>@if($desc) <span>{{ $desc }}</span>@endif</li>@endforeach
          </ul>
        @endif
        @if($c->certificationList())
          <h3 class="coach-sub">Certifications</h3>
          <ul class="cert-list">@foreach($c->certificationList() as $cert)<li>{{ $cert }}</li>@endforeach</ul>
        @endif
        @if(!empty($teaches[$c->id]))
          <h3 class="coach-sub">Group classes</h3>
          <p class="coach-classes">@foreach($teaches[$c->id] as $cls => $times)<span><strong>{{ $cls }}</strong> {{ implode(', ', $times) }}</span>@endforeach</p>
        @endif
      </div>
    </article>
  @endforeach
</section>
@php $others = $coaches->filter(fn ($c) => ! $c->isStaff() && ! $c->certifications); @endphp
@if($others->isNotEmpty())
<section class="wrap coach-support">
  @foreach($others as $c)
    <div class="coach-mini">
      @if($c->photoUrl())<img class="coach-face" src="{{ $c->photoUrl() }}" alt="">@else<span class="avatar avatar-{{ $c->color }}" aria-hidden="true">{{ $c->initials() }}</span>@endif
      <div><strong>Coach {{ $c->name }}</strong><p class="muted">{{ $c->bio }}</p></div>
    </div>
  @endforeach
</section>
@endif

@php $staff = $coaches->filter(fn ($c) => $c->isStaff())->sortBy(fn ($c) => $c->role === 'owner' ? 0 : 1); @endphp
@if($staff->isNotEmpty())
<section class="wrap team-section">
  <h2 class="team-title">Our team</h2>
  <div class="coach-list">
  @foreach($staff as $c)
    <article class="coach coach-full plate-{{ $c->color }}">
      <div class="coach-photo">
        @if($c->photoUrl())<img src="{{ $c->photoUrl() }}" alt="{{ $c->name }}" loading="lazy">@else<span class="avatar avatar-lg avatar-{{ $c->color }}" aria-hidden="true">{{ $c->initials() }}</span>@endif
      </div>
      <div class="coach-body">
        @if($c->nickname)<p class="coach-title">“{{ $c->nickname }}”</p>@endif
        <h2>{{ $c->name }}@if($c->role === 'owner') <span class="role-tag">Owner</span>@else <span class="role-tag">{{ $c->specialty }}</span>@endif</h2>
        <p class="coach-bio">{{ $c->bio }}</p>
        @if($c->specialtyList())
          <h3 class="coach-sub">Responsibilities</h3>
          <ul class="cert-list">@foreach($c->specialtyList() as [$item])<li>{{ $item }}</li>@endforeach</ul>
        @endif
      </div>
    </article>
  @endforeach
  </div>
</section>
@endif
@endsection
