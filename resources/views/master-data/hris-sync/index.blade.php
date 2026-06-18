@extends('adminlte::page')

@section('title', 'HRIS Sync')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="m-0">HRIS Sync</h1>
            <p class="text-muted mb-0">Simulated HRIS CSV import and manual synchronization log.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="{{ route('master-data.hris-sync.sample') }}" class="btn btn-outline-success">
                <i class="fas fa-download mr-1"></i> Sample CSV
            </a>
            <form method="POST" action="{{ route('master-data.hris-sync.manual') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-info" onclick="return confirm('Run manual HRIS sync simulation?')">
                    <i class="fas fa-sync-alt mr-1"></i> Manual Sync
                </button>
            </form>
        </div>
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    @include('partials.flash')

    <div class="row">
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['total'] }}" text="Total Sinkronisasi" icon="fas fa-sync" theme="primary"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['successful'] }}" text="Berhasil" icon="fas fa-check-circle" theme="success"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['failed'] }}" text="Gagal" icon="fas fa-times-circle" theme="danger"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['latest']?->format('d M H:i') ?? '-' }}" text="Sinkronisasi Terakhir" icon="fas fa-clock" theme="info"/></div>
    </div>

    <x-adminlte-card title="Filter Sync History" theme="secondary" icon="fas fa-filter">
        <form method="GET" action="{{ route('master-data.hris-sync.index') }}">
            <div class="row">
                <div class="col-md-3"><input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search log message"></div>
                <div class="col-md-2">
                    <select name="sync_type" class="form-control">
                        <option value="">All types</option>
                        <option value="import_csv" @selected(request('sync_type') === 'import_csv')>CSV Import</option>
                        <option value="manual_sync" @selected(request('sync_type') === 'manual_sync')>Manual Sync</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-control">
                        <option value="">All statuses</option>
                        <option value="success" @selected(request('status') === 'success')>Success</option>
                        <option value="failed" @selected(request('status') === 'failed')>Failed</option>
                    </select>
                </div>
                <div class="col-md-2"><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" aria-label="From date"></div>
                <div class="col-md-2"><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control" aria-label="To date"></div>
                <div class="col-md-1"><button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i></button></div>
            </div>
            <a href="{{ route('master-data.hris-sync.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filters</a>
        </form>
    </x-adminlte-card>

    <div class="row">
        <div class="col-lg-5">
            <x-adminlte-card title="Import Employee CSV" theme="primary" icon="fas fa-file-csv">
                <form method="POST" action="{{ route('master-data.hris-sync.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label for="csv_file">CSV File</label>
                        <input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv"
                            class="form-control-file @error('csv_file') is-invalid @enderror">
                        @error('csv_file')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload mr-1"></i> Import CSV
                    </button>
                </form>
            </x-adminlte-card>
        </div>
        <div class="col-lg-7">
            <x-adminlte-card title="CSV Columns" theme="secondary" icon="fas fa-info-circle">
                <p class="mb-2">Required columns: <code>employee_number</code>, <code>name</code>, <code>department</code>.</p>
                <p class="mb-0">Optional columns: <code>email</code>, <code>department_code</code>, <code>position</code>, <code>position_level</code>, <code>supervisor_employee_number</code>, <code>employment_status</code>, <code>hris_external_id</code>.</p>
            </x-adminlte-card>
        </div>
    </div>

    <x-adminlte-card title="Sync Logs" theme="success" icon="fas fa-history">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Success</th>
                        <th class="text-right">Failed</th>
                        <th>Message</th>
                        <th>Synced By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $log->sync_type)) }}</td>
                            <td>
                                <span class="badge badge-{{ $log->status === 'success' ? 'success' : 'danger' }}">
                                    {{ ucfirst($log->status) }}
                                </span>
                            </td>
                            <td class="text-right">{{ $log->total_records }}</td>
                            <td class="text-right">{{ $log->success_records }}</td>
                            <td class="text-right">{{ $log->failed_records }}</td>
                            <td>{{ $log->message ?? '-' }}</td>
                            <td>{{ $log->syncedBy?->name ?? 'System' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">No HRIS sync logs found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $logs->links() }}
        </div>
    </x-adminlte-card>
@stop
