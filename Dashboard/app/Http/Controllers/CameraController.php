<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CameraController extends Controller
{
    public function index(): View
    {
        return view('inventory.cameras');
    }
}