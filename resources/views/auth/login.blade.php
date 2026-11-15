@extends('layouts.app', ['title' => 'Log in', 'hideErrorSummary' => true])
@section('content')
<section class="wrap auth">
  <div class="auth-card">
    <h1 class="display-sm">Log in</h1>
    @error('email')<p class="flash flash-error" role="alert">{{ $message }}</p>@enderror
    <form method="post" action="{{ route('login') }}" class="form" novalidate>
      @csrf
      <label>Email <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label>
      <label>Password <input type="password" name="password" autocomplete="current-password" required></label>
      <label class="check"><input type="checkbox" name="remember" value="1"> Keep me logged in</label>
      <button class="btn btn-block" type="submit">Log in</button>
    </form>
    <p class="muted">New to Infliction? <a href="{{ route('register') }}">Create an account</a></p>
  </div>
</section>
@endsection
