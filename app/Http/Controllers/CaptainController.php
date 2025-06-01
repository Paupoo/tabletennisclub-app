<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaptainRequest;
use App\Http\Requests\UpdateCaptainRequest;
use App\Models\Captain;

class CaptainController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCaptainRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Captain $captain)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Captain $captain)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCaptainRequest $request, Captain $captain)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Captain $captain)
    {
        //
    }
}
