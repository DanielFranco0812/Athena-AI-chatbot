<nav class="admin-nav" aria-label="Front desk">
  <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>Overview</a>
  <a href="{{ route('admin.leads') }}" @if(request()->routeIs('admin.leads')) aria-current="page" @endif>Leads</a>
  <a href="{{ route('admin.conversations') }}" @if(request()->routeIs('admin.conversations*')) aria-current="page" @endif>Conversations</a>
  <a href="{{ route('admin.knowledge') }}" @if(request()->routeIs('admin.knowledge')) aria-current="page" @endif>Knowledge base</a>
  <a href="{{ route('admin.members') }}" @if(request()->routeIs('admin.members')) aria-current="page" @endif>Members</a>
</nav>
