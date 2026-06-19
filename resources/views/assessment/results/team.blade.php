@extends('adminlte::page')

@section('title', 'Team Results')

@section('content_header')
    <h1 class="m-0">Team Results</h1>
    <p class="text-muted mb-0">Aggregated assessment results for direct reports. Individual assessor responses remain confidential.</p>
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
                <div class="col-md-2"><button type="submit" class="btn btn-primary btn-block">Terapkan</button></div>
            </div>
        </form>
    </x-adminlte-card>

    <div class="alert alert-info">
        Results are grouped by assessor type across {{ $teamCount }} direct reports. No individual peer or subordinate score and no assessor identity is shown.
    </div>

    @if ($subordinateFeedback->isNotEmpty())
        <x-adminlte-card title="Feedback dari Tim/Bawahan" theme="info" icon="fas fa-comment-alt">
            <p class="text-muted">Feedback ditampilkan secara anonim untuk menjaga kerahasiaan pemberi masukan.</p>
            @foreach ($subordinateFeedback as $feedback)
                <div class="callout callout-info">
                    <p class="mb-0" style="white-space: pre-line">{{ $feedback }}</p>
                </div>
            @endforeach
        </x-adminlte-card>
    @endif

    <x-adminlte-card title="Aggregated Scores by Assessor Type" theme="success" icon="fas fa-users">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead><tr><th>Assessor Type</th><th class="text-right">Submitted Assignments</th><th class="text-right">Average Score</th></tr></thead>
                <tbody>
                    @forelse ($aggregates as $aggregate)
                        <tr>
                            <td>{{ ucfirst($aggregate->assessor_type) }}</td>
                            <td class="text-right">{{ $aggregate->assignment_count }}</td>
                            <td class="text-right">{{ number_format((float) $aggregate->average_score, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">No aggregated team results are available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte-card>
@stop
