@extends('adminlte::page')

@section('title', 'User & Role Management')

@section('content_header')
    <div>
        <h1 class="m-0">User & Role Management</h1>
        <p class="text-muted mb-0">Hak akses diterapkan berdasarkan role efektif. Permission individual tidak digunakan.</p>
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    @include('partials.flash')

    @if ($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <x-adminlte-card title="Filter Pengguna" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('master-data.users.index') }}">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-2">
                    <label for="search">NIP, Nama, atau Email</label>
                    <input id="search" name="search" value="{{ request('search') }}" class="form-control" maxlength="255">
                </div>
                <div class="col-lg-2 col-md-6 mb-2">
                    <label for="department_id">Departemen</label>
                    <select id="department_id" name="department_id" class="form-control">
                        <option value="">Semua</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 mb-2">
                    <label for="effective_role">Role Efektif</label>
                    <select id="effective_role" name="effective_role" class="form-control">
                        <option value="">Semua</option>
                        @foreach ([...$assignableRoles, 'it_admin'] as $role)
                            <option value="{{ $role }}" @selected(request('effective_role') === $role)>
                                {{ str($role)->replace('_', ' ')->title() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 mb-2">
                    <label for="role_source">Sumber Role</label>
                    <select id="role_source" name="role_source" class="form-control">
                        <option value="">Semua</option>
                        <option value="automatic" @selected(request('role_source') === 'automatic')>Automatic</option>
                        <option value="manual" @selected(request('role_source') === 'manual')>Manual Override</option>
                        <option value="protected_it_admin" @selected(request('role_source') === 'protected_it_admin')>Protected IT Admin</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 mb-2">
                    <label for="account_status">Status Akun</label>
                    <select id="account_status" name="account_status" class="form-control">
                        <option value="">Semua</option>
                        <option value="provisioned" @selected(request('account_status') === 'provisioned')>User tersedia</option>
                        <option value="unprovisioned" @selected(request('account_status') === 'unprovisioned')>Belum diprovisi</option>
                        <option value="inactive" @selected(request('account_status') === 'inactive')>Pegawai nonaktif</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search mr-1"></i>Terapkan</button>
            <a href="{{ route('master-data.users.index') }}" class="btn btn-outline-secondary">Reset</a>
        </form>
    </x-adminlte-card>

    <x-adminlte-card title="Daftar User dan Role" theme="primary" icon="fas fa-users-cog">
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>NIP / Pegawai</th>
                        <th>Unit dan Jabatan</th>
                        <th>Status</th>
                        <th>Role Otomatis</th>
                        <th>Role Manual</th>
                        <th>Role Efektif</th>
                        <th>Sumber</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $row)
                        @php
                            $employee = $row['employee'];
                            $isCurrentAccount = (int) $employee->user_id === (int) auth()->id();
                            $isProtectedItAdmin = $row['source'] === 'protected_it_admin';
                            $canAssign = ! $isCurrentAccount && ! $isProtectedItAdmin && $employee->employment_status === 'active';
                            $sourceLabel = match ($row['source']) {
                                'manual' => 'Manual Override',
                                'protected_it_admin' => 'Protected IT Admin',
                                default => 'Automatic',
                            };
                            $accountLabel = match ($row['accountStatus']) {
                                'inactive' => 'Pegawai nonaktif',
                                'unprovisioned' => 'Belum diprovisi / Belum pernah login',
                                default => 'Aktif / User tersedia',
                            };
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $employee->employee_number }}</strong><br>
                                {{ $employee->name }}<br>
                                <small class="text-muted">{{ $employee->email ?: '-' }}</small>
                            </td>
                            <td>
                                {{ $employee->department?->name ?? '-' }}<br>
                                <small class="text-muted">{{ $employee->position?->name ?? '-' }}</small>
                            </td>
                            <td>
                                <span class="badge badge-{{ $row['accountStatus'] === 'provisioned' ? 'success' : ($row['accountStatus'] === 'inactive' ? 'danger' : 'secondary') }}">
                                    {{ $accountLabel }}
                                </span>
                            </td>
                            <td>{{ str($row['automaticRole'])->replace('_', ' ')->title() }}</td>
                            <td>{{ $employee->role_override ? str($employee->role_override)->replace('_', ' ')->title() : '-' }}</td>
                            <td><span class="badge badge-info">{{ str($row['effectiveRole'])->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $sourceLabel }}</td>
                            <td class="text-right text-nowrap">
                                @if ($canAssign)
                                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#roleModal{{ $employee->id }}">
                                        <i class="fas fa-user-edit mr-1"></i>Ubah Role
                                    </button>
                                    @if ($employee->role_override)
                                        <form method="POST"
                                            action="{{ route('master-data.users.role.reset', $employee) }}"
                                            class="d-inline"
                                            onsubmit="return confirm('Hapus override dan gunakan role otomatis?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                Gunakan Role Otomatis
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <button type="button" class="btn btn-sm btn-secondary" disabled>
                                        {{ $isCurrentAccount ? 'Akun saat ini' : ($isProtectedItAdmin ? 'IT Admin dilindungi' : 'Pegawai nonaktif') }}
                                    </button>
                                @endif
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted">Tidak ada pegawai yang sesuai dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $employees->links() }}
    </x-adminlte-card>

    @foreach ($employees as $row)
        @php
            $employee = $row['employee'];
            $canAssign = (int) $employee->user_id !== (int) auth()->id()
                && $row['source'] !== 'protected_it_admin'
                && $employee->employment_status === 'active';
        @endphp
        @if ($canAssign)
            <div class="modal fade" id="roleModal{{ $employee->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('master-data.users.role.update', $employee) }}">
                            @csrf
                            @method('PATCH')
                            <div class="modal-header">
                                <h5 class="modal-title">Ubah Role {{ $employee->name }}</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <dl class="row">
                                    <dt class="col-sm-5">NIP</dt><dd class="col-sm-7">{{ $employee->employee_number }}</dd>
                                    <dt class="col-sm-5">Role otomatis</dt><dd class="col-sm-7">{{ str($row['automaticRole'])->replace('_', ' ')->title() }}</dd>
                                    <dt class="col-sm-5">Role manual</dt><dd class="col-sm-7">{{ $employee->role_override ? str($employee->role_override)->replace('_', ' ')->title() : '-' }}</dd>
                                    <dt class="col-sm-5">Role efektif</dt><dd class="col-sm-7">{{ str($row['effectiveRole'])->replace('_', ' ')->title() }}</dd>
                                </dl>
                                <div class="form-group">
                                    <label for="role_{{ $employee->id }}">Role baru</label>
                                    <select id="role_{{ $employee->id }}" name="role" class="form-control" required>
                                        @foreach ($assignableRoles as $role)
                                            <option value="{{ $role }}" @selected($employee->role_override === $role)>
                                                {{ str($role)->replace('_', ' ')->title() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="alert alert-warning mb-0">
                                    Perubahan role memengaruhi dashboard, menu, dan halaman yang dapat diakses pengguna.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary">Simpan Role</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach

    <x-adminlte-card title="Ringkasan Hak Akses Role" theme="info" icon="fas fa-shield-alt">
        <p class="text-muted">Tabel ini bersifat informasi. Hak akses mengikuti middleware, Gate, dan menu aplikasi.</p>
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr><th style="width: 18%">Role</th><th>Hak Akses</th></tr>
                </thead>
                <tbody>
                    @foreach ($accessMatrix as $role => $access)
                        <tr>
                            <td>
                                <strong>{{ str($role)->replace('_', ' ')->title() }}</strong>
                                @if ($role === 'it_admin')
                                    <span class="badge badge-secondary">Internal / Protected</span>
                                @endif
                            </td>
                            <td>{{ implode(' · ', $access) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-adminlte-card>
@stop
