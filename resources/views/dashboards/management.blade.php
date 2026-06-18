@extends('adminlte::page')

@section('title', 'Management Dashboard')

@section('content_header')
    <h1 class="m-0">Management Dashboard</h1>
    <p class="text-muted mb-0">Company and unit aggregates without individual employee rankings.</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <x-adminlte-card title="Filters" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('management.dashboard') }}">
            <div class="row">
                <div class="col-md-3"><select name="period_id" class="form-control"><option value="">All periods</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected((int)$filters['period_id'] === $period->id)>{{ $period->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><select name="department_id" class="form-control"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((int)$filters['department_id'] === $department->id)>{{ $department->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><select name="category" class="form-control"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category }}" @selected($filters['category'] === $category)>{{ $category }}</option>@endforeach</select></div>
                <div class="col-md-2"><select name="talent_category" class="form-control"><option value="">All talent</option>@foreach($talentCategories as $category)<option value="{{ $category }}" @selected($filters['talent_category'] === $category)>{{ $category }}</option>@endforeach</select></div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-block mb-2">Apply</button>
                    <a href="{{ route('management.dashboard') }}" class="btn btn-outline-secondary btn-block">Reset</a>
                </div>
            </div>
        </form>
    </x-adminlte-card>
    <div class="row">
        <div class="col-lg-2 col-6"><x-adminlte-small-box title="{{ $summary['assessedEmployees'] }}" text="Assessed Employees" icon="fas fa-user-check" theme="primary"/></div>
        <div class="col-lg-2 col-6"><x-adminlte-small-box title="{{ $summary['averageScore'] ?? '-' }}" text="Company Average" icon="fas fa-star" theme="info"/></div>
        <div class="col-lg-2 col-6"><x-adminlte-small-box title="{{ $summary['completionRate'] }}%" text="Completion Rate" icon="fas fa-tasks" theme="success"/></div>
        <div class="col-lg-2 col-6"><x-adminlte-small-box title="{{ $summary['belowThreshold'] }}" text="Below Threshold" icon="fas fa-exclamation-triangle" theme="danger"/></div>
        <div class="col-lg-2 col-6"><x-adminlte-small-box title="{{ $summary['highPotential'] }}" text="High Potential" icon="fas fa-rocket" theme="success"/></div>
        <div class="col-lg-2 col-6"><x-adminlte-small-box title="{{ $summary['activeIdp'] }}" text="Active IDP" icon="fas fa-lightbulb" theme="warning"/></div>
    </div>
    <div class="row">
        <div class="col-lg-6"><x-adminlte-card title="Company Core Value Profile" theme="primary" icon="fas fa-chart-bar">@if(array_sum($coreValueChart['data']))<canvas id="coreChart" height="140"></canvas>@else<p class="text-muted mb-0">No core value data.</p>@endif</x-adminlte-card></div>
        <div class="col-lg-6"><x-adminlte-card title="Talent Category Distribution" theme="success" icon="fas fa-chart-pie">@if(array_sum($talentChart['data']))<canvas id="talentChart" height="140"></canvas>@else<p class="text-muted mb-0">No talent data.</p>@endif</x-adminlte-card></div>
    </div>
    <div class="row">
        <div class="col-lg-7"><x-adminlte-card title="Semester Trend" theme="info" icon="fas fa-chart-line">@if(count($trendChart['final']))<canvas id="trendChart" height="120"></canvas>@else<p class="text-muted mb-0">No trend data.</p>@endif</x-adminlte-card></div>
        <div class="col-lg-5"><x-adminlte-card title="Gap Distribution" theme="warning" icon="fas fa-balance-scale"><canvas id="gapChart" height="120"></canvas></x-adminlte-card></div>
    </div>
    <x-adminlte-card title="Management Attention by Department / Unit" theme="danger" icon="fas fa-building">
        <div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Department</th><th class="text-right">Assessed</th><th class="text-right">Average</th><th class="text-right">Completion</th><th class="text-right">Below Threshold</th><th class="text-right">High Potential</th></tr></thead><tbody>
            @forelse($departmentRows as $row)<tr><td>{{ $row['department']->name }}</td><td class="text-right">{{ $row['assessed'] }}</td><td class="text-right">{{ $row['average'] ?? '-' }}</td><td class="text-right">{{ $row['completion'] }}%</td><td class="text-right">{{ $row['belowThreshold'] }}</td><td class="text-right">{{ $row['highPotential'] }}</td></tr>@empty<tr><td colspan="6" class="text-center text-muted">No department aggregates.</td></tr>@endforelse
        </tbody></table></div>
    </x-adminlte-card>
    <x-adminlte-card title="IDP Status Summary" theme="secondary" icon="fas fa-lightbulb"><div class="row">@forelse($idpStatus as $status => $count)<div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-secondary"><i class="fas fa-folder"></i></span><div class="info-box-content"><span class="info-box-text">{{ ucfirst(str_replace('_', ' ', $status)) }}</span><span class="info-box-number">{{ $count }}</span></div></div></div>@empty<div class="col-12 text-muted">No IDP status data.</div>@endforelse</div></x-adminlte-card>
@stop

@section('js')
    <script>
        const core = {{ Illuminate\Support\Js::from($coreValueChart) }};
        const talent = {{ Illuminate\Support\Js::from($talentChart) }};
        const trend = {{ Illuminate\Support\Js::from($trendChart) }};
        const gap = {{ Illuminate\Support\Js::from($gapDistribution) }};
        const scoreScale = { beginAtZero: true, max: 5 };
        if (document.getElementById('coreChart')) new Chart(document.getElementById('coreChart'), { type: 'bar', data: { labels: core.labels, datasets: [{ label: 'Average', data: core.data }] }, options: { maintainAspectRatio: false, scales: { y: scoreScale } } });
        if (document.getElementById('talentChart')) new Chart(document.getElementById('talentChart'), { type: 'doughnut', data: { labels: talent.labels, datasets: [{ data: talent.data }] }, options: { maintainAspectRatio: false } });
        if (document.getElementById('trendChart')) new Chart(document.getElementById('trendChart'), { type: 'line', data: { labels: trend.labels, datasets: [{ label: 'Average Final Score', data: trend.final }] }, options: { maintainAspectRatio: false, scales: { y: scoreScale } } });
        if (document.getElementById('gapChart')) new Chart(document.getElementById('gapChart'), { type: 'doughnut', data: { labels: gap.labels, datasets: [{ data: gap.data }] }, options: { maintainAspectRatio: false } });
    </script>
@stop
