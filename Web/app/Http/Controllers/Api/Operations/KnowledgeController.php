<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KnowledgeController extends Controller
{
    public function facets(Request $request): JsonResponse
    {
        $rows = KnowledgeArticle::with('category:id,name')->when(! $request->user()->isAdmin(), fn ($q) => $q->where('published', true))->select(['kind', 'crop', 'category_id'])->distinct()->get();

        return response()->json(['data' => $rows->map(fn ($row) => ['kind' => $row->kind, 'crop' => $row->crop, 'category_id' => $row->category_id, 'category_name' => $row->category?->name])]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = KnowledgeArticle::with(['category:id,name', 'product']);
        if (! $request->user()->isAdmin()) {
            $query->where('published', true);
        }
        foreach (['kind', 'crop', 'category_id'] as $key) {
            if ($request->filled($key)) {
                $query->where($key, $request->input($key));
            }
        }
        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->string('search').'%');
        }

        return response()->json($query->orderBy('crop')->orderBy('title')->paginate(30));
    }

    public function store(Request $request, ?KnowledgeArticle $article = null): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['kind' => ['required', Rule::in(['crop', 'product'])], 'crop' => 'required_if:kind,crop|nullable|string|max:100', 'category_id' => 'required|integer|exists:categories,id', 'product_id' => 'nullable|integer|exists:products,id', 'title' => 'required|string|max:255', 'published' => 'required|boolean', 'image' => 'nullable|image|max:5120', 'content' => 'required|array', 'content.active_ingredient' => 'nullable|string|max:2000', 'content.dosage' => 'nullable|string|max:2000', 'content.stage' => 'nullable|string|max:2000', 'content.method' => 'nullable|string|max:3000', 'content.dos' => 'nullable|string|max:3000', 'content.donts' => 'nullable|string|max:3000', 'content.phi' => 'nullable|string|max:2000', 'content.notes' => 'nullable|string|max:5000']);
        if ($data['published']) {
            $request->validate(['product_id' => 'required|exists:products,id', 'content.active_ingredient' => 'required|string', 'content.dosage' => 'required|string', 'content.stage' => 'required|string', 'content.method' => 'required|string', 'content.dos' => 'required|string', 'content.donts' => 'required|string', 'content.phi' => 'required|string']);
        }
        $data['content'] = array_intersect_key($data['content'], array_flip(['active_ingredient', 'dosage', 'stage', 'method', 'dos', 'donts', 'phi', 'notes']));
        unset($data['image']);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('knowledge', 'public');
        }
        $data['updated_by'] = $request->user()->id;
        if ($article?->exists) {
            $article->update($data);
        } else {
            $article = KnowledgeArticle::create($data);
        }
        ActivityLogger::log('knowledge.saved', $article->title, $article, $request->user()->id);

        return response()->json(['data' => $article->fresh(['product', 'category'])]);
    }
}
