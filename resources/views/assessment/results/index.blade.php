@extends('adminlte::page')

@section('title', 'Hasil Penilaian')

@section('content_header')
    <h1 class="m-0">Hasil Penilaian</h1>
    <p class="text-muted mb-0">{{ $isSupervisor ? 'Hasil pribadi dan anggota tim langsung.' : 'Ringkasan hasil penilaian pribadi Anda.' }}</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <x-adminlte-card title="Filter Periode" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('assessment.results.index') }}">
            <div class="row">
                <div class="col-md-10">
                    <select name="period_id" class="form-control">
                        @foreach ($periods as $period)
                            <option value="{{ $period->id }}" @selected($selectedPeriod === $period->id)>{{ $period->name }} - {{ $period->semester }} {{ $period->year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search mr-1"></i>Terapkan</button></div>
            </div>
            <a href="{{ route('assessment.results.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filters</a>
        </form>
    </x-adminlte-card>

    @if ($periods->isEmpty())
        <div class="alert alert-info">Belum ada periode penilaian yang tersedia.</div>
    @endif

    @forelse ($results as $result)
        @php($idp = $result->employee?->idpRecommendations->first())
        <x-adminlte-card title="{{ $result->employee?->name ?? 'Pegawai' }} - {{ $result->assessmentPeriod?->name }}" theme="success" icon="fas fa-chart-bar">
            <div class="row">
                <div class="col-md-3">
                    <dl>
                        <dt>Departemen</dt><dd>{{ $result->employee?->department?->name ?? '-' }}</dd>
                        <dt>Skor Akhir</dt><dd><strong>{{ number_format((float) $result->final_score, 2) }}</strong></dd>
                        <dt>Kategori</dt><dd><span class="badge badge-info">{{ $result->category ?? '-' }}</span></dd>
                        <dt>Talent Mapping</dt><dd>{{ $result->talent_mapping_category ?? '-' }}</dd>
                    </dl>
                </div>
                <div class="col-md-5">
                    <div style="height: 220px"><canvas id="resultCoreChart{{ $result->id }}"></canvas></div>
                </div>
                <div class="col-md-4">
                    <table class="table table-sm">
                        <tr><th>Self</th><td class="text-right">{{ number_format((float) $result->self_score, 2) }}</td></tr>
                        <tr><th>Others</th><td class="text-right">{{ number_format((float) $result->others_score, 2) }}</td></tr>
                        <tr><th>Gap</th><td class="text-right">{{ number_format((float) $result->gap_score, 2) }}</td></tr>
                        <tr><th>IDP Terlemah</th><td>{{ $idp?->weakest_core_value ?? '-' }}</td></tr>
                        <tr><th>Status IDP</th><td>{{ $idp ? ucfirst(str_replace('_', ' ', $idp->status)) : '-' }}</td></tr>
                    </table>
                </div>
            </div>
        </x-adminlte-card>
    @empty
        @if ($periods->isNotEmpty())
            <div class="alert alert-light border">
                Belum ada hasil kalkulasi untuk periode terpilih. Hasil akan tersedia setelah assessment dikirim dan dikalkulasikan.
                <a href="{{ route('assessment.pending.index') }}" class="btn btn-sm btn-primary ml-2">Lihat Assessment</a>
            </div>
        @endif
    @endforelse

    {{ $results->links() }}
@stop

@section('js')
    <script>
        const resultCharts = {{ Illuminate\Support\Js::from($results->getCollection()->map(fn ($result) => [
            'id' => $result->id,
            'labels' => ['Amanah', 'Kompeten', 'Harmonis', 'Loyal', 'Adaptif', 'Kolaboratif'],
            'data' => [
                (float) $result->amanah_score, (float) $result->kompeten_score,
                (float) $result->harmonis_score, (float) $result->loyal_score,
                (float) $result->adaptif_score, (float) $result->kolaboratif_score,
            ],
        ])->values()) }};
        resultCharts.forEach(item => {
            const element = document.getElementById(`resultCoreChart${item.id}`);
            if (element) new Chart(element, {
                type: 'bar',
                data: { labels: item.labels, datasets: [{ label: 'Skor', data: item.data, backgroundColor: '#28a745' }] },
                options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 5 } } }
            });
        });
    </script>
@stop
