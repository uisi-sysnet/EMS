<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User; // If you're using a User model

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

        // Your existing login logic...
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

    public function showRegisterForm()
    {
        return view('login'); // You can reuse the same view with mode='register'
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|unique:users,username', // Adjust table/column as needed
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // If you have a User model, create the user:
        // $user = User::create([
        //     'username' => $validated['username'],
        //     'email' => $validated['email'],
        //     'password' => Hash::make($validated['password']),
        // ]);

        // If you're using the hardcoded users array:
        // $users[$validated['username']] = [
        //     'password' => $validated['password'],
        //     'role' => 'user',
        // ];

        return redirect()->route('login')->with('success', 'Registration successful! Please login.');
    }

    public function logout(Request $request)
    {
        session()->forget(['authenticated', 'username', 'role']);
        return redirect('/login');
    }
}