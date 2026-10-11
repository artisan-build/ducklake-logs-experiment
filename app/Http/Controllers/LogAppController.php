<?php

namespace App\Http\Controllers;

use App\Models\LogApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogAppController extends Controller
{
    public function index(): View
    {
        return view('apps.index', ['apps' => LogApp::query()->latest()->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);
        [$app, $token] = LogApp::issue($validated['name']);

        return to_route('apps.index')->with('issued_token', ['name' => $app->name, 'token' => $token]);
    }
}
