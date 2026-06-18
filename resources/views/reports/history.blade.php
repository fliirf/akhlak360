@extends('adminlte::page')

@section('title', 'Export History')

@section('content_header')
    <h1 class="m-0">Export History</h1>
    <p class="text-muted mb-0">Generated and failed report export activity.</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <x-adminlte-card title="Filter Riwayat" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('reports.history.index') }}">
            <div class="row">
                <div class="col-md-4">
                    <select name="period_id" class="form-control"><option value="">Semua periode</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected((int) request('period_id') === $period->id)>{{ $period->name }}</option>@endforeach</select>
                </div>
                <div class="col-md-3">
                    <select name="report_type" class="form-control"><option value="">Semua tipe</option>@foreach(['csv','excel','pdf'] as $type)<option value="{{ $type }}" @selected(request('report_type') === $type)>{{ strtoupper($type) }}</option>@endforeach</select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control"><option value="">Semua status</option><option value="generated" @selected(request('status') === 'generated')>Generated</option><option value="failed" @selected(request('status') === 'failed')>Failed</option></select>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary btn-block">Filter</button></div>
            </div>
            <a href="{{ route('reports.history.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filters</a>
        </form>
    </x-adminlte-card>
    <div class="row">
        <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ $summary['total'] }}" text="Total Export" icon="fas fa-file-export" theme="primary"/></div>
        <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ $summary['generated'] }}" text="Berhasil" icon="fas fa-check-circle" theme="success"/></div>
        <div class="col-lg-4 col-12"><x-adminlte-small-box title="{{ $summary['failed'] }}" text="Gagal" icon="fas fa-times-circle" theme="danger"/></div>
    </div>
    <x-adminlte-card title="Export History" theme="primary" icon="fas fa-history">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Period</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>File Path</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($exports as $export)
                        <tr>
                            <td>{{ $export->created_at->format('d M Y H:i') }}</td>
                            <td>{{ $export->user?->name ?? '-' }}</td>
                            <td>{{ $export->assessmentPeriod?->name ?? 'All periods' }}</td>
                            <td><span class="badge badge-info">{{ strtoupper($export->report_type) }}</span></td>
                            <td>
                                <span class="badge badge-{{ $export->status === 'generated' ? 'success' : 'danger' }}">
                                    {{ ucfirst($export->status) }}
                                </span>
                            </td>
                            <td>{{ $export->file_path ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No export activity found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $exports->links() }}
        </div>
    </x-adminlte-card>
@stop
