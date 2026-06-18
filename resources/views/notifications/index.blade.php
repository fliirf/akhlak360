@extends('adminlte::page')

@section('title', 'Notifications')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">Notifications</h1>
        <form method="POST" action="{{ route('notifications.mark-all-read') }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check-double mr-1"></i> Mark All as Read
            </button>
        </form>
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    @include('partials.flash')

    <div class="row">
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['total'] }}" text="Total Notifikasi" icon="fas fa-bell" theme="primary"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['unread'] }}" text="Belum Dibaca" icon="fas fa-envelope" theme="danger"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['reminders'] }}" text="Reminder" icon="fas fa-clock" theme="warning"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $summary['results'] }}" text="Hasil dan IDP" icon="fas fa-chart-bar" theme="success"/></div>
    </div>

    <x-adminlte-card title="Notification Center" theme="primary" icon="fas fa-bell">
        <form method="GET" action="{{ route('notifications.index') }}" class="mb-3">
            <div class="row">
                <div class="col-md-4">
                    <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari judul atau pesan">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control">
                        <option value="">Semua status</option>
                        <option value="unread" @selected(request('status') === 'unread')>Belum dibaca</option>
                        <option value="read" @selected(request('status') === 'read')>Sudah dibaca</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-control">
                        <option value="">Semua tipe</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary btn-block">Filter</button></div>
            </div>
            <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filters</a>
        </form>
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Type</th>
                        <th>Created</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($notifications as $notification)
                        <tr class="{{ $notification->read_at ? '' : 'font-weight-bold' }}">
                            <td>
                                <span class="badge badge-{{ $notification->read_at ? 'secondary' : 'danger' }}">
                                    {{ $notification->read_at ? 'Read' : 'Unread' }}
                                </span>
                            </td>
                            <td>{{ $notification->title }}</td>
                            <td>{{ $notification->message }}</td>
                            <td><span class="badge badge-info">{{ str_replace('_', ' ', $notification->type) }}</span></td>
                            <td>{{ $notification->created_at->format('d M Y H:i') }}</td>
                            <td class="text-right">
                                @unless ($notification->read_at)
                                    <form method="POST" action="{{ route('notifications.mark-read', $notification) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fas fa-check mr-1"></i> Mark as Read
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted">-</span>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">Tidak ada notifikasi untuk filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $notifications->links() }}
    </x-adminlte-card>
@stop
