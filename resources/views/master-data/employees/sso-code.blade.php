@extends('adminlte::page')

@section('title', 'Kode SSO Personal')

@section('content_header')
    <h1 class="m-0">Kode SSO Personal</h1>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <div class="alert alert-warning">
        <strong>Kode ini hanya ditampilkan pada halaman ini.</strong>
        Setelah meninggalkan halaman, kode tidak dapat dilihat kembali dan harus di-reset jika hilang.
    </div>

    <x-adminlte-card title="Kode untuk {{ $employee->employee_number }} - {{ $employee->name }}" theme="info" icon="fas fa-key">
        <p>Salin dan berikan kepada karyawan melalui kanal yang aman.</p>
        <div class="bg-light border rounded p-4 text-center mb-3">
            <code style="font-size: 1.6rem; letter-spacing: .08em;">{{ $plainCode }}</code>
        </div>
        <p class="text-muted mb-3">
            Kode lama sudah tidak berlaku. Sistem hanya menyimpan hash kode ini.
        </p>
        <a href="{{ route('master-data.employees.index', ['search' => $employee->employee_number]) }}" class="btn btn-primary">
            <i class="fas fa-check mr-1"></i> Saya Sudah Menyalin Kode
        </a>
    </x-adminlte-card>
@stop
