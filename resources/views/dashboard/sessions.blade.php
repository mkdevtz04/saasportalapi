@extends('dashboard.layout')

@section('title', 'Live sessions')
@section('breadcrumb', 'Live sessions')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endpush

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Live sessions</div>
        <div class="page-sub">Customers connected right now, from your routers' own reports. Updates every few minutes.</div>
    </div>
</div>

@if (! $hasRadiusRouter)
    <div class="card">
        <div class="empty-state">
            <div class="icon"><i class="fa-solid fa-plug-circle-xmark"></i></div>
            <p>Live sessions appear once a router is connected with the one-command setup.</p>
            <a href="{{ route('dashboard.routers.index') }}" class="btn btn-primary" style="margin-top:16px;display:inline-flex;">Go to routers</a>
        </div>
    </div>
@else
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon"><i class="fa-solid fa-users"></i></span>
            <div class="stat-label">Online now</div>
            <div class="stat-value">{{ number_format($sessions->count()) }}</div>
            <div class="stat-sub">open sessions</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon"><i class="fa-solid fa-download"></i></span>
            <div class="stat-label">Downloaded today</div>
            <div class="stat-value" style="font-size:22px;">{{ \App\Support\Format::bytes($today['download']) }}</div>
            <div class="stat-sub">by {{ number_format($today['sessions']) }} sessions</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon"><i class="fa-solid fa-upload"></i></span>
            <div class="stat-label">Uploaded today</div>
            <div class="stat-value" style="font-size:22px;">{{ \App\Support\Format::bytes($today['upload']) }}</div>
            <div class="stat-sub">since midnight</div>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-chart-column"></i> Data used, last 7 days</div>
        <canvas id="usageChart" height="70"></canvas>
    </div>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-list"></i> Connected customers</div>

        @if ($sessions->isEmpty())
            <div class="empty-state">
                <div class="icon"><i class="fa-solid fa-user-clock"></i></div>
                <p>Nobody is connected at the moment.</p>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Login</th>
                            <th>Device</th>
                            <th>Address</th>
                            <th>Started</th>
                            <th>Online for</th>
                            <th>Downloaded</th>
                            <th>Uploaded</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sessions as $s)
                            @php($started = \Illuminate\Support\Carbon::parse($s->acctstarttime, 'UTC'))
                            <tr>
                                <td style="font-family:monospace;font-size:12px;">{{ $s->username }}</td>
                                <td style="font-family:monospace;font-size:12px;color:#64748b;">{{ $s->callingstationid ?: '—' }}</td>
                                <td style="font-size:12px;color:#64748b;">{{ $s->framedipaddress ?: '—' }}</td>
                                <td style="font-size:13px;color:#64748b;white-space:nowrap;">{{ $started->timezone('Africa/Dar_es_Salaam')->format('d M H:i') }}</td>
                                <td style="font-size:13px;">{{ \App\Support\Format::duration($s->acctsessiontime ?: $started->diffInSeconds(now())) }}</td>
                                <td style="font-size:13px;">{{ \App\Support\Format::bytes($s->acctoutputoctets) }}</td>
                                <td style="font-size:13px;">{{ \App\Support\Format::bytes($s->acctinputoctets) }}</td>
                                <td>
                                    <form method="POST" action="{{ route('dashboard.sessions.disconnect') }}"
                                          onsubmit="return confirm('Disconnect this customer and remove their access? They will not be able to reconnect.')">
                                        @csrf
                                        <input type="hidden" name="username" value="{{ $s->username }}">
                                        <button type="submit" class="btn btn-danger btn-sm">Disconnect</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif

@endsection

@push('scripts')
@if ($hasRadiusRouter)
<script>
const usage = @json($byDay);
new Chart(document.getElementById('usageChart'), {
    type: 'bar',
    data: {
        labels: Object.keys(usage).map(d => new Date(d + 'T00:00:00').toLocaleDateString(undefined, {weekday: 'short', day: 'numeric'})),
        datasets: [{
            label: 'Data (GB)',
            data: Object.values(usage).map(b => +(b / 1073741824).toFixed(2)),
            backgroundColor: '#2561e822', borderColor: '#2561e8', borderWidth: 2, borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.raw + ' GB' } } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => v + ' GB' } } }
    }
});
</script>
@endif
@endpush
