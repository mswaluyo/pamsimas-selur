<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventLog;
use Illuminate\Http\Request;

class LogApiController extends Controller
{
    /**
     * GET /api/terminal/events — event terbaru untuk terminal monitor.
     */
    public function terminalEvents(Request $request)
    {
        $limit = min(200, (int) $request->query('limit', 50));
        $events = EventLog::with('device')
            ->orderByDesc('event_time')->limit($limit)->get();

        return response()->json(['status' => 'success', 'data' => $events->map(fn ($e) => [
            'id' => $e->id,
            'device' => $e->device?->mac_address ?? "-",
            'type' => $e->event_type,
            'message' => $e->message,
            'time' => optional($e->event_time)->format('d-m-Y H:i:s'),
        ])]);
    }

    /**
     * POST /api/terminal/clear — bersihkan event log lama (>7 hari).
     */
    public function clearTerminalEvents()
    {
        $count = EventLog::where('event_time', '<', now()->subDays(7))->delete();
        return response()->json(['status' => 'success', 'deleted' => $count]);
    }

    /**
     * GET /api/security/events — event keamanan (login, alert).
     */
    public function securityEvents(Request $request)
    {
        $limit = min(200, (int) $request->query('limit', 50));
        $events = EventLog::whereIn('event_type', ['Security', 'Security Alert'])
            ->orderByDesc('event_time')->limit($limit)->get();

        return response()->json(['status' => 'success', 'data' => $events]);
    }
}
