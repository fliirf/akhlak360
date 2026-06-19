@extends('adminlte::page')

@section('title', 'Fill Assessment')

@section('content_header')
    <div>
        <h1 class="m-0">Fill Assessment</h1>
        <p class="text-muted mb-0">
            {{ $assignment->assessmentPeriod->name }} · {{ ucfirst($assignment->assessor_type) }} assessment for {{ $assignment->assessee->name }}
        </p>
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    @include('partials.flash')

    @if ($errors->any())
        <div class="alert alert-danger">
            Some assessment answers are invalid. Final submission requires all 18 indicators.
        </div>
    @endif

    <div class="alert alert-info">
        <i class="fas fa-info-circle mr-1"></i>
        You may save an incomplete draft and edit it later. Only <strong>Submit Assessment</strong> completes this assignment.
    </div>

    @if ($hasDraft)
        <div class="alert alert-warning">
            <i class="fas fa-save mr-1"></i>
            <strong>Draft tersimpan &mdash; assessment belum dikirim.</strong>
        </div>
    @endif

    <form method="POST" action="{{ route('assessment.submit', $assignment) }}">
        @csrf

        @foreach ($indicators as $coreValue => $items)
            <x-adminlte-card :title="$coreValue" theme="primary" icon="fas fa-star">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th style="width: 34%">Indicator</th>
                                @foreach ($scale as $value => $label)
                                    <th class="text-center" style="width: 13.2%">
                                        <span class="badge badge-secondary">{{ $value }}</span>
                                        <div class="small font-weight-normal">{{ $label }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $index => $indicator)
                                <tr>
                                    <td>
                                        {{ $indicator }}
                                        @error("scores.{$coreValue}.{$index}")
                                            <div class="text-danger small">{{ $message }}</div>
                                        @enderror
                                    </td>
                                    @foreach ($scale as $value => $label)
                                        <td class="text-center align-middle">
                                            <div class="custom-control custom-radio d-inline-block">
                                                <input id="score_{{ md5($coreValue.$index.$value) }}"
                                                    name="scores[{{ $coreValue }}][{{ $index }}]"
                                                    type="radio"
                                                    value="{{ $value }}"
                                                    class="custom-control-input"
                                                    @checked((string) old("scores.{$coreValue}.{$index}", $draftScores[$coreValue][$index] ?? null) === (string) $value)
                                                    required>
                                                <label class="custom-control-label" for="score_{{ md5($coreValue.$index.$value) }}">
                                                    <span class="sr-only">{{ $label }}</span>
                                                </label>
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-adminlte-card>
        @endforeach

        @if (in_array($assignment->assessor_type, ['supervisor', 'subordinate'], true))
            <x-adminlte-card
                :title="$assignment->assessor_type === 'supervisor' ? 'Feedback untuk Bawahan' : 'Feedback untuk Atasan'"
                theme="info"
                icon="fas fa-comment-alt">
                <p class="text-muted">
                    {{ $assignment->assessor_type === 'supervisor'
                        ? 'Tuliskan saran, apresiasi, atau area pengembangan untuk pegawai yang dinilai.'
                        : 'Tuliskan saran atau masukan konstruktif untuk atasan langsung yang dinilai.' }}
                </p>
                <textarea id="feedback"
                    name="feedback"
                    rows="5"
                    maxlength="2000"
                    class="form-control @error('feedback') is-invalid @enderror"
                    aria-describedby="feedback_help">{{ old('feedback', $assignment->feedback) }}</textarea>
                <div id="feedback_help" class="d-flex justify-content-between mt-1">
                    <small class="text-muted">Opsional. Maksimal 2.000 karakter.</small>
                    <small class="text-muted"><span id="feedback_count">0</span>/2000</small>
                </div>
                @error('feedback')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </x-adminlte-card>
        @endif

        <div class="card">
            <div class="card-body d-flex justify-content-between align-items-center">
                <a href="{{ route('assessment.pending.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
                <div>
                    <button type="submit"
                        class="btn btn-outline-primary mr-2"
                        formaction="{{ route('assessment.assignments.draft', $assignment) }}"
                        formnovalidate>
                        <i class="fas fa-save mr-1"></i> Simpan Draft
                    </button>
                    <button type="submit" class="btn btn-success" onclick="return confirm('Submit this assessment? Answers cannot be changed after final submission.');">
                        <i class="fas fa-paper-plane mr-1"></i> Submit Assessment
                    </button>
                </div>
            </div>
        </div>
    </form>
@stop

@section('js')
    @if (in_array($assignment->assessor_type, ['supervisor', 'subordinate'], true))
        <script>
            const feedback = document.getElementById('feedback');
            const feedbackCount = document.getElementById('feedback_count');
            const updateFeedbackCount = () => feedbackCount.textContent = feedback.value.length;

            feedback.addEventListener('input', updateFeedbackCount);
            updateFeedbackCount();
        </script>
    @endif
@stop
