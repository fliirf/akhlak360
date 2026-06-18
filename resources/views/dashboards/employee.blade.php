@extends('adminlte::page')

@section('title', 'Employee Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0">Employee Dashboard</h1>
            <p class="text-muted mb-0">{{ $employee?->name ?? 'No employee profile linked' }}</p>
        </div>
        <span class="badge badge-{{ $activePeriod ? 'success' : 'secondary' }} px-3 py-2">{{ $activePeriod?->name ?? 'No Active Period' }}</span>
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    @if (! $employee)
        <div class="alert alert-warning">Akun Anda belum terhubung ke profil pegawai. Hubungi Admin HR agar hasil personal dapat ditampilkan.</div>
    @elseif (! $activePeriod)
        <div class="alert alert-info">Belum ada periode penilaian aktif.</div>
    @elseif (! $result)
        <div class="alert alert-info">Hasil periode ini belum tersedia. Selesaikan assignment atau tunggu Admin HR melakukan kalkulasi hasil.
            <a class="btn btn-sm btn-info ml-2" href="{{ route('assessment.pending.index') }}">Lihat Assessment</a>
        </div>
    @endif
    <div class="row">
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['pendingAssessments'] }}" text="Pending Assessments" icon="fas fa-hourglass-half" theme="warning"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['completedAssessments'] }}" text="Completed Assessments" icon="fas fa-check-circle" theme="success"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $result?->final_score ?? '-' }}" text="Personal Final Score" icon="fas fa-star" theme="primary"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $result?->gap_score ?? '-' }}" text="Self vs Others Gap" icon="fas fa-balance-scale" theme="info"/></div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <x-adminlte-card title="Personal Result" theme="primary" icon="fas fa-chart-bar">
                @if ($result)
                    <div class="row mb-3">
                        <div class="col-md-4"><strong>Category:</strong> <span class="badge badge-info">{{ $result->category }}</span></div>
                        <div class="col-md-4"><strong>Talent:</strong> <span class="badge badge-success">{{ $result->talent_mapping_category }}</span></div>
                        <div class="col-md-4"><strong>Period:</strong> {{ $result->assessmentPeriod?->name }}</div>
                    </div>
                    <canvas id="personalCoreChart" height="145"></canvas>
                @else
                    <div class="alert alert-light mb-0">No personal result available yet.</div>
                @endif
            </x-adminlte-card>
        </div>
        <div class="col-lg-5">
            <x-adminlte-card title="Self vs Others" theme="info" icon="fas fa-chart-pie">
                @if ($result && ($result->self_score !== null || $result->others_score !== null))
                    <canvas id="gapChart" height="180"></canvas>
                @else
                    <div class="alert alert-light mb-0">No gap summary available yet.</div>
                @endif
            </x-adminlte-card>
        </div>
    </div>

    <x-adminlte-card title="IDP Recommendation" theme="warning" icon="fas fa-lightbulb">
        @if ($idp)
            <dl class="row mb-0">
                <dt class="col-md-3">Weakest Core Value</dt><dd class="col-md-9"><span class="badge badge-danger">{{ $idp->weakest_core_value }}</span></dd>
                <dt class="col-md-3">Recommendation</dt><dd class="col-md-9">{{ $idp->recommendation }}</dd>
                <dt class="col-md-3">Action Plan</dt><dd class="col-md-9">{{ $idp->action_plan ?? '-' }}</dd>
                <dt class="col-md-3">Status</dt><dd class="col-md-9"><span class="badge badge-info">{{ ucfirst(str_replace('_', ' ', $idp->status)) }}</span></dd>
                <dt class="col-md-3">Due Date</dt><dd class="col-md-9">{{ $idp->due_date?->format('d M Y') ?? '-' }}</dd>
            </dl>
        @else
            <div class="alert alert-light mb-0">No IDP recommendation available yet.</div>
        @endif
    </x-adminlte-card>
@stop

@section('js')
    <script>
        const personalCoreData = {{ Illuminate\Support\Js::from($personalCoreChart) }};
        const gapData = {{ Illuminate\Support\Js::from($gapChart) }};

        if (document.getElementById('personalCoreChart')) {
            new Chart(document.getElementById('personalCoreChart'), {
                type: 'bar',
                data: { labels: personalCoreData.labels, datasets: [{ label: 'Score', data: personalCoreData.data, backgroundColor: '#007bff' }] },
                options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 5 } } }
            });
        }
        if (document.getElementById('gapChart')) {
            new Chart(document.getElementById('gapChart'), {
                type: 'bar',
                data: { labels: gapData.labels, datasets: [{ label: 'Score', data: gapData.data, backgroundColor: ['#17a2b8', '#6c757d'] }] },
                options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 5 } } }
            });
        }
    </script>
@stop
