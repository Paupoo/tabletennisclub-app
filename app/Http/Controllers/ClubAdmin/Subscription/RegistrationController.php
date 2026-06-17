<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Subscription;

use App\Domains\ClubAdmin\Subscriptions\Models\Registration;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create(): void
    {
        // TODO
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): void
    {
        // TODO
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): void
    {
        // TODO
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $registrations = Registration::all();

        return view('clubAdmin.registrations.index', compact([
            'registrations',
        ]));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): void
    {
        // TODO
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): void
    {
        // TODO
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): void
    {
        // TODO
    }
}
