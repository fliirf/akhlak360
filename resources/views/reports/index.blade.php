@extends('adminlte::page')

@section('title', 'Export Reports')

@section('content_header')
    <h1 class="m-0">Export Reports</h1>
    <p class="text-muted mb-0">Generate AKHLAK 360 result reports by period, department, category, and threshold status.</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    @include('partials.flash')

    <x-adminlte-card title="Report Filters" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('reports.export.index') }}">
            <div class="row">
                <div class="col-md-3">
                    <select name="period_id" class="form-control">
                        <option value="">All periods</option>
                        @foreach ($periods as $period)
                            <option value="{{ $period->id }}" @selected((int) request('period_id') === $period->id)>
                                {{ $period->name }} - {{ $period->semester }} {{ $period->year }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="department_id" class="form-control">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-control">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="form-check pt-2">
                        <input id="below_threshold" name="below_threshold" value="1" type="checkbox" class="form-check-input" @checked(request()->boolean('below_threshold'))>
                        <label for="below_threshold" class="form-check-label">Below threshold only</label>
                    </div>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-4">
                    <select name="talent_category" class="form-control">
                        <option value="">All talent categories</option>
                        @foreach ($talentCategories as $talentCategory)
                            <option value="{{ $talentCategory }}" @selected(request('talent_category') === $talentCategory)>{{ $talentCategory }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
                <div>
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search mr-1"></i> Apply Filters
                    </button>
                    <a href="{{ route('reports.export.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-undo mr-1"></i> Reset
                    </a>
                </div>
                <div class="btn-group mt-2 mt-md-0">
                    <a href="{{ route('reports.export.csv', request()->query()) }}" class="btn btn-success">
                        <i class="fas fa-file-csv mr-1"></i> CSV
                    </a>
                    <a href="{{ route('reports.export.excel', request()->query()) }}" class="btn btn-outline-success">
                        <i class="fas fa-file-excel mr-1"></i> Excel
                    </a>
                    <a href="{{ route('reports.export.pdf', request()->query()) }}" class="btn btn-outline-danger">
                        <i class="fas fa-file-pdf mr-1"></i> PDF
                    </a>
                </div>
            </div>
        </form>
    </x-adminlte-card>

    <div class="row">
        <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ $summary['records'] }}" text="Record Laporan" icon="fas fa-list" theme="primary"/></div>
        <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ number_format($summary['averageScore'], 2) }}" text="Rata-rata Skor" icon="fas fa-star" theme="info"/></div>
        <div class="col-lg-4 col-12"><x-adminlte-small-box title="{{ $summary['belowThreshold'] }}" text="Di Bawah Threshold" icon="fas fa-exclamation-triangle" theme="danger"/></div>
    </div>

    <x-adminlte-card title="Report Preview" theme="success" icon="fas fa-table">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th class="text-right">Final</th>
                        <th>Category</th>
                        <th>Talent</th>
                        <th>IDP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($results as $result)
                        @php($idp = $result->employee?->idpRecommendations->firstWhere('assessment_period_id', $result->assessment_period_id))
                        <tr>
                            <td>{{ $result->assessmentPeriod?->name ?? '-' }}</td>
                            <td>
                                <strong>{{ $result->employee?->name ?? '-' }}</strong><br>
                                <span class="text-muted">{{ $result->employee?->employee_number ?? '-' }}</span>
                            </td>
                            <td>{{ $result->employee?->department?->name ?? '-' }}</td>
                            <td>{{ $result->employee?->position?->name ?? '-' }}</td>
                            <td class="text-right">{{ number_format((float) $result->final_score, 2) }}</td>
                            <td><span class="badge badge-info">{{ $result->category }}</span></td>
                            <td>{{ $result->talent_mapping_category ?? '-' }}</td>
                            <td>{{ $idp?->weakest_core_value ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">No report data found for this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $results->links() }}
        </div>
    </x-adminlte-card>
@stop
