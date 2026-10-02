<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class JudgeController extends Controller
{
    /**
     * List all judges.
     */
    public function index()
    {
        return Inertia::render('Admin/Judges/Index', [
            'judges' => User::where('role', 'judge')
                ->orderBy('id')
                ->get(['id', 'name', 'email', 'created_at']),
        ]);
    }

    /**
     * Create a new judge account.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'judge',
        ])->forceFill(['email_verified_at' => now()])->save();

        return back();
    }

    /**
     * Update a judge's name, email, and optionally their password.
     */
    public function update(Request $request, User $judge)
    {
        abort_unless($judge->role === 'judge', 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($judge->id)],
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        $judge->name = $data['name'];
        $judge->email = $data['email'];

        if (! empty($data['password'])) {
            $judge->password = Hash::make($data['password']);
        }

        $judge->save();

        return back();
    }
}
