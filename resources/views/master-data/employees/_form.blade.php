@csrf

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label for="employee_number">Employee Number</label>
            <input id="employee_number" name="employee_number" type="text"
                value="{{ old('employee_number', $employee->employee_number) }}"
                class="form-control @error('employee_number') is-invalid @enderror" required>
            @error('employee_number')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="name">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $employee->name) }}"
                class="form-control @error('name') is-invalid @enderror" required>
            @error('name')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $employee->email) }}"
                class="form-control @error('email') is-invalid @enderror">
            @error('email')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label for="department_id">Department</label>
            <select id="department_id" name="department_id" class="form-control @error('department_id') is-invalid @enderror" required>
                <option value="">Select department</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) old('department_id', $employee->department_id) === $department->id)>
                        {{ $department->name }}
                    </option>
                @endforeach
            </select>
            @error('department_id')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="position_id">Position</label>
            <select id="position_id" name="position_id" class="form-control @error('position_id') is-invalid @enderror">
                <option value="">No position</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}" @selected((int) old('position_id', $employee->position_id) === $position->id)>
                        {{ $position->name }}{{ $position->level ? ' - '.$position->level : '' }}
                    </option>
                @endforeach
            </select>
            @error('position_id')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="supervisor_id">Supervisor</label>
            <select id="supervisor_id" name="supervisor_id" class="form-control @error('supervisor_id') is-invalid @enderror">
                <option value="">No supervisor</option>
                @foreach ($supervisors as $supervisor)
                    <option value="{{ $supervisor->id }}" @selected((int) old('supervisor_id', $employee->supervisor_id) === $supervisor->id)>
                        {{ $supervisor->employee_number }} - {{ $supervisor->name }}
                        {{ $supervisor->position ? '('.$supervisor->position->name.' / '.$supervisor->position->level.')' : '' }}
                    </option>
                @endforeach
            </select>
            @error('supervisor_id')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
            <small class="form-text text-muted">
                Hanya pegawai aktif yang sudah memiliki bawahan atau berada pada level jabatan kepemimpinan.
            </small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label for="employment_status">Employment Status</label>
            <select id="employment_status" name="employment_status" class="form-control @error('employment_status') is-invalid @enderror" required>
                <option value="active" @selected(old('employment_status', $employee->employment_status) === 'active')>Active</option>
                <option value="inactive" @selected(old('employment_status', $employee->employment_status) === 'inactive')>Inactive</option>
            </select>
            @error('employment_status')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="user_id">Linked User</label>
            <select id="user_id" name="user_id" class="form-control @error('user_id') is-invalid @enderror">
                <option value="">No linked user</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((int) old('user_id', $employee->user_id) === $user->id)>
                        {{ $user->name }} - {{ $user->email }} ({{ $user->role }})
                    </option>
                @endforeach
            </select>
            @error('user_id')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="hris_external_id">HRIS External ID</label>
            <input id="hris_external_id" name="hris_external_id" type="text"
                value="{{ old('hris_external_id', $employee->hris_external_id) }}"
                class="form-control @error('hris_external_id') is-invalid @enderror">
            @error('hris_external_id')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>
    </div>
</div>

<div class="d-flex justify-content-between">
    <a href="{{ route('master-data.employees.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back
    </a>
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save mr-1"></i> Save
    </button>
</div>
