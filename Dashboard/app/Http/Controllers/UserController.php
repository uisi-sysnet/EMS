<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display the user management page
     */
    public function index(): View
    {
        $users = User::orderBy('created_at', 'desc')->get();
        return view('user_management', compact('users'));
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'size:11', 'regex:/^[0-9]{11}$/', 'unique:users,contact_number'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in(['superAdmin', 'admin', 'user'])],
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        if (auth()->user()?->role !== 'superAdmin' && $request->role === 'superAdmin') {
            return back()->withErrors(['role' => 'You do not have permission to create Super Administrator accounts.']);
        }

        $user = User::create([
            'name' => $request->first_name . ' ' . $request->last_name, // <-- ADD THIS
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'contact_number' => $request->contact_number,
            'email' => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'email_verified_at' => now(),
        ]);

        return redirect()->route('user.index')
            ->with('success', "User {$user->first_name} {$user->last_name} created successfully!");
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'size:11', 'regex:/^[0-9]{11}$/'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in(['superAdmin', 'admin', 'user'])],
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // update()
        if (auth()->user()?->role !== 'superAdmin' && $request->role === 'superAdmin') {
            return back()->withErrors(['role' => 'You do not have permission to assign Super Administrator role.']);
        }

        if (auth()->user()?->role !== 'superAdmin' && $user->role === 'superAdmin') {
            return back()->withErrors(['role' => 'You do not have permission to edit Super Administrator accounts.']);
        }

        $user->update([
            'name' => $request->first_name . ' ' . $request->last_name, // <-- ADD THIS
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'contact_number' => $request->contact_number,
            'email' => $request->email,
            'username' => $request->username,
            'role' => $request->role,
        ]);

        // Only update password if provided
        if ($request->filled('password')) {
            $user->update([
                'password' => Hash::make($request->password),
            ]);
        }

        return redirect()->route('user.index')
            ->with('success', "User {$user->first_name} {$user->last_name} updated successfully!");
    }

    /**
     * Remove the specified user
     */
    public function destroy(string $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        
        // destroy()
        if (auth()->user()?->role !== 'superAdmin' && $user->role === 'superAdmin') {
            return redirect()->route('user.index')
                ->with('error', 'You do not have permission to delete Super Administrator accounts.');
        }
        
        // Prevent deleting yourself
        if (auth()->id() === $user->id) {
            return redirect()->route('user.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $fullName = "{$user->first_name} {$user->last_name}";
        $user->delete();

        return redirect()->route('user.index')
            ->with('success', "User {$fullName} deleted successfully!");
    }

    /**
     * Get user data for editing (AJAX)
     */
    public function edit(string $id)
    {
        $user = User::findOrFail($id);
        
        return response()->json([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'contact_number' => $user->contact_number,
            'email' => $user->email,
            'username' => $user->username,
            'role' => $user->role,
        ]);
    }
}