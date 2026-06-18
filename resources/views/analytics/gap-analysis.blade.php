@extends('adminlte::page')

@section('title', 'Gap Analysis')

@section('content_header')
    <h1 class="m-0">Gap Analysis</h1>
    <p class="text-muted mb-0">Analisis perbandingan self score dan others score.</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <x-adminlte-card title="Filters" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('analytics.gap-analysis.index') }}">
            <div class="row">
                <div class="col-md-5">
                    <select name="period_id" class="form-control">
                        <option value="">All periods</option>
                        @foreach ($periods as $period)
                            <option value="{{ $period->id }}" @selected((int) $selectedPeriod === $period->id)>
                                {{ $period->name }} - {{ $period->semester }} {{ $period->year }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <select name="department_id" class="form-control">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) $selectedDepartment === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-block" type="submit">
                        <i class="fas fa-search mr-1"></i> Apply
                    </button>
                </div>
            </div>
            <a href="{{ route('analytics.gap-analysis.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filters</a>
        </form>
    </x-adminlte-card>

    <div class="row">
        <div class="col-lg-4 col-6">
            <x-adminlte-small-box title="{{ $summary['total'] }}" text="Employees Analyzed" icon="fas fa-users" theme="primary"/>
        </div>
        <div class="col-lg-4 col-6">
            <x-adminlte-small-box title="{{ $summary['averageGap'] ?? '-' }}" text="Average Gap Score" icon="fas fa-balance-scale" theme="info"/>
        </div>
        <div class="col-lg-4 col-12">
            <x-adminlte-small-box title="{{ $selectedPeriod ? $periods->firstWhere('id', $selectedPeriod)?->name : 'All Periods' }}" text="Selected Period" icon="far fa-calendar-alt" theme="secondary"/>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <x-adminlte-card title="Average Self Score vs Others Score" theme="primary" icon="fas fa-chart-bar">
                @if (array_sum($averageChart['data']) > 0)
                    <canvas id="averageGapChart" height="160"></canvas>
                @else
                    <div class="alert alert-light mb-0">No score data available for this filter.</div>
                @endif
            </x-adminlte-card>
        </div>
        <div class="col-lg-6">
            <x-adminlte-card title="Gap Distribution" theme="warning" icon="fas fa-chart-pie">
                @if (array_sum($gapDistributionChart['data']) > 0)
                    <canvas id="gapDistributionChart" height="160"></canvas>
                @else
                    <div class="alert alert-light mb-0">No gap distribution data available.</div>
                @endif
            </x-adminlte-card>
        </div>
    </div>

    <x-adminlte-card title="Employee Gap Detail" theme="success" icon="fas fa-table">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead>
                    <tr>
                        <th>Employee Number</th>
                        <th>Employee Name</th>
                        <th>Department</th>
                        <th class="text-right">Self Score</th>
                        <th class="text-right">Others Score</th>
                        <th class="text-right">Gap Score</th>
                        <th>Interpretation</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($results as $result)
                        <tr>
                            <td>{{ $result->employee?->employee_number ?? '-' }}</td>
                            <td>{{ $result->employee?->name ?? '-' }}</td>
                            <td>{{ $result->employee?->department?->name ?? '-' }}</td>
                            <td class="text-right">{{ number_format((float) $result->self_score, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $result->others_score, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $result->gap_score, 2) }}</td>
                            <td><span class="badge badge-{{ $result->gap_interpretation['theme'] }}">{{ $result->gap_interpretation['label'] }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No gap analysis records found for this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $results->links() }}
        </div>
    </x-adminlte-card>
@stop

@section('js')
    <script>
        const averageChartData = {{ Illuminate\Support\Js::from($averageChart) }};
        const gapDistributionData = {{ Illuminate\Support\Js::from($gapDistributionChart) }};

        if (document.getElementById('averageGapChart')) {
            new Chart(document.getElementById('averageGapChart'), {
                type: 'bar',
                data: {
                    labels: averageChartData.labels,
                    datasets: [{
                        label: 'Average Score',
                        data: averageChartData.data,
                        backgroundColor: ['#007bff', '#6c757d']
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 5
                        }
                    }
                }
            });
        }

        if (document.getElementById('gapDistributionChart')) {
            new Chart(document.getElementById('gapDistributionChart'), {
                type: 'doughnut',
                data: {
                    labels: gapDistributionData.labels,
                    datasets: [{
                        data: gapDistributionData.data,
                        backgroundColor: ['#ffc107', '#28a745', '#dc3545']
                    }]
                },
                options: {
                    maintainAspectRatio: false
                }
            });
        }
    </script>
@stop
