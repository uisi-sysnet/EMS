<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Define allowed users and their roles
        $users = [
            'admin' => [
                'password' => 'admin',
                'role'     => 'administrator',
            ],
            'user' => [
                'password' => 'user',
                'role'     => 'user',
            ],
        ];

        $username = $credentials['username'];
        $password = $credentials['password'];

        if (isset($users[$username]) && $users[$username]['password'] === $password) {
            // Store authentication state and role in session
            session([
                'authenticated' => true,
                'username'      => $username,
                'role'          => $users[$username]['role'],
            ]);

            return redirect()->intended('/');
        }

        return back()->withErrors([
            'username' => 'The provided credentials do not match our records.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        session()->forget(['authenticated', 'username', 'role']);
        return redirect('/login');
    }
}