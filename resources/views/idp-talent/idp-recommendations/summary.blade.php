@extends('adminlte::page')

@section('title', 'IDP Summary')

@section('content_header')
    <h1 class="m-0">IDP Summary</h1>
    <p class="text-muted mb-0">Aggregate IDP monitoring for management.</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <x-adminlte-card title="Filters" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('idp-talent.idp-recommendations.index') }}">
            <div class="row">
                <div class="col-md-5">
                    <select name="period_id" class="form-control">
                        <option value="">All periods</option>
                        @foreach ($periods as $period)
                            <option value="{{ $period->id }}" @selected((int) request('period_id') === $period->id)>
                                {{ $period->name }} - {{ $period->semester }} {{ $period->year }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <select name="department_id" class="form-control">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-block" type="submit">
                        <i class="fas fa-search mr-1"></i> Apply
                    </button>
                </div>
            </div>
            <a href="{{ route('idp-talent.idp-recommendations.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filters</a>
        </form>
    </x-adminlte-card>

    <div class="row">
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['total'] }}" text="Total IDP" icon="fas fa-lightbulb" theme="primary"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['open'] }}" text="Open IDP" icon="fas fa-folder-open" theme="warning"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['completed'] }}" text="Completed IDP" icon="fas fa-check-circle" theme="success"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['overdue'] }}" text="Overdue IDP" icon="fas fa-exclamation-triangle" theme="danger"/></div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <x-adminlte-card title="IDP Status Distribution" theme="info" icon="fas fa-chart-pie">
                @if (array_sum($statusChart['data']) > 0)
                    <canvas id="statusChart" height="180"></canvas>
                @else
                    <div class="alert alert-light mb-0">No IDP summary data available.</div>
                @endif
            </x-adminlte-card>
        </div>
        <div class="col-lg-6">
            <x-adminlte-card title="Weakest Core Value Distribution" theme="success" icon="fas fa-chart-bar">
                @if (array_sum($coreValueChart['data']) > 0)
                    <canvas id="coreValueChart" height="180"></canvas>
                @else
                    <div class="alert alert-light mb-0">No core value summary data available.</div>
                @endif
            </x-adminlte-card>
        </div>
    </div>
@stop

@section('js')
    <script>
        const statusChartData = {{ Illuminate\Support\Js::from($statusChart) }};
        const coreValueChartData = {{ Illuminate\Support\Js::from($coreValueChart) }};

        if (document.getElementById('statusChart')) {
            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: statusChartData.labels,
                    datasets: [{ data: statusChartData.data, backgroundColor: ['#6c757d', '#007bff', '#ffc107', '#28a745'] }]
                },
                options: { maintainAspectRatio: false }
            });
        }

        if (document.getElementById('coreValueChart')) {
            new Chart(document.getElementById('coreValueChart'), {
                type: 'bar',
                data: {
                    labels: coreValueChartData.labels,
                    datasets: [{ label: 'IDP Count', data: coreValueChartData.data, backgroundColor: '#28a745' }]
                },
                options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
            });
        }
    </script>
@stop
