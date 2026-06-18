<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $departments = Department::query()
            ->withCount('employees')
            ->search($request->string('search')->toString())
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('master-data.departments.index', compact('departments'));
    }

    public function create(): View
    {
        return view('master-data.departments.create', [
            'department' => new Department(['is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $department = Department::create([
            ...$data,
            'is_active' => true,
        ]);

        $this->audit($request, 'create', "Created department {$department->name}.");

        return redirect()
            ->route('master-data.departments.index')
            ->with('success', 'Department created successfully.');
    }

    public function edit(Department $department): View
    {
        return view('master-data.departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('departments', 'code')->ignore($department)],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $department->update([
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit($request, 'update', "Updated department {$department->name}.");

        return redirect()
            ->route('master-data.departments.index')
            ->with('success', 'Department updated successfully.');
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        if ($department->employees()->exists()) {
            $department->update(['is_active' => false]);
            $this->audit($request, 'deactivate', "Deactivated department {$department->name} because related employees exist.");

            return redirect()
                ->route('master-data.departments.index')
                ->with('warning', 'Department has related employees, so it was deactivated instead of deleted.');
        }

        $name = $department->name;
        $department->delete();

        $this->audit($request, 'delete', "Deleted department {$name}.");

        return redirect()
            ->route('master-data.departments.index')
            ->with('success', 'Department deleted successfully.');
    }

    private function audit(Request $request, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => 'departments',
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
