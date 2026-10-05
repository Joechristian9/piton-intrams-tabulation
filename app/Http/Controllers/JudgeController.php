<?php

namespace App\Http\Controllers;

use App\Models\TopFiveScore;
use App\Models\TopFiveSelectionScore;
use App\Models\User;
use App\Support\LiveVersions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        // Score rows per judge (Round 1 + finals), shown in the delete warning.
        $selection = TopFiveSelectionScore::selectRaw('judge_id, COUNT(*) as n')
            ->groupBy('judge_id')->pluck('n', 'judge_id');
        $finals = TopFiveScore::selectRaw('judge_id, COUNT(*) as n')
            ->groupBy('judge_id')->pluck('n', 'judge_id');

        return Inertia::render('Admin/Judges/Index', [
            'judges' => User::where('role', 'judge')
                ->orderBy('id')
                ->get(['id', 'name', 'email', 'created_at'])
                ->each(fn ($judge) => $judge->score_count =
                    (int) ($selection[$judge->id] ?? 0) + (int) ($finals[$judge->id] ?? 0)),
        ]);
    }

    /**
     * Delete a judge and every score they gave (results are averaged over the
     * remaining judges). Admins re-enter their password to confirm.
     */
    public function destroy(Request $request, User $judge)
    {
        abort_unless($judge->role === 'judge', 404);

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        DB::transaction(function () use ($judge) {
            // The foreign keys cascade too; deleting explicitly doesn't depend on that.
            TopFiveSelectionScore::where('judge_id', $judge->id)->delete();
            TopFiveScore::where('judge_id', $judge->id)->delete();
            $judge->delete();
        });

        LiveVersions::bump(LiveVersions::LEGACY, LiveVersions::JUDGES, LiveVersions::SCORES);

        return back();
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

        LiveVersions::bump(LiveVersions::LEGACY, LiveVersions::JUDGES);

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

        LiveVersions::bump(LiveVersions::LEGACY, LiveVersions::JUDGES);

        return back();
    }
}
