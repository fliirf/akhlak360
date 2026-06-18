<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['read', 'unread'])],
            'type' => ['nullable', Rule::in(['assessment_reminder', 'system', 'result', 'idp'])],
        ]);
        $baseQuery = $request->user()->notifications();
        $notifications = (clone $baseQuery)
            ->when(($validated['status'] ?? null) === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when(($validated['status'] ?? null) === 'unread', fn ($query) => $query->unread())
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->type($type))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('message', 'like', '%'.$search.'%');
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'unread' => (clone $baseQuery)->unread()->count(),
                'reminders' => (clone $baseQuery)->type('assessment_reminder')->count(),
                'results' => (clone $baseQuery)->whereIn('type', ['result', 'idp'])->count(),
            ],
            'types' => ['assessment_reminder', 'system', 'result', 'idp'],
        ]);
    }

    public function navbar(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(5)
            ->get();

        $unreadCount = $request->user()
            ->notifications()
            ->unread()
            ->count();

        return response()->json([
            'label' => $unreadCount,
            'label_color' => $unreadCount > 0 ? 'danger' : 'secondary',
            'icon_color' => $unreadCount > 0 ? 'warning' : 'muted',
            'dropdown' => view('notifications.partials.navbar-dropdown', compact('notifications'))->render(),
        ]);
    }

    public function markAsRead(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->update([
            'read_at' => now(),
        ]);

        $this->audit($request, 'mark_read', "Marked notification #{$notification->id} as read.");

        $destination = $this->safeDestination($notification->destination_url);

        return redirect($destination)->with('success', 'Notification marked as read.');
    }

    private function safeDestination(?string $destination): string
    {
        if (! $destination || ! str_starts_with($destination, '/') || str_starts_with($destination, '//')) {
            return route('notifications.index', absolute: false);
        }

        return $destination;
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $count = $request->user()
            ->notifications()
            ->unread()
            ->update(['read_at' => now()]);

        $this->audit($request, 'mark_all_read', "Marked {$count} notifications as read.");

        return back()->with('success', 'All notifications marked as read.');
    }

    private function audit(Request $request, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => 'notifications',
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
