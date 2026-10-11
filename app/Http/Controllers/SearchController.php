<?php

namespace App\Http\Controllers;

use App\Models\LogApp;
use App\Services\LakeQueries;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SearchController extends Controller
{
    public function __invoke(Request $request, LakeQueries $queries): View
    {
        $filters = $request->validate([
            'app' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:20'],
            'text' => ['nullable', 'string', 'max:500'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $result = null;
        $error = null;

        if ($request->query() !== []) {
            try {
                $result = $queries->search([
                    ...$filters,
                    'from' => $filters['from'] ?? now()->subDay()->toIso8601String(),
                    'to' => $filters['to'] ?? now()->toIso8601String(),
                    'limit' => 100,
                ]);
            } catch (Throwable) {
                $error = 'The lake query failed. Check the runtime probe and flush status.';
            }
        }

        return view('search', [
            'apps' => LogApp::query()->orderBy('name')->get(),
            'filters' => $filters,
            'result' => $result,
            'error' => $error,
        ]);
    }
}
