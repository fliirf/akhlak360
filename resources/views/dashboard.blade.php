@extends('adminlte::page')

@section('title', 'Dashboard AKHLAK 360')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="m-0">Dashboard Penilaian 360</h1>
            <p class="text-muted mb-0">Sistem Penilaian 360 Core Values AKHLAK</p>
        </div>
        <span class="badge badge-success mt-3 mt-md-0 px-3 py-2">Periode 2026 Aktif</span>
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <div class="row">
        <div class="col-lg-3 col-6">
            <x-adminlte-small-box title="128" text="Pegawai Dinilai" icon="fas fa-users" theme="primary"/>
        </div>
        <div class="col-lg-3 col-6">
            <x-adminlte-small-box title="84%" text="Progress Penilaian" icon="fas fa-tasks" theme="success"/>
        </div>
        <div class="col-lg-3 col-6">
            <x-adminlte-small-box title="6" text="Core Values" icon="fas fa-gem" theme="warning"/>
        </div>
        <div class="col-lg-3 col-6">
            <x-adminlte-small-box title="21" text="Butuh Tindak Lanjut" icon="fas fa-clipboard-check" theme="danger"/>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <x-adminlte-card title="Tren Skor Core Values" theme="primary" icon="fas fa-chart-line">
                <canvas id="akhlakScoreChart" height="120"></canvas>
            </x-adminlte-card>
        </div>
        <div class="col-lg-4">
            <x-adminlte-card title="Ringkasan Peran Penilai" theme="info" icon="fas fa-user-check">
                <div class="info-box bg-light">
                    <span class="info-box-icon bg-primary"><i class="fas fa-user"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Self Assessment</span>
                        <span class="info-box-number">94%</span>
                        <div class="progress">
                            <div class="progress-bar bg-primary" style="width: 94%"></div>
                        </div>
                    </div>
                </div>
                <div class="info-box bg-light">
                    <span class="info-box-icon bg-success"><i class="fas fa-user-friends"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Peer Review</span>
                        <span class="info-box-number">78%</span>
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width: 78%"></div>
                        </div>
                    </div>
                </div>
                <div class="info-box bg-light mb-0">
                    <span class="info-box-icon bg-warning"><i class="fas fa-sitemap"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Atasan & Bawahan</span>
                        <span class="info-box-number">81%</span>
                        <div class="progress">
                            <div class="progress-bar bg-warning" style="width: 81%"></div>
                        </div>
                    </div>
                </div>
            </x-adminlte-card>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <x-adminlte-card title="Status Core Values AKHLAK" theme="success" icon="fas fa-table">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Core Value</th>
                                <th>Rata-rata</th>
                                <th>Status</th>
                                <th class="text-right">Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ([
                                ['name' => 'Amanah', 'score' => 88, 'status' => 'Unggul', 'theme' => 'success'],
                                ['name' => 'Kompeten', 'score' => 82, 'status' => 'Baik', 'theme' => 'primary'],
                                ['name' => 'Harmonis', 'score' => 79, 'status' => 'Dipantau', 'theme' => 'warning'],
                                ['name' => 'Loyal', 'score' => 85, 'status' => 'Baik', 'theme' => 'primary'],
                                ['name' => 'Adaptif', 'score' => 76, 'status' => 'Dipantau', 'theme' => 'warning'],
                                ['name' => 'Kolaboratif', 'score' => 90, 'status' => 'Unggul', 'theme' => 'success'],
                            ] as $value)
                                <tr>
                                    <td>{{ $value['name'] }}</td>
                                    <td>{{ $value['score'] }}</td>
                                    <td><span class="badge badge-{{ $value['theme'] }}">{{ $value['status'] }}</span></td>
                                    <td class="text-right">
                                        <div class="progress progress-sm">
                                            <div class="progress-bar bg-{{ $value['theme'] }}" style="width: {{ $value['score'] }}%"></div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-adminlte-card>
        </div>
        <div class="col-lg-5">
            <x-adminlte-card title="Akses Cepat" theme="secondary" icon="fas fa-bolt">
                <p class="text-muted">Gunakan halaman fungsional berikut untuk menindaklanjuti hasil penilaian.</p>
                <a href="{{ route('notifications.index') }}" class="btn btn-primary mr-2">
                    <i class="fas fa-bell mr-1"></i> Notifikasi
                </a>
                <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-user-cog mr-1"></i> Profil
                </a>
            </x-adminlte-card>
        </div>
    </div>
@stop

@section('js')
    <script>
        const akhlakScoreChart = document.getElementById('akhlakScoreChart');

        if (akhlakScoreChart && window.Chart) {
            new Chart(akhlakScoreChart, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
                    datasets: [{
                        label: 'Skor AKHLAK',
                        data: [74, 78, 80, 83, 86, 88],
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.12)',
                        pointBackgroundColor: '#007bff',
                        fill: true,
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                }
            });
        }
    </script>
@stop
