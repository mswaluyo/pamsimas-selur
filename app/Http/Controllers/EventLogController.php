<?php

namespace App\Http\Controllers;

use App\Models\EventLog;
use Illuminate\Http\Request;

class EventLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = EventLog::query()
            ->when($request->query('type'), fn ($q, $t) => $q->where('event_type', $t))
            ->orderByDesc('event_time')->paginate(50)->withQueryString();

        return view('logs.events', [
            'logs' => $logs,
            'types' => EventLog::select('event_type')->distinct()->pluck('event_type'),
        ]);
    }
}
