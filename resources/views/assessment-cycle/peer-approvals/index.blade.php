@extends('adminlte::page')

@section('title', 'Peer Approval')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0">Peer Approval</h1>
            <p class="text-muted mb-0">Proposal dan persetujuan peer assessor untuk penilaian 360.</p>
        </div>
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    @include('partials.flash')

    @if (! $activePeriod)
        <div class="alert alert-warning">
            Tidak ada periode aktif. Proposal peer baru dinonaktifkan sampai Admin HR mengaktifkan periode.
            @if(auth()->user()->hasRole('admin_hr'))<a href="{{ route('assessment-cycle.periods.create') }}" class="btn btn-sm btn-warning ml-2">Buat Periode</a>@endif
        </div>
    @endif

    <div class="row">
        <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ $summary['pending'] }}" text="Pending" icon="fas fa-hourglass-half" theme="warning"/></div>
        <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ $summary['approved'] }}" text="Approved" icon="fas fa-check-circle" theme="success"/></div>
        <div class="col-lg-4 col-12"><x-adminlte-small-box title="{{ $summary['rejected'] }}" text="Rejected" icon="fas fa-times-circle" theme="danger"/></div>
    </div>

    @if (auth()->user()->hasRole('admin_hr'))
        <x-adminlte-card title="Propose Peer Assessor" theme="primary" icon="fas fa-user-plus">
            <form method="POST" action="{{ route('assessment-cycle.peer-approval.store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="assessment_period_id">Active Period</label>
                            <select id="assessment_period_id" name="assessment_period_id" class="form-control @error('assessment_period_id') is-invalid @enderror" required>
                                @foreach ($periods as $period)
                                    <option value="{{ $period->id }}" @selected(old('assessment_period_id', $activePeriod?->id) === $period->id) @disabled($period->status !== 'active')>
                                        {{ $period->name }} - {{ ucfirst($period->status) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('assessment_period_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="employee_id">Employee Assessed</label>
                            <select id="employee_id" name="employee_id" class="form-control @error('employee_id') is-invalid @enderror" required>
                                <option value="">Select employee</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((int) old('employee_id') === $employee->id)>
                                        {{ $employee->employee_number }} - {{ $employee->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('employee_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="peer_employee_id">Proposed Peer</label>
                            <select id="peer_employee_id" name="peer_employee_id" class="form-control @error('peer_employee_id') is-invalid @enderror" required>
                                <option value="">Select peer</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((int) old('peer_employee_id') === $employee->id)>
                                        {{ $employee->employee_number }} - {{ $employee->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('peer_employee_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <input id="notes" name="notes" type="text" value="{{ old('notes') }}" class="form-control" placeholder="Optional note">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" @disabled(! $activePeriod)>
                    <i class="fas fa-paper-plane mr-1"></i> Propose Peer
                </button>
            </form>
        </x-adminlte-card>
    @endif

    <x-adminlte-card title="{{ auth()->user()->hasRole('supervisor') ? 'Pending Peer Approvals for My Team' : 'Peer Approval List' }}" theme="success" icon="fas fa-user-check">
        <form method="GET" action="{{ route('assessment-cycle.peer-approval.index') }}" class="mb-3">
            <div class="row">
                <div class="col-md-4">
                    <select name="assessment_period_id" class="form-control">
                        <option value="">All periods</option>
                        @foreach ($periods as $period)
                            <option value="{{ $period->id }}" @selected((int) request('assessment_period_id') === $period->id)>
                                {{ $period->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-control">
                        <option value="">All status</option>
                        @foreach (['pending', 'approved', 'rejected'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-block" type="submit">
                        <i class="fas fa-filter mr-1"></i> Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="{{ route('assessment-cycle.peer-approval.index') }}" class="btn btn-outline-secondary btn-block">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Employee</th>
                        <th>Proposed Peer</th>
                        <th>Supervisor</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($peerApprovals as $approval)
                        <tr>
                            <td>{{ $approval->assessmentPeriod->name }}</td>
                            <td>
                                {{ $approval->employee->name }}
                                <div class="small text-muted">{{ $approval->employee->department?->name }}</div>
                            </td>
                            <td>{{ $approval->peerEmployee?->name ?? '-' }}</td>
                            <td>{{ $approval->supervisorEmployee->name }}</td>
                            <td>
                                <span class="badge badge-{{ ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$approval->status] }}">
                                    {{ ucfirst($approval->status) }}
                                </span>
                            </td>
                            <td>{{ $approval->notes ?? '-' }}</td>
                            <td class="text-right">
                                @if (auth()->user()->hasRole('supervisor') && $approval->status === 'pending')
                                    <form method="POST" action="{{ route('assessment-cycle.peer-approval.approve', $approval) }}" class="mb-1">
                                        @csrf
                                        @method('PATCH')
                                        <div class="input-group input-group-sm">
                                            <input name="notes" type="text" class="form-control" placeholder="Approval notes">
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                    <form method="POST" action="{{ route('assessment-cycle.peer-approval.reject', $approval) }}">
                                        @csrf
                                        @method('PATCH')
                                        <div class="input-group input-group-sm">
                                            <input name="notes" type="text" class="form-control" placeholder="Rejection notes">
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No peer approvals found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $peerApprovals->links() }}
    </x-adminlte-card>
@stop
