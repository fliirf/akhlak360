@extends('adminlte::page')

@section('title', 'Distribusi Departemen')

@section('content_header')
    <h1 class="m-0">Distribusi Departemen</h1>
    <p class="text-muted mb-0">Perbandingan hasil dan penyelesaian penilaian per departemen.</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <x-adminlte-card title="Filter Analitik" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('analytics.department-distribution.index') }}">
            <div class="row">
                <div class="col-md-5">
                    <label for="period_id">Periode Penilaian</label>
                    <select id="period_id" name="period_id" class="form-control">
                        @foreach ($periods as $item)
                            <option value="{{ $item->id }}" @selected($selectedPeriod === $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="department_id">Departemen</label>
                    <select id="department_id" name="department_id" class="form-control">
                        <option value="">Semua departemen</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($selectedDepartment === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search mr-1"></i>Terapkan</button>
                </div>
            </div>
            <a href="{{ route('analytics.department-distribution.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filters</a>
        </form>
    </x-adminlte-card>

    @if (! $period)
        <div class="alert alert-warning">
            <h5><i class="icon fas fa-calendar-times"></i> Belum ada periode penilaian</h5>
            Data distribusi dapat ditampilkan setelah periode dibuat.
            @if (auth()->user()->hasRole('admin_hr'))
                <a class="btn btn-sm btn-warning ml-2" href="{{ route('assessment-cycle.periods.create') }}">Buat Periode</a>
            @endif
        </div>
    @else
        <div class="row">
            <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['employees'] }}" text="Pegawai Aktif" icon="fas fa-users" theme="primary"/></div>
            <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['assessed'] }}" text="Pegawai Dinilai" icon="fas fa-user-check" theme="success"/></div>
            <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['average'] !== null ? number_format($summary['average'], 2) : '-' }}" text="Rata-rata Departemen" icon="fas fa-star" theme="info"/></div>
            <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['below'] }}" text="Di Bawah Threshold" icon="fas fa-exclamation-triangle" theme="danger"/></div>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <x-adminlte-card title="Rata-rata Skor Akhir" theme="primary" icon="fas fa-chart-bar">
                    @if ($summary['assessed'] > 0)
                        <canvas id="departmentAverageChart" height="150"></canvas>
                    @else
                        <div class="alert alert-light border mb-0">Belum ada hasil penilaian untuk filter ini.</div>
                    @endif
                </x-adminlte-card>
            </div>
            <div class="col-lg-5">
                <x-adminlte-card title="Cakupan Pegawai Dinilai" theme="success" icon="fas fa-chart-bar">
                    @if ($summary['employees'] > 0)
                        <canvas id="departmentCoverageChart" height="150"></canvas>
                    @else
                        <div class="alert alert-light border mb-0">Tidak ada pegawai aktif pada departemen terpilih.</div>
                    @endif
                </x-adminlte-card>
            </div>
        </div>

        <x-adminlte-card title="Ringkasan per Departemen" theme="secondary" icon="fas fa-table">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead><tr><th>Departemen</th><th class="text-right">Pegawai</th><th class="text-right">Dinilai</th><th class="text-right">Rata-rata</th><th class="text-right">Di Bawah Threshold</th><th class="text-right">Penyelesaian</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-right">{{ $row['employee_count'] }}</td>
                                <td class="text-right">{{ $row['assessed_count'] }}</td>
                                <td class="text-right">{{ $row['average_score'] !== null ? number_format($row['average_score'], 2) : '-' }}</td>
                                <td class="text-right"><span class="badge badge-{{ $row['below_threshold_count'] ? 'danger' : 'success' }}">{{ $row['below_threshold_count'] }}</span></td>
                                <td class="text-right">{{ number_format($row['completion_percentage'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">Tidak ada departemen yang sesuai.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-adminlte-card>
    @endif
@stop

@section('js')
    <script>
        const averageData = {{ Illuminate\Support\Js::from($averageChart) }};
        const distributionData = {{ Illuminate\Support\Js::from($distributionChart) }};
        const scoreScale = { beginAtZero: true, max: 5 };
        if (document.getElementById('departmentAverageChart')) new Chart(document.getElementById('departmentAverageChart'), {
            type: 'bar', data: { labels: averageData.labels, datasets: [{ label: 'Rata-rata Skor', data: averageData.data, backgroundColor: '#007bff' }] },
            options: { maintainAspectRatio: false, scales: { y: scoreScale } }
        });
        if (document.getElementById('departmentCoverageChart')) new Chart(document.getElementById('departmentCoverageChart'), {
            type: 'bar', data: { labels: distributionData.labels, datasets: [
                { label: 'Sudah Dinilai', data: distributionData.assessed, backgroundColor: '#28a745' },
                { label: 'Belum Dinilai', data: distributionData.unassessed, backgroundColor: '#dee2e6' }
            ]}, options: { maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } }
        });
    </script>
@stop
