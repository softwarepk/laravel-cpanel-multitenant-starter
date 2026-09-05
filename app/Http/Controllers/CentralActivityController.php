<?php

namespace App\Http\Controllers;

use App\Models\CentralAuditLog;
use Illuminate\View\View;

class CentralActivityController extends Controller
{
    public function index(): View
    {
        return view('central.activity.index', ['auditLogs' => CentralAuditLog::query()->with('centralAdmin')->latest('created_at')->paginate(50)]);
    }
}
