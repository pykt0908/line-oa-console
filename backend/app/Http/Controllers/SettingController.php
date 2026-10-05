<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('settings.manage') || $request->user()->can('dashboard.view'), 403);

        $settings = Setting::query()->orderBy('group')->orderBy('key')->get()->groupBy('group');

        return response()->json(['data' => $settings]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['nullable'],
            'settings.*.group' => ['nullable', 'string'],
        ]);

        foreach ($data['settings'] as $item) {
            Setting::setValue($item['key'], $item['value'] ?? '', $item['group'] ?? 'general');
        }

        $this->audit->log('settings.update', null, ['count' => count($data['settings'])]);

        return $this->index($request);
    }
}
