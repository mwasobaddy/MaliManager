<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private SearchService $service) {}

    public function index(Request $request)
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $results = $this->service->search($request, $request->string('q'));

        if ($request->wantsJson()) {
            return response()->json(['results' => $results]);
        }

        return response()->json(['results' => $results]);
    }
}
