<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CameraController extends Controller
{
    public function index(): View
    {
        return view('inventory.cameras');
    }

    public function live()
    {
        $cameras = Camera::where('enabled', true)->orderBy('name')->get();

        return view('server.cameras-live', [
            'cameras'          => $cameras,
            'mediamtxReadUser' => config('services.mediamtx.read_user'),
            'mediamtxReadPass' => config('services.mediamtx.read_pass'),
        ]);
    }

}