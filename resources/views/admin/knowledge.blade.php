@extends('layouts.app', ['title' => 'Knowledge base'])
@section('content')
<section class="wrap page-head">
  <h1 class="display-sm">Knowledge base</h1>
  <p class="lede">Athena AI answers from what you save here. Changes apply to the next message, on the website and on Messenger.</p>
</section>
<div class="wrap">
  @include('admin._nav')

  <section class="panel" id="hours">
    <h2>Gym details and hours</h2>
    <form method="post" action="{{ route('admin.settings.update') }}" class="form">
      @csrf @method('PUT')
      <div class="settings-grid">
        @foreach(\App\Models\Setting::FIELDS as $key => $label)
          <label>{{ $label }} <input name="{{ $key }}" value="{{ old($key, $settings[$key] ?? '') }}" @if($key === 'gym_name') required @endif></label>
        @endforeach
      </div>
      <div><button class="btn" type="submit">Save details and hours</button></div>
    </form>
  </section>

  <section class="panel" id="prices">
    <h2>Prices and plans</h2>
    <div class="kb-grid">
      @foreach($plans as $p)
        <div class="kb-item">
          <h3>{{ $p->name }} <span class="muted small">per {{ $p->period }}</span></h3>
          <form method="post" action="{{ route('admin.plans.update', $p) }}" class="form">
            @csrf @method('PUT')
            <div class="form-2">
              <label>Price (₱) <input type="number" step="0.01" min="0" name="price" value="{{ (float) $p->price }}" required></label>
              <label>Classes included <input type="number" min="0" name="class_limit" value="{{ $p->class_limit }}" placeholder="Empty = unlimited"></label>
            </div>
            <label>Short description <input name="description" value="{{ $p->description }}" required></label>
            <label>Perks (one per line) <textarea name="perks" rows="4" required>{{ $p->perks }}</textarea></label>
            <div><button class="btn btn-ghost btn-small" type="submit">Save {{ $p->name }}</button></div>
          </form>
        </div>
      @endforeach
    </div>
  </section>

  <section class="panel" id="faqs">
    <h2>FAQs</h2>
    <div class="kb-item" style="margin-bottom:16px">
      <h3>Add a question</h3>
      <form method="post" action="{{ route('admin.faqs.store') }}" class="form">
        @csrf
        <div class="form-2">
          <label>Question <input name="question" required></label>
          <label>Category <input name="category" value="general" required></label>
        </div>
        <label>Answer <textarea name="answer" rows="3" required></textarea></label>
        <label class="check"><input type="checkbox" name="is_active" value="1" checked> Athena AI can use this answer</label>
        <div><button class="btn btn-small" type="submit">Add FAQ</button></div>
      </form>
    </div>
    <div class="kb-grid">
      @foreach($faqs as $f)
        <div class="kb-item">
          <form method="post" action="{{ route('admin.faqs.update', $f) }}" class="form">
            @csrf @method('PUT')
            <label>Question <input name="question" value="{{ $f->question }}" required></label>
            <label>Answer <textarea name="answer" rows="3" required>{{ $f->answer }}</textarea></label>
            <div class="form-2">
              <label>Category <input name="category" value="{{ $f->category }}" required></label>
              <label class="check" style="align-self:end"><input type="checkbox" name="is_active" value="1" @checked($f->is_active)> Active</label>
            </div>
            <div class="kb-actions">
              <button class="btn btn-ghost btn-small" type="submit">Save</button>
              <button class="btn btn-danger btn-small" type="submit" form="del-faq-{{ $f->id }}">Delete</button>
            </div>
          </form>
          <form id="del-faq-{{ $f->id }}" method="post" action="{{ route('admin.faqs.destroy', $f) }}">@csrf @method('DELETE')</form>
        </div>
      @endforeach
    </div>
  </section>
</div>
@endsection
