<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPending;
use Illuminate\Http\Request;

class EventsPendingController extends Controller
{
    public function index(Request $req, Event $event)
    {
        $q = $event->pendings()->newQuery();
        $user = $req->user();

        $q->where(function ($where) use ($user) {
            $where->where('is_private', false);
            if ($user) {
                $where->orWhere(function ($private) use ($user) {
                    $private->where('is_private', true)
                        ->where(function ($visible) use ($user) {
                            $visible->where('created_by', $user->id)
                                ->orWhere('assignee_id', $user->id);
                        });
                });
            }
        });

        if ($status = $req->query('status')) {
            $q->whereIn('status', (array)$status);
        }
        if ($assignee = $req->query('assignee_id')) {
            $q->where('assignee_id', $assignee);
        }

        return $q->orderByDesc('id')->paginate(50);
    }

    public function store(Request $req, Event $event)
    {
        $data = $req->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_private'  => 'sometimes|boolean',
            'status'      => 'sometimes|in:open,doing,done',
            'assignee_id' => 'nullable|exists:users,id',
            'due_date'    => 'nullable|date',
        ]);
        $data['status'] = $data['status'] ?? 'open';
        $data['created_by'] = $req->user()?->id;

        return $event->pendings()->create($data);
    }

    public function update(Request $req, Event $event, EventPending $pending)
    {
        if ((int)$pending->event_id !== (int)$event->id) abort(404);

        $user = $req->user();
        if ($pending->is_private) {
            $isOwner = $user && (int)$pending->created_by === (int)$user->id;
            $isAssignee = $user && (int)$pending->assignee_id === (int)$user->id;
            if (!$isOwner && !$isAssignee) {
                abort(403, 'Access denied.');
            }
        }

        $data = $req->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_private'  => 'sometimes|boolean',
            'status'      => 'sometimes|in:open,doing,done',
            'assignee_id' => 'nullable|exists:users,id',
            'due_date'    => 'nullable|date',
        ]);

        $pending->fill($data)->save();
        return $pending->fresh();
    }

    public function destroy(Event $event, EventPending $pending)
    {
        if ((int)$pending->event_id !== (int)$event->id) abort(404);

        $user = request()->user();
        if ($pending->is_private) {
            $isOwner = $user && (int)$pending->created_by === (int)$user->id;
            $isAssignee = $user && (int)$pending->assignee_id === (int)$user->id;
            if (!$isOwner && !$isAssignee) {
                abort(403, 'Access denied.');
            }
        }

        $pending->delete();
        return response()->noContent();
    }
}
