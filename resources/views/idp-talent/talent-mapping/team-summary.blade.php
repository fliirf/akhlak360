@extends('adminlte::page')

@section('title', 'Team Talent Summary')

@section('content_header')
    <h1 class="m-0">Team Talent Summary</h1>
    <p class="text-muted mb-0">Aggregate talent categories for direct reports without employee rankings.</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <x-adminlte-card title="Filter Periode" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('idp-talent.talent-mapping.index') }}">
            <div class="row">
                <div class="col-md-10">
                    <select name="period_id" class="form-control">
                        @foreach ($periods as $period)
                            <option value="{{ $period->id }}" @selected($selectedPeriod === $period->id)>{{ $period->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary btn-block">Apply</button></div>
            </div>
        </form>
    </x-adminlte-card>

    <div class="row">
        @foreach ($categoryCounts as $category => $count)
            <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $count }}" text="{{ $category }}" icon="fas fa-chart-pie" theme="info"/></div>
        @endforeach
    </div>
    <x-adminlte-card title="Category Distribution" theme="info" icon="fas fa-chart-pie">
        @if (array_sum($categoryChart['data']) > 0)
            <canvas id="teamTalentChart" height="120"></canvas>
        @else
            <div class="alert alert-light mb-0">No aggregate talent data is available.</div>
        @endif
    </x-adminlte-card>
@stop

@section('js')
    <script>
        const teamTalentData = {{ Illuminate\Support\Js::from($categoryChart) }};
        const target = document.getElementById('teamTalentChart');
        if (target) new Chart(target, {
            type: 'doughnut',
            data: { labels: teamTalentData.labels, datasets: [{ data: teamTalentData.data }] },
            options: { maintainAspectRatio: false }
        });
    </script>
@stop
