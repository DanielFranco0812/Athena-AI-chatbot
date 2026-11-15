@extends('layouts.app', ['title' => 'Contact', 'hideErrorSummary' => true])
@section('content')
<section class="wrap page-head">
  <h1 class="display-sm">Contact us</h1>
  <p class="lede">Send a question and the front desk replies within the day. For an instant answer, ask Athena AI.</p>
</section>
<div class="wrap contact-grid">
  <section class="panel">
    <h2>Send an inquiry</h2>
    <form method="post" action="{{ route('contact.store') }}" class="form" novalidate>
      @csrf
      <label>Name <input name="name" value="{{ old('name') }}" autocomplete="name" required @error('name') aria-invalid="true" @enderror>
        @error('name')<span class="field-error">{{ $message }}</span>@enderror</label>
      <div class="form-2">
        <label>Mobile number <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" placeholder="0917 123 4567">
          @error('phone')<span class="field-error">{{ $message }}</span>@enderror</label>
        <label>Email <input type="email" name="email" value="{{ old('email') }}" autocomplete="email">
          @error('email')<span class="field-error">{{ $message }}</span>@enderror</label>
      </div>
      <label>Interested in
        <select name="interest">
          <option value="">Just a question</option>
          @foreach($plans as $p)<option @selected(old('interest') === $p->name . ' plan')>{{ $p->name }} plan</option>@endforeach
          <option @selected(old('interest') === 'Personal training')>Personal training</option>
          <option @selected(old('interest') === 'Group classes')>Group classes</option>
        </select>
      </label>
      <label>Message <textarea name="message" required @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
        @error('message')<span class="field-error">{{ $message }}</span>@enderror</label>
      <button class="btn" type="submit">Send inquiry</button>
    </form>
    <p class="muted small">Or call {{ $gym['phone'] }}, email {{ $gym['email'] }}, or message our <a href="{{ $gym['facebook_url'] }}" target="_blank" rel="noopener">Facebook page</a>.</p>
  </section>
  <section>
    <h2>Common questions</h2>
    <div class="faq-list">
      @foreach($faqs as $f)
        <details><summary>{{ $f->question }}</summary><p>{{ $f->answer }}</p></details>
      @endforeach
    </div>
  </section>
</div>
@endsection
