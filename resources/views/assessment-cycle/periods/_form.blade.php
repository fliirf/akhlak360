@csrf

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="name">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $period->name) }}"
                class="form-control @error('name') is-invalid @enderror" required>
            @error('name')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="semester">Semester</label>
            <input id="semester" name="semester" type="text" value="{{ old('semester', $period->semester) }}"
                class="form-control @error('semester') is-invalid @enderror" required>
            @error('semester')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="year">Year</label>
            <input id="year" name="year" type="number" value="{{ old('year', $period->year) }}"
                class="form-control @error('year') is-invalid @enderror" required min="2000" max="2100">
            @error('year')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label for="start_date">Start Date</label>
            <input id="start_date" name="start_date" type="date"
                value="{{ old('start_date', optional($period->start_date)->format('Y-m-d') ?? $period->start_date) }}"
                class="form-control @error('start_date') is-invalid @enderror" required>
            @error('start_date')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="end_date">End Date</label>
            <input id="end_date" name="end_date" type="date"
                value="{{ old('end_date', optional($period->end_date)->format('Y-m-d') ?? $period->end_date) }}"
                class="form-control @error('end_date') is-invalid @enderror" required>
            @error('end_date')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
            <small class="form-text text-muted">Maximum duration: 14 calendar days including the start and end dates.</small>
            <small class="form-text text-muted">A 14-day period is supported by setting end date 13 days after start date.</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-control @error('status') is-invalid @enderror" required>
                @foreach (['draft' => 'Draft', 'active' => 'Active', 'closed' => 'Closed'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $period->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="threshold_score">Threshold Score</label>
            <input id="threshold_score" name="threshold_score" type="number" step="0.01" min="1" max="5"
                value="{{ old('threshold_score', $period->threshold_score ?? '3.00') }}"
                class="form-control @error('threshold_score') is-invalid @enderror" required>
            @error('threshold_score')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

<div class="d-flex justify-content-between">
    <a href="{{ route('assessment-cycle.periods.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back
    </a>
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save mr-1"></i> Save
    </button>
</div>
