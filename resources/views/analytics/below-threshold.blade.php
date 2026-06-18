@extends('adminlte::page')

@section('title', 'Di Bawah Threshold')

@section('content_header')
    <h1 class="m-0">Pegawai Di Bawah Threshold</h1>
    <p class="text-muted mb-0">Prioritas pengembangan berdasarkan hasil periode penilaian.</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <x-adminlte-card title="Filter Analitik" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('analytics.below-threshold.index') }}">
            <div class="row">
                <div class="col-md-5">
                    <select name="period_id" class="form-control">
                        @foreach ($periods as $item)
                            <option value="{{ $item->id }}" @selected($selectedPeriod === $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <select name="department_id" class="form-control">
                        <option value="">Semua departemen</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($selectedDepartment === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search mr-1"></i>Terapkan</button></div>
            </div>
            <a href="{{ route('analytics.below-threshold.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filters</a>
        </form>
    </x-adminlte-card>

    @if (! $period)
        <div class="alert alert-warning">
            Belum ada periode penilaian.
            @if (auth()->user()->hasRole('admin_hr'))
                <a class="btn btn-sm btn-warning ml-2" href="{{ route('assessment-cycle.periods.create') }}">Buat Periode</a>
            @endif
        </div>
    @else
        <div class="row">
            <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ number_format((float) $period->threshold_score, 2) }}" text="Threshold Periode" icon="fas fa-bullseye" theme="warning"/></div>
            <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ $results->count() }}" text="Ditampilkan di Halaman Ini" icon="fas fa-users" theme="danger"/></div>
            <div class="col-lg-4 col-12"><x-adminlte-small-box title="{{ method_exists($results, 'total') ? $results->total() : $results->count() }}" text="Total Di Bawah Threshold" icon="fas fa-exclamation-triangle" theme="danger"/></div>
        </div>

        <x-adminlte-card title="Daftar Prioritas Pengembangan" theme="danger" icon="fas fa-table">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead><tr><th>Pegawai</th><th>Departemen</th><th class="text-right">Skor Akhir</th><th>Core Value Terlemah</th><th>Kategori</th><th>Status IDP</th></tr></thead>
                    <tbody>
                        @forelse ($results as $result)
                            <tr>
                                <td>{{ $result->employee?->name ?? '-' }}</td>
                                <td>{{ $result->employee?->department?->name ?? '-' }}</td>
                                <td class="text-right"><span class="badge badge-danger">{{ number_format((float) $result->final_score, 2) }}</span></td>
                                <td>{{ $result->weakest_core_value_label ?? '-' }}</td>
                                <td><span class="badge badge-warning">{{ $result->category ?? '-' }}</span></td>
                                <td><span class="badge badge-{{ $result->idp_status_label ? 'info' : 'secondary' }}">{{ $result->idp_status_label ? ucfirst(str_replace('_', ' ', $result->idp_status_label)) : 'Belum tersedia' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">Tidak ada pegawai di bawah threshold untuk filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($results, 'links'))
                <div class="mt-3">{{ $results->links() }}</div>
            @endif
        </x-adminlte-card>
    @endif
@stop
