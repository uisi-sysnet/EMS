<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use App\Models\Camera;

class CameraController extends Controller
{
    public function index(): View
    {
        $cameras = Camera::orderBy('name')->get();

        return view('inventory.cameras', [
            'cameras'          => $cameras,
            'mediamtxReadUser' => config('services.mediamtx.read_user'),
            'mediamtxReadPass' => config('services.mediamtx.read_pass'),
        ]);
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