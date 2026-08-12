<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

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

        // Find user by username
        $user = User::where('username', $credentials['username'])->first();

        // Check if user exists and password matches
        if ($user && Hash::check($credentials['password'], $user->password)) {
            // Store user info in session
            session([
                'authenticated' => true,
                'user_id'       => $user->id,
                'username'      => $user->username,
                'name'          => $user->first_name . ' ' . $user->last_name,
                'email'         => $user->email,
                'role'          => $user->role,
            ]);

            return redirect()->intended('/');
        }

        return back()->withErrors([
            'username' => 'The provided credentials do not match our records.',
        ])->onlyInput('username');
    }

    public function showRegisterForm()
    {
        return view('register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'contact_number' => 'nullable|string|size:11|regex:/^[0-9]{11}$/',
        ]);

        // Create the user
        $user = User::create([
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'contact_number' => $validated['contact_number'] ?? null,
            'role' => 'user', // Default role for new registrations
            'email_verified_at' => now(),
        ]);

        return redirect()->route('login')->with('success', 'Registration successful! Please login.');
    }

    public function logout(Request $request)
    {
        // Clear session
        session()->forget(['authenticated', 'user_id', 'username', 'name', 'email', 'role']);
        
        // Optionally, you can also flush the entire session
        // session()->flush();
        
        return redirect('/login');
    }

    /**
     * Seed default users for testing/development
     * You can call this method from a route or seeder
     */
    public function seedDefaultUsers()
    {
        $users = [
            'superAdmin' => [
                'password' => 'superAdmin',
                'role'     => 'superAdmin',
                'email'    => 'superadmin@example.com',
                'first_name' => 'Super',
                'last_name' => 'Admin',
            ],
            'admin' => [
                'password' => 'admin',
                'role'     => 'admin',
                'email'    => 'admin@example.com',
                'first_name' => 'Admin',
                'last_name' => 'User',
            ],
            'user' => [
                'password' => 'user',
                'role'     => 'user',
                'email'    => 'user@example.com',
                'first_name' => 'Regular',
                'last_name' => 'User',
            ],
        ];

        $created = [];
        foreach ($users as $username => $data) {
            // Check if user already exists
            $existingUser = User::where('username', $username)->first();
            
            if (!$existingUser) {
                $user = User::create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'name' => $data['first_name'] . ' ' . $data['last_name'],
                    'username' => $username,
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'role' => $data['role'],
                    'email_verified_at' => now(),
                ]);
                $created[] = $username;
            }
        }

        if (count($created) > 0) {
            return response()->json([
                'message' => 'Default users created successfully!',
                'users' => $created,
                'credentials' => [
                    'superAdmin' => ['username' => 'superAdmin', 'password' => 'superAdmin'],
                    'admin' => ['username' => 'admin', 'password' => 'admin'],
                    'user' => ['username' => 'user', 'password' => 'user'],
                ]
            ]);
        } else {
            return response()->json([
                'message' => 'Default users already exist.',
            ]);
        }
    }
}