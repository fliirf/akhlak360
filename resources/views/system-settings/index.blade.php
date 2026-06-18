@extends('adminlte::page')

@section('title', 'System Settings')

@section('content_header')
    <h1 class="m-0">System Settings</h1>
    <p class="text-muted mb-0">Konfigurasi read-only untuk academic MVP AKHLAK360.</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <div class="alert alert-info">
        <i class="fas fa-info-circle mr-1"></i>
        Pengaturan berasal dari file konfigurasi dan environment. Halaman ini tidak menyimpan konfigurasi ke database.
    </div>

    <div class="row">
        <div class="col-lg-6">
            <x-adminlte-card title="Konfigurasi Aplikasi" theme="primary" icon="fas fa-sliders-h">
                <table class="table table-sm mb-0">
                    <tr><th>Nama aplikasi</th><td>{{ $settings['applicationName'] }}</td></tr>
                    <tr><th>Default threshold</th><td>{{ number_format($settings['defaultThreshold'], 2) }}</td></tr>
                    <tr><th>Interval reminder</th><td>{{ $settings['reminderInterval'] }} hari</td></tr>
                    <tr><th>Notifikasi email/log</th><td><span class="badge badge-{{ $settings['emailNotifications'] ? 'success' : 'secondary' }}">{{ $settings['emailNotifications'] ? 'Aktif' : 'Nonaktif' }}</span></td></tr>
                    <tr><th>Notifikasi in-app</th><td><span class="badge badge-{{ $settings['inAppNotifications'] ? 'success' : 'secondary' }}">{{ $settings['inAppNotifications'] ? 'Aktif' : 'Nonaktif' }}</span></td></tr>
                    <tr><th>Mail driver</th><td><code>{{ $settings['mailDriver'] }}</code></td></tr>
                </table>
            </x-adminlte-card>
        </div>
        <div class="col-lg-6">
            <x-adminlte-card title="Environment dan Simulasi" theme="info" icon="fas fa-server">
                <table class="table table-sm mb-0">
                    <tr><th>Environment</th><td>{{ $settings['environment'] }}</td></tr>
                    <tr><th>Debug</th><td>{{ $settings['debug'] ? 'Aktif' : 'Nonaktif' }}</td></tr>
                    <tr><th>Database</th><td>{{ strtoupper($settings['database']) }} / {{ $mvp['database'] }}</td></tr>
                    <tr><th>Queue</th><td>{{ $settings['queue'] }}</td></tr>
                    <tr><th>HRIS</th><td><span class="badge badge-warning">Simulasi CSV</span></td></tr>
                    <tr><th>SSO</th><td><span class="badge badge-warning">Simulasi</span></td></tr>
                    <tr><th>Format laporan aktif</th><td>{{ implode(', ', $mvp['report_formats']) }}</td></tr>
                </table>
            </x-adminlte-card>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <x-adminlte-card title="Bobot Penilaian Default" theme="success" icon="fas fa-balance-scale">
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead><tr><th>Tipe Penilai</th><th class="text-right">Default</th><th class="text-right">{{ $activePeriod?->name ?? 'Periode Aktif' }}</th></tr></thead>
                        <tbody>
                            @foreach ($configuredWeights as $type => $weight)
                                <tr>
                                    <td>{{ ucfirst($type) }}</td>
                                    <td class="text-right">{{ number_format($weight, 2) }}%</td>
                                    <td class="text-right">{{ $activeWeights->has($type) ? number_format($activeWeights[$type], 2).'%' : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (! $activePeriod)
                    <div class="alert alert-light border mt-3 mb-0">Belum ada periode aktif; kolom periode menggunakan state kosong.</div>
                @endif
            </x-adminlte-card>
        </div>
        <div class="col-lg-5">
            <x-adminlte-card title="Status Runtime" theme="secondary" icon="fas fa-microchip">
                <table class="table table-sm mb-0">
                    <tr><th>PHP</th><td>{{ $system['php'] }}</td></tr>
                    <tr><th>Laravel</th><td>{{ $system['laravel'] }}</td></tr>
                    <tr><th>Weight records</th><td>{{ $system['weightRecords'] }}</td></tr>
                    <tr><th>Queued jobs</th><td>{{ $system['pendingJobs'] }}</td></tr>
                    <tr><th>Failed jobs</th><td>{{ $system['failedJobs'] }}</td></tr>
                </table>
            </x-adminlte-card>
        </div>
    </div>
@stop
