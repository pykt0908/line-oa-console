<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('audit.view'), 403);

        $query = AuditLog::query()->with('user:id,name,display_name')->orderByDesc('id');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        return response()->json($query->paginate(50));
    }
}
