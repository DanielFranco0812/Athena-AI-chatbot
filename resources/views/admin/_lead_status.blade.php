<form method="post" action="{{ route('admin.leads.update', $lead) }}" class="inline-select">
  @csrf @method('PATCH')
  <label class="sr-only" for="st-{{ $lead->id }}">Status for {{ $lead->name }}</label>
  <select id="st-{{ $lead->id }}" name="status">
    @foreach(\App\Models\Lead::STATUSES as $k => $label)<option value="{{ $k }}" @selected($lead->status === $k)>{{ $label }}</option>@endforeach
  </select>
  <button class="btn btn-small btn-ghost" type="submit">Save</button>
</form>
