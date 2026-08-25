<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CameraController extends Controller
{
    public function camera(): View
    {
        return view('inventory.cameras');
    }
}