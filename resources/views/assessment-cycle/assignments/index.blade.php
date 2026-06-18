@extends('adminlte::page')

@section('title', 'Assign Assessors')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">Assign Assessors</h1>
        <a href="{{ route('assessment-cycle.assign-assessors.create') }}" class="btn btn-primary">
            <i class="fas fa-plus mr-1"></i> Create Assignment
        </a>
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <div class="assignments-index-page">
    @include('partials.flash')

    @if (! $activePeriod)
        <div class="alert alert-warning">
            Tidak ada periode aktif. Assignment manual masih dapat dilihat, tetapi generator memerlukan periode aktif.
            <a href="{{ route('assessment-cycle.periods.create') }}" class="btn btn-sm btn-warning ml-2">Buat Periode</a>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ $summary['total'] }}" text="Total Assignment" icon="fas fa-clipboard-list" theme="primary"/></div>
        <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ $summary['submitted'] }}" text="Submitted" icon="fas fa-check-circle" theme="success"/></div>
        <div class="col-lg-4 col-12"><x-adminlte-small-box title="{{ $summary['pending'] }}" text="Pending" icon="fas fa-hourglass-half" theme="warning"/></div>
    </div>

    <x-adminlte-card title="Generate Assignments" theme="info" icon="fas fa-magic">
        <div class="row">
            @foreach ([
                'generate-self' => ['label' => 'Generate Self Assignments', 'icon' => 'fas fa-user'],
                'generate-supervisor' => ['label' => 'Generate Supervisor Assignments', 'icon' => 'fas fa-user-tie'],
                'generate-subordinate' => ['label' => 'Generate Subordinate Assignments', 'icon' => 'fas fa-users'],
            ] as $route => $meta)
                <div class="col-md-4">
                    <form method="POST" action="{{ route('assessment-cycle.assign-assessors.'.$route) }}">
                        @csrf
                        <div class="input-group mb-2">
                            <select name="assessment_period_id" class="form-control">
                                @foreach ($periods as $period)
                                    <option value="{{ $period->id }}" @selected((int) request('assessment_period_id') === $period->id) @disabled($period->status !== 'active')>
                                        {{ $period->name }} - {{ ucfirst($period->status) }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-info">
                                    <i class="{{ $meta['icon'] }} mr-1"></i> {{ $meta['label'] }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </x-adminlte-card>

    <x-adminlte-card title="Assignment List" theme="primary" icon="fas fa-user-check">
        <form method="GET" action="{{ route('assessment-cycle.assign-assessors.index') }}" class="mb-3">
            <div class="row">
                <div class="col-md-3">
                    <select name="assessment_period_id" class="form-control">
                        <option value="">All periods</option>
                        @foreach ($periods as $period)
                            <option value="{{ $period->id }}" @selected((int) request('assessment_period_id') === $period->id)>
                                {{ $period->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-control">
                        <option value="">All status</option>
                        @foreach (['pending', 'submitted'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="assessor_type" class="form-control">
                        <option value="">All types</option>
                        @foreach (['supervisor', 'peer', 'subordinate', 'self'] as $type)
                            <option value="{{ $type }}" @selected(request('assessor_type') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="department_id" class="form-control">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-block" type="submit">
                        <i class="fas fa-filter mr-1"></i> Filter
                    </button>
                </div>
            </div>
            <a href="{{ route('assessment-cycle.assign-assessors.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filters</a>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Type</th>
                        <th>Assessor</th>
                        <th>Assessee</th>
                        <th>Status</th>
                        <th>Submitted At</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $assignment)
                        <tr>
                            <td>{{ $assignment->assessmentPeriod->name }}</td>
                            <td><span class="badge badge-info">{{ ucfirst($assignment->assessor_type) }}</span></td>
                            <td>
                                {{ $assignment->assessor->name }}
                                <div class="small text-muted">{{ $assignment->assessor->department?->name }}</div>
                            </td>
                            <td>
                                {{ $assignment->assessee->name }}
                                <div class="small text-muted">{{ $assignment->assessee->department?->name }}</div>
                            </td>
                            <td>
                                <span class="badge badge-{{ $assignment->status === 'pending' ? 'warning' : 'success' }}">
                                    {{ ucfirst($assignment->status) }}
                                </span>
                            </td>
                            <td>{{ $assignment->submitted_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="text-right">
                                @if ($assignment->status === 'pending')
                                    <a href="{{ route('assessment-cycle.assign-assessors.edit', $assignment) }}" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('assessment-cycle.assign-assessors.destroy', $assignment) }}" class="d-inline"
                                        onsubmit="return confirm('Delete this pending assignment?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted">Locked</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No assignments found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $assignments->links() }}
    </x-adminlte-card>
    </div>
@stop

@section('css')
    <style>
        @media (max-width: 575.98px) {
            .assignments-index-page .card-body {
                overflow-x: hidden;
            }

            .assignments-index-page .table-responsive {
                max-width: 100%;
                overflow-x: auto;
            }
        }
    </style>
@stop
