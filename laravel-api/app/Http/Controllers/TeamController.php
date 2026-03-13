<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TeamController extends Controller
{
    public function index()
    {
        return response()->json(
            User::select('id', 'name', 'email', 'role', 'is_active', 'last_login_at', 'created_at')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role'     => 'in:admin,member',
        ]);

        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);

        ActivityLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'team_member_created',
            'details'    => ['name' => $user->name, 'email' => $user->email],
            'created_at' => now(),
        ]);

        return response()->json(
            $user->only('id', 'name', 'email', 'role', 'is_active'),
            201
        );
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'email'     => 'sometimes|email|unique:users,email,' . $user->id,
            'password'  => 'sometimes|string|min:8',
            'role'      => 'sometimes|in:admin,member',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return response()->json(
            $user->only('id', 'name', 'email', 'role', 'is_active')
        );
    }

    public function destroy(User $user)
    {
        // Empêcher de désactiver le dernier admin
        if ($user->role === 'admin') {
            $adminCount = User::where('role', 'admin')->where('is_active', true)->count();
            if ($adminCount <= 1) {
                return response()->json(['message' => 'Impossible de désactiver le dernier admin.'], 422);
            }
        }
        $user->update(['is_active' => false]);
        return response()->json(null, 204);
    }
}
