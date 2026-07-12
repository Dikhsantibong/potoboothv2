<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('users/index', [
            'users' => User::latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users',
            'password' => ['required', Rules\Password::defaults()],
            'role' => 'required|in:admin,mitra',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        User::create($validated);

        return to_route('users.index')->with('status', 'user-created');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email,'.$user->id,
            'role' => 'required|in:admin,mitra',
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['required', Rules\Password::defaults()];
        }

        $validated = $request->validate($rules);

        if ($request->filled('password')) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return to_route('users.index')->with('status', 'user-updated');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot delete yourself.');
        }
        $user->delete();

        return to_route('users.index')->with('status', 'user-deleted');
    }
}
