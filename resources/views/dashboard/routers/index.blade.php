@extends('dashboard.layout')

@section('title', 'Routers')
@section('breadcrumb', 'Routers')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Router Fleet</div>
        <div class="page-sub">MikroTik routers connected to {{ $tenant->name }}</div>
    </div>
    <a href="{{ route('dashboard.routers.create') }}" class="btn btn-primary">+ Add Router</a>
</div>

@if ($routers->isEmpty())
    <div class="card">
        <div class="empty-state">
            <div class="icon"><i class="fa-solid fa-wifi"></i></div>
            <p>No routers added yet. Add your first MikroTik router to get started.</p>
            <a href="{{ route('dashboard.routers.create') }}" class="btn btn-primary" style="margin-top:16px;display:inline-flex;">+ Add Router</a>
        </div>
    </div>
@else
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>IP Address</th>
                        <th>Port</th>
                        <th>NAS ID</th>
                        <th>Status</th>
                        <th>Last Seen</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($routers as $router)
                        <tr>
                            <td style="font-weight:600;">{{ $router->name }}</td>
                            <td style="font-family:monospace;font-size:13px;">{{ $router->router_ip }}</td>
                            <td style="color:#64748b;">{{ $router->port }}</td>
                            <td style="font-family:monospace;font-size:11px;color:#64748b;">{{ $router->nas_identifier }}</td>
                            <td>
                                <span class="badge badge-{{ $router->status }}">
                                    {{ ucfirst($router->status) }}
                                </span>
                            </td>
                            <td style="color:#64748b;font-size:13px;">
                                {{ $router->last_seen_at ? $router->last_seen_at->diffForHumans() : 'Never' }}
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;">
                                        <button
                                            class="btn btn-secondary btn-sm"
                                            onclick="testRouter(this, '{{ $router->router_ip }}', '{{ $router->username }}', {{ $router->port }})"
                                            title="Test connection">
                                        <i class="fa-solid fa-link"></i> Test
                                    </button>
                                    <a href="{{ route('dashboard.routers.edit', $router) }}" class="btn btn-secondary btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('dashboard.routers.destroy', $router) }}"
                                          onsubmit="return confirm('Remove this router?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Remove</button>
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
function testRouter(btn, ip, username, port) {
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Testing…';

    // We need the password to test — prompt the user
    const password = prompt('Enter router password to test connection:');
    if (!password) {
        btn.disabled = false;
        btn.innerHTML = original;
        return;
    }

    fetch('{{ route('dashboard.routers.test') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ ip, username, password, port })
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = data.ok ? '<i class="fa-solid fa-check"></i> OK' : '<i class="fa-solid fa-xmark"></i> Failed';
        setTimeout(() => { btn.innerHTML = original; btn.disabled = false; }, 3000);
        if (!data.ok) alert('Test failed: ' + data.message);
    })
    .catch(() => {
        btn.innerHTML = '<i class="fa-solid fa-xmark"></i> Error';
        setTimeout(() => { btn.innerHTML = original; btn.disabled = false; }, 3000);
    });
}
</script>
@endpush
