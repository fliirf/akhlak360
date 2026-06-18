@extends('adminlte::page')

@section('title', 'Konfirmasi Logout')

@section('content_header')
    <h1 class="m-0">Konfirmasi Logout</h1>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <x-adminlte-card title="Keluar dari AKHLAK360" theme="warning" icon="fas fa-sign-out-alt">
        <p>Apakah Anda yakin ingin mengakhiri sesi Company SSO?</p>

        <div class="d-flex justify-content-between">
            <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt mr-1"></i> Ya, Logout
                </button>
            </form>
        </div>
    </x-adminlte-card>
@stop
