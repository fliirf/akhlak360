<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PositionController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $positions = Position::query()
            ->withCount('employees')
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('level', 'like', '%'.$request->search.'%')))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('master-data.positions.index', compact('positions'));
    }

    public function create(): View
    {
        return view('master-data.positions.create', [
            'position' => new Position(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:100'],
        ]);

        $position = Position::create($data);

        $this->audit($request, 'create', "Created position {$position->name}.");

        return redirect()
            ->route('master-data.positions.index')
            ->with('success', 'Position created successfully.');
    }

    public function edit(Position $position): View
    {
        return view('master-data.positions.edit', compact('position'));
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:100'],
        ]);

        $position->update($data);

        $this->audit($request, 'update', "Updated position {$position->name}.");

        return redirect()
            ->route('master-data.positions.index')
            ->with('success', 'Position updated successfully.');
    }

    public function destroy(Request $request, Position $position): RedirectResponse
    {
        $name = $position->name;
        $position->delete();

        $this->audit($request, 'delete', "Deleted position {$name}.");

        return redirect()
            ->route('master-data.positions.index')
            ->with('success', 'Position deleted successfully.');
    }

    private function audit(Request $request, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => 'positions',
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
