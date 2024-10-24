<?php

namespace App\Http\Controllers\v1\Guest;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function errorLogs()
    {
        try {
            $record = ErrorLog::take(5)->latest()->get();

            return JsonResponser::send(false, 'Command executed successfully!', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
