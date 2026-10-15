<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\SaveNotificationPreferences;
use App\Http\Requests\Notifications\NotificationListRequest;
use App\Http\Requests\Notifications\SaveNotificationPreferencesRequest;
use App\Http\Requests\Notifications\SetNotificationReadRequest;
use App\Notifications\StudentActivity;
use App\Support\NotificationInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(NotificationListRequest $request): Response
    {
        $filters = $request->validated();
        $query = NotificationInbox::query($request->user());
        if (($filters['filter'] ?? '') === 'unread') {
            $query->whereNull('read_at');
        } elseif (($filters['filter'] ?? '') === 'read') {
            $query->whereNotNull('read_at');
        }
        if (filled($filters['kind'] ?? null)) {
            $query->where('data->kind', $filters['kind']);
        }

        return Inertia::render('notifications/index', [
            // A separate prop keeps the shared bell feed intact on this page too.
            'entries' => $query->orderByDesc('id')->paginate(25)->withQueryString()->through(fn (DatabaseNotification $item) => NotificationInbox::present($item)),
            'filters' => ['filter' => $filters['filter'] ?? '', 'kind' => $filters['kind'] ?? ''],
            'types' => collect(StudentActivity::TYPES)->map(fn ($label, $kind) => ['kind' => $kind, 'label' => $label, 'enabled' => $request->user()->wantsNotification($kind)])->values(),
        ]);
    }

    /** POST: prefetching a link must never mark a notification read. */
    public function open(Request $request, string $notification): RedirectResponse
    {
        Gate::authorize('viewAny', DatabaseNotification::class);
        $item = NotificationInbox::query($request->user())->whereKey($notification)->firstOrFail();
        Gate::authorize('update', $item);
        $item->markAsRead();

        return redirect(NotificationInbox::present($item)['data']['href']);
    }

    public function read(SetNotificationReadRequest $request, string $notification): RedirectResponse
    {
        $item = NotificationInbox::query($request->user())->whereKey($notification)->firstOrFail();
        Gate::authorize('update', $item);
        if ($request->boolean('read')) {
            $item->markAsRead();
        } else {
            $item->markAsUnread();
        }

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', DatabaseNotification::class);
        NotificationInbox::query($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    public function preferences(SaveNotificationPreferencesRequest $request, SaveNotificationPreferences $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated('enabled'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Préférences de notification enregistrées.']);

        return back();
    }
}
