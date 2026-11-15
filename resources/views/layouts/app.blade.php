<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($title) ? $title . ' | ' : '' }}{{ $gym['gym_name'] }} Magalang</title>
<meta name="description" content="{{ $gym['gym_name'] }} in Magalang, Pampanga: group classes, six coaches, personal training and Athena AI, our 24/7 chat assistant.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wdth,wght@0,62..125,300..900;1,62..125,400..700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/athena.css') }}?v=7">
<link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
</head>
<body class="route-{{ str_replace('.', '-', request()->route()?->getName() ?? 'page') }}">
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
  <div class="wrap header-row">
    <a class="brand" href="{{ route('home') }}" aria-label="{{ $gym['gym_name'] }} home">
      <span class="brand-plate" aria-hidden="true"></span>
      <span class="brand-word">Infliction</span>
      <span class="brand-place">Magalang</span>
    </a>
    <button class="menu-toggle" aria-expanded="false" aria-controls="site-nav">Menu</button>
    <nav id="site-nav" class="site-nav" aria-label="Main">
      <a href="{{ route('schedule') }}" @if(request()->routeIs('schedule')) aria-current="page" @endif>Schedule</a>
      <a href="{{ route('coaches') }}" @if(request()->routeIs('coaches')) aria-current="page" @endif>Coaches</a>
      <a href="{{ route('membership') }}" @if(request()->routeIs('membership')) aria-current="page" @endif>Membership</a>
      <a href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>Contact</a>
      <button type="button" class="nav-chat" data-open-chat>Ask Athena AI</button>
      <span class="nav-sep" aria-hidden="true"></span>
      @auth
        @if(auth()->user()->isAdmin())
          <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif>Front desk</a>
        @endif
        <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>My account</a>
        <form method="post" action="{{ route('logout') }}" class="inline-form">@csrf<button type="submit" class="link-button">Log out</button></form>
      @else
        <a href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Log in</a>
        <a class="btn btn-small" href="{{ route('register') }}">Join</a>
      @endauth
    </nav>
  </div>
</header>
<main id="main">
  @if(session('success') || session('error') || $errors->any())
    <div class="wrap flashes" role="status">
      @if(session('success'))<p class="flash flash-success">{{ session('success') }}</p>@endif
      @if(session('error'))<p class="flash flash-error">{{ session('error') }}</p>@endif
      @if($errors->any() && empty($hideErrorSummary))<p class="flash flash-error">{{ $errors->first() }}</p>@endif
    </div>
  @endif
  @yield('content')
</main>
<footer class="site-footer">
  <div class="wrap footer-grid">
    <div>
      <p class="footer-brand">{{ $gym['gym_name'] }}</p>
      <p>{{ $gym['address'] }}</p>
      <p class="muted">{{ $gym['landmark'] }}</p>
    </div>
    <div>
      <p class="footer-head">Open</p>
      <dl class="hours">
        <div><dt>Monday – Saturday</dt><dd>{{ $gym['hours_weekdays'] }}</dd></div>
        <div><dt>Sunday</dt><dd>{{ $gym['hours_sunday'] }}</dd></div>
        <div><dt>Holidays</dt><dd>{{ $gym['hours_holidays'] }}</dd></div>
      </dl>
    </div>
    <div>
      <p class="footer-head">Contact</p>
      <p><a href="tel:{{ str_replace(' ', '', $gym['phone']) }}">{{ $gym['phone'] }}</a></p>
      <p><a href="mailto:{{ $gym['email'] }}">{{ $gym['email'] }}</a></p>
      @if($gym['facebook_url'])<p><a href="{{ $gym['facebook_url'] }}" rel="noopener" target="_blank">Facebook page</a></p>@endif
    </div>
  </div>
  <div class="wrap footer-base">© {{ date('Y') }} {{ $gym['gym_name'] }} Magalang. Chat assistant: Athena AI, Strategic Conversations, Automated. Photos from Unsplash.</div>
</footer>

<button class="chat-launcher" type="button" data-open-chat aria-controls="chat-panel" aria-expanded="false">
  <img class="chat-launcher-img" src="{{ asset('images/athena-mark.png') }}" alt=""> Ask Athena AI
</button>
<section id="chat-panel" class="chat-panel" role="dialog" aria-modal="false" aria-labelledby="chat-title" hidden>
  <header class="chat-head">
    <div class="chat-id">
      <img class="athena-avatar" src="{{ asset('images/athena-mark.png') }}" alt="">
      <div>
        <p id="chat-title" class="chat-title">Athena AI</p>
        <p class="chat-sub">Answers 24/7 and books classes for you</p>
      </div>
    </div>
    <div class="chat-actions">
      <button type="button" class="icon-btn" data-chat-reset title="Start a new conversation">New chat</button>
      <button type="button" class="icon-btn" data-close-chat aria-label="Close chat">Close</button>
    </div>
  </header>
  <div class="chat-log" aria-live="polite"></div>
  <div class="chat-suggest">
    <button type="button" data-suggest="What classes are on today?">Today's classes</button>
    <button type="button" data-suggest="Which plan is best for a student who wants to lose weight?">Pick a plan for me</button>
    <button type="button" data-suggest="Book me into the next Boxing Fundamentals class">Book boxing</button>
    <button type="button" data-suggest="I want to try personal training. Can someone contact me?">Talk to the front desk</button>
  </div>
  <form class="chat-form">
    <label class="sr-only" for="chat-input">Message Athena AI</label>
    <textarea id="chat-input" rows="1" maxlength="1000" placeholder="Ask Athena AI anything" required></textarea>
    <button type="submit" class="btn">Send</button>
  </form>
</section>
<script>
window.ATHENA = {
  loggedIn: {{ auth()->check() ? 'true' : 'false' }},
  name: @json(auth()->user()?->name),
  avatar: @json(asset('images/athena-mark.png')),
  routes: { send: @json(route('chat.send')), history: @json(route('chat.history')), reset: @json(route('chat.reset')), dashboard: @json(route('dashboard')) }
};
</script>
<script src="{{ asset('js/athena.js') }}?v=9"></script>
</body>
</html>
