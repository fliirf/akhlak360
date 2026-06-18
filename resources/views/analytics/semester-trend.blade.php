@extends('adminlte::page')

@section('title', 'Tren Semester')

@section('content_header')
    <h1 class="m-0">Tren Semester</h1>
    <p class="text-muted mb-0">Perubahan skor AKHLAK antarperiode penilaian.</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <x-adminlte-card title="Filter Departemen" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('analytics.semester-trend.index') }}">
            <div class="row">
                <div class="col-md-10">
                    <select name="department_id" class="form-control">
                        <option value="">Semua departemen</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($selectedDepartment === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search mr-1"></i>Terapkan</button></div>
            </div>
            <a href="{{ route('analytics.semester-trend.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filters</a>
        </form>
    </x-adminlte-card>

    @if ($trend['period_count'] === 0)
        <div class="alert alert-warning">
            <h5><i class="icon fas fa-calendar-times"></i> Belum ada periode penilaian</h5>
            Buat periode dan kalkulasikan hasil untuk mulai melihat tren.
            @if (auth()->user()->hasRole('admin_hr'))
                <a class="btn btn-sm btn-warning ml-2" href="{{ route('assessment-cycle.periods.create') }}">Buat Periode</a>
            @endif
        </div>
    @elseif ($trend['result_period_count'] === 0)
        <div class="alert alert-info">Periode tersedia, tetapi belum ada hasil penilaian untuk filter ini.</div>
    @else
        @if ($trend['result_period_count'] === 1)
            <div class="alert alert-info"><i class="fas fa-info-circle mr-1"></i>Baru satu periode yang memiliki hasil. Grafik ditampilkan, tetapi perbandingan historis memerlukan minimal dua periode.</div>
        @endif
        <div class="row">
            <div class="col-lg-6">
                <x-adminlte-card title="Tren Rata-rata Skor Akhir" theme="primary" icon="fas fa-chart-line">
                    <canvas id="finalScoreTrendChart" height="175"></canvas>
                </x-adminlte-card>
            </div>
            <div class="col-lg-6">
                <x-adminlte-card title="Tren Core Values AKHLAK" theme="info" icon="fas fa-chart-line">
                    <canvas id="coreValueTrendChart" height="175"></canvas>
                </x-adminlte-card>
            </div>
        </div>
        <x-adminlte-card title="Data Tren" theme="secondary" icon="fas fa-table">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead><tr><th>Periode</th><th class="text-right">Rata-rata Skor Akhir</th></tr></thead>
                    <tbody>
                        @foreach ($trend['labels'] as $index => $label)
                            <tr><td>{{ $label }}</td><td class="text-right">{{ $trend['final'][$index] !== null ? number_format($trend['final'][$index], 2) : '-' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-adminlte-card>
    @endif
@stop

@section('js')
    <script>
        const trendData = {{ Illuminate\Support\Js::from($trend) }};
        const colors = ['#007bff', '#28a745', '#17a2b8', '#ffc107', '#6f42c1', '#20c997'];
        const yScale = { beginAtZero: true, max: 5 };
        if (document.getElementById('finalScoreTrendChart')) new Chart(document.getElementById('finalScoreTrendChart'), {
            type: 'line', data: { labels: trendData.labels, datasets: [{ label: 'Rata-rata Skor Akhir', data: trendData.final, borderColor: '#007bff', backgroundColor: 'rgba(0,123,255,.12)', fill: true, tension: .25 }] },
            options: { maintainAspectRatio: false, scales: { y: yScale } }
        });
        if (document.getElementById('coreValueTrendChart')) new Chart(document.getElementById('coreValueTrendChart'), {
            type: 'line', data: { labels: trendData.labels, datasets: trendData.core_values.map((item, index) => ({ label: item.label, data: item.data, borderColor: colors[index], tension: .25 })) },
            options: { maintainAspectRatio: false, scales: { y: yScale } }
        });
    </script>
@stop
