@extends('dashboard.layout')

@section('title', 'Packages')
@section('breadcrumb', 'Packages')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Hotspot Packages</div>
        <div class="page-sub">Plans available on your customer portal</div>
    </div>
    <a href="{{ route('dashboard.packages.create') }}" class="btn btn-primary">+ New Package</a>
</div>

@if ($packages->isEmpty())
    <div class="card">
        <div class="empty-state">
            <div class="icon">📦</div>
            <p>No packages yet. Create your first hotspot plan.</p>
            <a href="{{ route('dashboard.packages.create') }}" class="btn btn-primary" style="margin-top:16px;display:inline-flex;">+ Create Package</a>
        </div>
    </div>
@else
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Package Name</th>
                        <th>Price</th>
                        <th>Duration</th>
                        <th>Speed</th>
                        <th>MikroTik Profile</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($packages as $pkg)
                        <tr id="row-{{ $pkg->id }}">
                            <td style="color:#94a3b8;font-size:12px;">{{ $pkg->sort_order }}</td>
                            <td style="font-weight:600;">{{ $pkg->name }}</td>
                            <td style="font-weight:700;color:#0f172a;">{{ number_format($pkg->price) }} TZS</td>
                            <td>{{ $pkg->durationLabel() }}</td>
                            <td>↓ {{ $pkg->speed_down_mbps }}Mbps / ↑ {{ $pkg->speed_up_mbps }}Mbps</td>
                            <td style="font-family:monospace;font-size:12px;color:#475569;">{{ $pkg->mikrotik_profile }}</td>
                            <td>
                                <label class="toggle" title="{{ $pkg->is_active ? 'Active — click to deactivate' : 'Inactive — click to activate' }}">
                                    <input type="checkbox" {{ $pkg->is_active ? 'checked' : '' }}
                                           onchange="togglePackage(this, {{ $pkg->id }})">
                                    <span class="toggle-slider"></span>
                                </label>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;">
                                    <a href="{{ route('dashboard.packages.edit', $pkg) }}" class="btn btn-secondary btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('dashboard.packages.destroy', $pkg) }}"
                                          onsubmit="return confirm('Delete this package?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection

@push('scripts')
<script>
function togglePackage(checkbox, id) {
    fetch(`/dashboard/packages/${id}/toggle`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        checkbox.checked = data.is_active;
    })
    .catch(() => {
        checkbox.checked = !checkbox.checked; // revert on error
        alert('Failed to update package. Please try again.');
    });
}
</script>
@endpush
