<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class LookupController extends Controller
{
    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function tags(): JsonResponse
    {
        return response()->json([
            'data' => Tag::query()->orderBy('name')->get(),
        ]);
    }
}
