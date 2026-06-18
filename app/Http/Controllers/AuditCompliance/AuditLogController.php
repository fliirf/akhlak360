<?php

namespace App\Http\Controllers\AuditCompliance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $modules = AuditLog::query()->select('module')->distinct()->orderBy('module')->pluck('module');
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'module' => ['nullable', Rule::in($modules->all())],
            'action' => ['nullable', Rule::in($actions->all())],
            'date' => ['nullable', 'date'],
        ]);
        $query = AuditLog::query()
            ->with('user')
            ->when($validated['user_id'] ?? null, fn (Builder $query, $id) => $query->where('user_id', $id))
            ->when($validated['module'] ?? null, fn (Builder $query, $module) => $query->where('module', $module))
            ->when($validated['action'] ?? null, fn (Builder $query, $action) => $query->where('action', $action))
            ->when($validated['date'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', $date));
        $logs = (clone $query)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('audit-compliance.audit-logs', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(),
            'modules' => $modules,
            'actions' => $actions,
            'summary' => [
                'total' => (clone $query)->count(),
                'users' => (clone $query)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
                'system' => (clone $query)->whereNull('user_id')->count(),
                'today' => (clone $query)->whereDate('created_at', today())->count(),
            ],
        ]);
    }
}
