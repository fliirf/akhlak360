@csrf

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="assessment_period_id">Assessment Period</label>
            <select id="assessment_period_id" name="assessment_period_id" class="form-control @error('assessment_period_id') is-invalid @enderror" required>
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}" @selected((int) old('assessment_period_id', $assignment->assessment_period_id) === $period->id)>
                        {{ $period->name }} - {{ ucfirst($period->status) }}
                    </option>
                @endforeach
            </select>
            @error('assessment_period_id')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="assessor_type">Assessor Type</label>
            <select id="assessor_type" name="assessor_type" class="form-control @error('assessor_type') is-invalid @enderror" required>
                @foreach (['supervisor', 'peer', 'subordinate', 'self'] as $type)
                    <option value="{{ $type }}" @selected(old('assessor_type', $assignment->assessor_type) === $type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
            @error('assessor_type')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="assessor_employee_id">Assessor</label>
            <select id="assessor_employee_id" name="assessor_employee_id" class="form-control @error('assessor_employee_id') is-invalid @enderror" required>
                <option value="">Select assessor</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((int) old('assessor_employee_id', $assignment->assessor_employee_id) === $employee->id)>
                        {{ $employee->employee_number }} - {{ $employee->name }} ({{ $employee->department?->name }})
                    </option>
                @endforeach
            </select>
            @error('assessor_employee_id')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="assessee_employee_id">Assessee</label>
            <select id="assessee_employee_id" name="assessee_employee_id" class="form-control @error('assessee_employee_id') is-invalid @enderror" required>
                <option value="">Select assessee</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((int) old('assessee_employee_id', $assignment->assessee_employee_id) === $employee->id)>
                        {{ $employee->employee_number }} - {{ $employee->name }} ({{ $employee->department?->name }})
                    </option>
                @endforeach
            </select>
            @error('assessee_employee_id')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle mr-1"></i>
    New assignments are always pending and can only be created for the active period. Assessor and assessee cannot be the same except self assignments.
</div>

<div class="d-flex justify-content-between">
    <a href="{{ route('assessment-cycle.assign-assessors.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back
    </a>
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save mr-1"></i> Save
    </button>
</div>
