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
            'status'      => 'sometimes|in:open,doing,done',
            'assignee_id' => 'nullable|exists:users,id',
            'due_date'    => 'nullable|date',
        ]);
        $data['status'] = $data['status'] ?? 'open';

        return $event->pendings()->create($data);
    }

    public function update(Request $req, Event $event, EventPending $pending)
    {
        if ((int)$pending->event_id !== (int)$event->id) abort(404);

        $data = $req->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
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
        $pending->delete();
        return response()->noContent();
    }
}
