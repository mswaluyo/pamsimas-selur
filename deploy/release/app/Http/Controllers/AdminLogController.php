<?php

namespace App\Http\Controllers;

use App\Models\AdminLog;
use Illuminate\Http\Request;

class AdminLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AdminLog::query()
            ->when($request->query('q'), fn ($q, $s) => $q->where('action', 'like', "%{$s}%")->orWhere('details', 'like', "%{$s}%"))
            ->orderByDesc('created_at')->paginate(50)->withQueryString();

        return view('logs.admin', ['logs' => $logs]);
    }
}
