@extends('adminlte::page')

@section('title', 'Profil Saya')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="m-0">Profil Saya</h1>
            <p class="text-muted mb-0">Kelola informasi akun dan keamanan akses.</p>
        </div>
        <span class="badge badge-primary mt-3 mt-md-0 px-3 py-2">{{ $user->email }}</span>
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <x-adminlte-card title="Informasi Profil" theme="primary" icon="fas fa-user-edit">
                @include('profile.partials.update-profile-information-form')
            </x-adminlte-card>

            <x-adminlte-card title="Keamanan Akun" theme="warning" icon="fas fa-lock">
                @include('profile.partials.update-password-form')
            </x-adminlte-card>
        </div>
        <div class="col-lg-4">
            <x-adminlte-card title="Status Akun" theme="success" icon="fas fa-id-card">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <th>Nama</th>
                            <td>{{ $user->name }}</td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td>{{ $user->email }}</td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td><span class="badge badge-success">Aktif</span></td>
                        </tr>
                        <tr><th>Role</th><td>{{ ucfirst(str_replace('_', ' ', $user->role)) }}</td></tr>
                        <tr><th>Login terakhir</th><td>{{ $user->last_login_at?->format('d M Y H:i') ?? '-' }}</td></tr>
                    </tbody>
                </table>
            </x-adminlte-card>

            <x-adminlte-card title="Profil Kepegawaian" theme="info" icon="fas fa-address-card">
                @if ($user->employee)
                    <table class="table table-sm mb-0">
                        <tr><th>NIP</th><td>{{ $user->employee->employee_number }}</td></tr>
                        <tr><th>Departemen</th><td>{{ $user->employee->department?->name ?? '-' }}</td></tr>
                        <tr><th>Jabatan</th><td>{{ $user->employee->position?->name ?? '-' }}</td></tr>
                        <tr><th>Supervisor</th><td>{{ $user->employee->supervisor?->name ?? '-' }}</td></tr>
                        <tr><th>Status kerja</th><td>{{ ucfirst($user->employee->employment_status) }}</td></tr>
                        <tr><th>HRIS ID</th><td>{{ $user->employee->hris_external_id ?? '-' }}</td></tr>
                        <tr><th>Sinkronisasi</th><td>{{ $user->employee->last_synced_at?->format('d M Y H:i') ?? '-' }}</td></tr>
                    </table>
                @else
                    <div class="alert alert-light border mb-0">Akun ini belum terhubung dengan profil pegawai.</div>
                @endif
            </x-adminlte-card>

            <x-adminlte-card title="Zona Berisiko" theme="danger" icon="fas fa-exclamation-triangle">
                @include('profile.partials.delete-user-form')
            </x-adminlte-card>
        </div>
    </div>
@stop
