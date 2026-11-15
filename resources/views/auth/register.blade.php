@extends('layouts.app', ['title' => 'Create an account', 'hideErrorSummary' => true])
@section('content')
@php $sel = (int) old('plan_id', $selectedPlan); @endphp
<section class="wrap auth">
  <div class="auth-card auth-wide">
    <h1 class="display-sm">Create an account</h1>
    <p class="muted">Takes a minute. You can book classes right after.</p>
    <form method="post" action="{{ route('register') }}" class="form" novalidate>
      @csrf
      <label>Full name <input name="name" value="{{ old('name') }}" autocomplete="name" required @error('name') aria-invalid="true" @enderror>
        @error('name')<span class="field-error">{{ $message }}</span>@enderror</label>
      <div class="form-2">
        <label>Email <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required @error('email') aria-invalid="true" @enderror>
          @error('email')<span class="field-error">{{ $message }}</span>@enderror</label>
        <label>Mobile number <span class="muted">(optional)</span> <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" placeholder="0917 123 4567">
          @error('phone')<span class="field-error">{{ $message }}</span>@enderror</label>
      </div>
      <div class="form-2">
        <label>Password <input type="password" name="password" autocomplete="new-password" minlength="8" required @error('password') aria-invalid="true" @enderror>
          @error('password')<span class="field-error">{{ $message }}</span>@enderror</label>
        <label>Confirm password <input type="password" name="password_confirmation" autocomplete="new-password" required></label>
      </div>
      <fieldset class="plan-pick">
        <legend>Plan</legend>
        @foreach($plans as $p)
          <label class="plan-option">
            <input type="radio" name="plan_id" value="{{ $p->id }}" @checked($sel === $p->id)>
            <span><strong>{{ $p->name }}</strong> {{ $p->priceLabel() }}/{{ $p->period }}<br><span class="muted small">{{ $p->description }}</span></span>
          </label>
        @endforeach
        @error('plan_id')<span class="field-error">{{ $message }}</span>@enderror
      </fieldset>
      <button class="btn btn-block" type="submit">Create account</button>
    </form>
    <p class="muted">Already a member? <a href="{{ route('login') }}">Log in</a></p>
  </div>
</section>
@endsection
