@extends('adminlte::page')

@section('title', 'Audit Logs')

@section('content_header')
    <h1 class="m-0">Audit Logs</h1>
    <p class="text-muted mb-0">Trace user and system activity across AKHLAK 360 modules.</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <div class="row">
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['total'] }}" text="Log Terfilter" icon="fas fa-list" theme="primary"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['users'] }}" text="Pengguna Unik" icon="fas fa-users" theme="info"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['system'] }}" text="Aktivitas Sistem" icon="fas fa-cog" theme="secondary"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['today'] }}" text="Hari Ini" icon="fas fa-calendar-day" theme="success"/></div>
    </div>
    <x-adminlte-card title="Filters" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('audit-compliance.audit-logs.index') }}">
            <div class="row">
                <div class="col-md-3">
                    <select name="user_id" class="form-control">
                        <option value="">All users</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((int) request('user_id') === $user->id)>{{ $user->name }} - {{ $user->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="module" class="form-control">
                        <option value="">All modules</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="action" class="form-control">
                        <option value="">All actions</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <input name="date" type="date" value="{{ request('date') }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-block" type="submit">
                        <i class="fas fa-search mr-1"></i> Apply
                    </button>
                </div>
            </div>
            <a href="{{ route('audit-compliance.audit-logs.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filters</a>
        </form>
    </x-adminlte-card>

    <x-adminlte-card title="Audit Log List" theme="secondary" icon="fas fa-shield-alt">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('d M Y H:i:s') }}</td>
                            <td>{{ $log->user?->name ?? 'System' }}</td>
                            <td><span class="badge badge-info">{{ $log->module }}</span></td>
                            <td>{{ $log->action }}</td>
                            <td>{{ $log->description ?? '-' }}</td>
                            <td>{{ $log->ip_address ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No audit logs found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $logs->links() }}
        </div>
    </x-adminlte-card>
@stop
