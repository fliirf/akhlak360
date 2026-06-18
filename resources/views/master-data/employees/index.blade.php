@extends('adminlte::page')

@section('title', 'Employees')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">Employees</h1>
        <a href="{{ route('master-data.employees.create') }}" class="btn btn-primary">
            <i class="fas fa-plus mr-1"></i> Create Employee
        </a>
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    @include('partials.flash')

    <x-adminlte-card title="Employee List" theme="primary" icon="fas fa-id-badge">
        <form method="GET" action="{{ route('master-data.employees.index') }}" class="mb-3">
            <div class="row">
                <div class="col-md-5">
                    <div class="form-group">
                        <input name="search" type="text" value="{{ request('search') }}" class="form-control"
                            placeholder="Search name, employee number, or email">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <select name="department_id" class="form-control">
                            <option value="">All departments</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <select name="employment_status" class="form-control">
                            <option value="">All status</option>
                            <option value="active" @selected(request('employment_status') === 'active')>Active</option>
                            <option value="inactive" @selected(request('employment_status') === 'inactive')>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-block" type="submit">
                        <i class="fas fa-search mr-1"></i> Search
                    </button>
                </div>
            </div>
            <a href="{{ route('master-data.employees.index') }}" class="btn btn-sm btn-outline-secondary">
                Reset Filters
            </a>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Supervisor</th>
                        <th>Status</th>
                        <th>SSO Personal</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>{{ $employee->employee_number }}</td>
                            <td>{{ $employee->name }}</td>
                            <td>{{ $employee->email ?? '-' }}</td>
                            <td>{{ $employee->department->name }}</td>
                            <td>{{ $employee->position?->name ?? '-' }}</td>
                            <td>{{ $employee->supervisor?->name ?? '-' }}</td>
                            <td>
                                <span class="badge badge-{{ $employee->employment_status === 'active' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($employee->employment_status) }}
                                </span>
                            </td>
                            <td>
                                @if ($employee->sso_code_generated_at)
                                    <span class="badge badge-success">Ready</span>
                                    <div class="small text-muted">{{ $employee->sso_code_generated_at->format('d M Y H:i') }}</div>
                                @else
                                    <span class="badge badge-warning">Belum dibuat</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('master-data.employees.sso-code', $employee) }}" class="d-inline"
                                    onsubmit="return confirm('Generate kode SSO personal baru? Kode lama akan langsung tidak berlaku.');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-info" title="Generate/Reset Kode SSO"
                                        @disabled($employee->employment_status !== 'active')>
                                        <i class="fas fa-key"></i>
                                    </button>
                                </form>
                                <a href="{{ route('master-data.employees.edit', $employee) }}" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="{{ route('master-data.employees.destroy', $employee) }}" class="d-inline"
                                    onsubmit="return confirm('Deactivate this employee?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" @disabled($employee->employment_status === 'inactive')>
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted">No employees found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $employees->links() }}
    </x-adminlte-card>
@stop
