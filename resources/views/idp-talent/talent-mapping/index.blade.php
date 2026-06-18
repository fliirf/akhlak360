@extends('adminlte::page')

@section('title', 'Talent Mapping')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="m-0">Talent Mapping</h1>
            <p class="text-muted mb-0">Mapping talenta berdasarkan hasil AKHLAK 360 dan gap score.</p>
        </div>
        @if ($canExport)
            <a href="{{ route('idp-talent.talent-mapping.export', request()->only(['period_id', 'department_id'])) }}" class="btn btn-success mt-3 mt-md-0">
                <i class="fas fa-file-csv mr-1"></i> Export CSV
            </a>
        @endif
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <x-adminlte-card title="Filters" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('idp-talent.talent-mapping.index') }}">
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
            <a href="{{ route('idp-talent.talent-mapping.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filters</a>
        </form>
    </x-adminlte-card>

    <div class="alert alert-info">
        Development/IDP uses 60% of result interpretation and Talent Mapping uses 40% of result interpretation. For this MVP, talent category is displayed using final_score and gap_score from calculated assessment results.
    </div>

    <div class="row">
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $categoryCounts['High Potential'] }}" text="High Potential" icon="fas fa-rocket" theme="success"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $categoryCounts['Solid Contributor'] }}" text="Solid Contributor" icon="fas fa-star" theme="primary"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $categoryCounts['Core Contributor'] }}" text="Core Contributor" icon="fas fa-user-check" theme="info"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $categoryCounts['Need Development'] }}" text="Need Development" icon="fas fa-tools" theme="danger"/></div>
    </div>

    <div class="row">
        <div class="col-lg-5">
            <x-adminlte-card title="Category Distribution" theme="warning" icon="fas fa-chart-pie">
                @if (array_sum($categoryChart['data']) > 0)
                    <canvas id="talentCategoryChart" height="210"></canvas>
                @else
                    <div class="alert alert-light mb-0">No talent mapping data available for this filter.</div>
                @endif
            </x-adminlte-card>
        </div>
        <div class="col-lg-7">
            <x-adminlte-card title="Talent Mapping Detail" theme="success" icon="fas fa-table">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Department</th>
                                <th class="text-right">Final Score</th>
                                <th class="text-right">Gap Score</th>
                                <th>Talent Mapping Category</th>
                                <th>IDP Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($results as $result)
                                @php
                                    $idpStatus = $result->employee?->idpRecommendations->first()?->status;
                                    $categoryTheme = [
                                        'High Potential' => 'success',
                                        'Solid Contributor' => 'primary',
                                        'Core Contributor' => 'info',
                                        'Need Development' => 'danger',
                                    ][$result->talent_mapping_category] ?? 'secondary';
                                @endphp
                                <tr>
                                    <td>{{ $result->employee?->name ?? '-' }}</td>
                                    <td>{{ $result->employee?->department?->name ?? '-' }}</td>
                                    <td class="text-right">{{ number_format((float) $result->final_score, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $result->gap_score, 2) }}</td>
                                    <td><span class="badge badge-{{ $categoryTheme }}">{{ $result->talent_mapping_category }}</span></td>
                                    <td>
                                        @if ($idpStatus)
                                            <span class="badge badge-{{ ['draft' => 'secondary', 'approved' => 'primary', 'in_progress' => 'warning', 'completed' => 'success'][$idpStatus] ?? 'secondary' }}">
                                                {{ ucfirst(str_replace('_', ' ', $idpStatus)) }}
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No talent mapping records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $results->links() }}
                </div>
            </x-adminlte-card>
        </div>
    </div>
@stop

@section('js')
    <script>
        const categoryChartData = {{ Illuminate\Support\Js::from($categoryChart) }};

        if (document.getElementById('talentCategoryChart')) {
            new Chart(document.getElementById('talentCategoryChart'), {
                type: 'doughnut',
                data: {
                    labels: categoryChartData.labels,
                    datasets: [{
                        data: categoryChartData.data,
                        backgroundColor: ['#28a745', '#007bff', '#17a2b8', '#dc3545']
                    }]
                },
                options: {
                    maintainAspectRatio: false
                }
            });
        }
    </script>
@stop
