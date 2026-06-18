@extends('adminlte::page')

@section('title', 'Assessment Periods')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">Assessment Periods</h1>
        <a href="{{ route('assessment-cycle.periods.create') }}" class="btn btn-primary">
            <i class="fas fa-plus mr-1"></i> Create Period
        </a>
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    @include('partials.flash')

    <x-adminlte-card title="Period List" theme="primary" icon="far fa-calendar-alt">
        <form method="GET" action="{{ route('assessment-cycle.periods.index') }}" class="mb-3">
            <div class="row">
                <div class="col-md-7">
                    <input name="search" type="text" value="{{ request('search') }}" class="form-control"
                        placeholder="Search period or semester">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control">
                        <option value="">All status</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="closed" @selected(request('status') === 'closed')>Closed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-block" type="submit">
                        <i class="fas fa-search mr-1"></i> Search
                    </button>
                </div>
            </div>
            <a href="{{ route('assessment-cycle.periods.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filters</a>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Semester</th>
                        <th>Year</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th class="text-right">Threshold</th>
                        <th class="text-right">Assignments</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($periods as $period)
                        <tr>
                            <td>{{ $period->name }}</td>
                            <td>{{ $period->semester }}</td>
                            <td>{{ $period->year }}</td>
                            <td>{{ $period->start_date->format('d M Y') }} - {{ $period->end_date->format('d M Y') }}</td>
                            <td>
                                <span class="badge badge-{{ ['draft' => 'secondary', 'active' => 'success', 'closed' => 'dark'][$period->status] }}">
                                    {{ ucfirst($period->status) }}
                                </span>
                            </td>
                            <td class="text-right">{{ $period->threshold_score }}</td>
                            <td class="text-right">{{ $period->assignments_count }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('assessment-cycle.periods.recalculate', $period) }}" class="d-inline"
                                    onsubmit="return confirm('Recalculate results for this period?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-info" title="Recalculate Period Results">
                                        <i class="fas fa-calculator"></i>
                                    </button>
                                </form>
                                @if ($period->status !== 'closed')
                                    <form method="POST" action="{{ route('assessment-cycle.periods.close', $period) }}" class="d-inline"
                                        onsubmit="return confirm('Close this period? Results will be recalculated first.');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-dark" title="Close Period">
                                            <i class="fas fa-lock"></i>
                                        </button>
                                    </form>
                                @endif
                                <a href="{{ route('assessment-cycle.periods.edit', $period) }}" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="{{ route('assessment-cycle.periods.destroy', $period) }}" class="d-inline"
                                    onsubmit="return confirm('Delete this period? Periods with assignments will be closed instead.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">No assessment periods found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $periods->links() }}
    </x-adminlte-card>
@stop
