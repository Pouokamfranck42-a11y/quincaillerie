<?php

namespace App\Http\Controllers;

use App\Models\ErrorLog;

class ErrorLogController extends Controller
{
    public function index()
    {
        $logs = ErrorLog::with('user')->latest('created_at')->paginate(30);

        return view('error-logs.index', compact('logs'));
    }
}
