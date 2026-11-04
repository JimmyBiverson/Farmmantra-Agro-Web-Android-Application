<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\Slide;
use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    public function slides(): JsonResponse
    {
        $slides = Slide::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->latest()
            ->get();

        return response()->json(['data' => $slides]);
    }

    public function news(): JsonResponse
    {
        $news = News::where('is_published', true)
            ->latest('published_at')
            ->get();

        return response()->json(['data' => $news]);
    }

    public function showNews(News $news): JsonResponse
    {
        if (! $news->is_published) {
            return response()->json(['message' => 'News article not found.'], 404);
        }

        return response()->json(['data' => $news]);
    }
}
