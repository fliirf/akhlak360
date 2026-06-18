@extends('adminlte::page')

@section('title', 'IDP Recommendations')

@section('content_header')
    <h1 class="m-0">IDP Recommendations</h1>
    <p class="text-muted mb-0">Individual Development Plan monitoring based on AKHLAK 360 results.</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    @include('partials.flash')

    <x-adminlte-card title="Filters" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('idp-talent.idp-recommendations.index') }}">
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
                    <select name="status" class="form-control">
                        <option value="">All status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary btn-block" type="submit">
                        <i class="fas fa-search mr-1"></i> Apply
                    </button>
                </div>
            </div>
            <a href="{{ route('idp-talent.idp-recommendations.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filters</a>
        </form>
    </x-adminlte-card>

    <x-adminlte-card title="IDP Recommendation List" theme="success" icon="fas fa-lightbulb">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Weakest Core Value</th>
                        <th>Recommendation</th>
                        <th>Action Plan</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        @if ($canEdit)
                            <th class="text-right">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recommendations as $recommendation)
                        <tr>
                            <td>
                                <strong>{{ $recommendation->employee?->name ?? '-' }}</strong><br>
                                <span class="text-muted">{{ $recommendation->employee?->employee_number ?? '-' }}</span>
                            </td>
                            <td>{{ $recommendation->employee?->department?->name ?? '-' }}</td>
                            <td><span class="badge badge-info">{{ $recommendation->weakest_core_value }}</span></td>
                            <td>{{ $recommendation->recommendation }}</td>
                            <td>{{ $recommendation->action_plan ?? '-' }}</td>
                            <td>{{ $recommendation->due_date?->format('d M Y') ?? '-' }}</td>
                            <td>
                                <span class="badge badge-{{ ['draft' => 'secondary', 'approved' => 'primary', 'in_progress' => 'warning', 'completed' => 'success'][$recommendation->status] ?? 'secondary' }}">
                                    {{ ucfirst(str_replace('_', ' ', $recommendation->status)) }}
                                </span>
                            </td>
                            @if ($canEdit)
                                <td class="text-right">
                                    <a href="{{ route('idp-talent.idp-recommendations.edit', $recommendation) }}" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canEdit ? 8 : 7 }}" class="text-center text-muted">No IDP recommendations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $recommendations->links() }}
        </div>
    </x-adminlte-card>
@stop
