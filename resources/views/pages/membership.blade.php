@extends('layouts.app', ['title' => 'Membership'])
@section('content')
<section class="wrap page-head">
  <h1 class="display-sm">Membership</h1>
  <p class="lede">No joining fee and no lock-in contract. Pick a plan when you sign up and pay at the front desk on your first visit.</p>
  @if($gym['promo'])<p class="note">{{ $gym['promo'] }}</p>@elseif($gym['promo_teaser'])<p class="note promo-teaser">{{ $gym['promo_teaser'] }}</p>@endif
</section>
<section class="wrap plans">
  @foreach($plans as $p)
    <article class="plan {{ $p->is_featured ? 'is-featured' : '' }}">
      @if($p->is_featured)<p class="plan-flag">Most members choose this</p>@endif
      <h2 class="plan-name">{{ $p->name }}</h2>
      <p class="plan-price">{{ $p->priceLabel() }}<span>/{{ $p->period }}</span></p>
      <p>{{ $p->description }}</p>
      <ul class="ticks">@foreach($p->perkList() as $perk)<li>{{ $perk }}</li>@endforeach</ul>
      @auth
        <a class="btn btn-block {{ $p->is_featured ? '' : 'btn-ghost' }}" href="{{ route('dashboard') }}#plan">Choose in my account</a>
      @else
        <a class="btn btn-block {{ $p->is_featured ? '' : 'btn-ghost' }}" href="{{ route('register', ['plan' => $p->id]) }}">Join with {{ $p->name }}</a>
      @endauth
    </article>
  @endforeach
</section>
<section class="wrap section rules">
  <h2>Good to know</h2>
  <dl class="rule-list">
    <div><dt>Personal training</dt><dd>{{ $gym['pt_price'] }}. Ask the front desk or any coach to book a consultation.</dd></div>
    <div><dt>Freestyle</dt><dd>{{ $gym['freestyle_note'] }}</dd></div>
    <div><dt>Hours</dt><dd>Monday to Saturday, {{ $gym['hours_weekdays'] }}. Closed on Sundays.</dd></div>
    <div><dt>Class limits</dt><dd>Limits count per membership period. Cancelled classes go back to your allowance.</dd></div>
    <div><dt>Cancelling a class</dt><dd>Cancel any time before it starts, on the website or by asking Athena AI.</dd></div>
    <div><dt>Student plan</dt><dd>Bring a valid school ID. PSAU, Holy Angel, AUF and other school IDs are accepted.</dd></div>
    <div><dt>Payment</dt><dd>{{ $gym['payment_methods'] }}.</dd></div>
    <div><dt>Facilities</dt><dd>{{ $gym['amenities'] }}.</dd></div>
  </dl>
</section>
@endsection
