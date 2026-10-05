<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('inbox.view'), 403);

        return response()->json([
            'data' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('inbox.view'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:tags,name'],
            'color' => ['nullable', 'string', 'max:32'],
        ]);

        $tag = Tag::query()->create($data);

        return response()->json(['data' => $tag], 201);
    }

    public function destroy(Request $request, Tag $tag)
    {
        abort_unless($request->user()->can('inbox.view'), 403);

        $tag->conversations()->detach();
        $tag->delete();

        return response()->json(['message' => 'ลบแท็กแล้ว']);
    }
}
