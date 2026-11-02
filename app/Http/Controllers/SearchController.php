<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __construct(private SearchService $service) {}

    public function index(SearchRequest $request): JsonResponse
    {
        $results = $this->service->search($request, $request->string('q'));

        if ($request->wantsJson()) {
            return response()->json(['results' => $results]);
        }

        return response()->json(['results' => $results]);
    }
}
