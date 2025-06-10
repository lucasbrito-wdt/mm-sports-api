<?php

namespace App\Domains\Shared\Interfaces;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface IController{

    /**
     * @param array $options (path, query, fragment, pageName)
     * @return JsonResponse
     */
    public function index(Request $request);

    /**
     * Display the specified resource.
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id);

    /**
     * Show the form for editing the specified resource.
     */
    public function store(Request $request);

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id);

    /**
     * Remove the specified resource from storage.
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id);
}
