<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $this->assertManager($request);

        $q = User::query();

        if ($request->boolean('only_deleted')) {
            $q->onlyTrashed();
        } elseif ($request->boolean('include_deleted')) {
            $q->withTrashed();
        }

        if ($search = $request->query('q')) {
            $q->where(function ($where) use ($search) {
                $like = "%{$search}%";
                $where->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        if ($role = $request->query('role')) {
            $q->where('role', $role);
        }

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));

        return $q->orderBy('id', 'desc')
            ->paginate($perPage, ['id', 'name', 'email', 'role', 'status', 'created_at', 'updated_at', 'deleted_at']);
    }

    public function show(Request $request, User $user)
    {
        $this->assertManager($request);

        return $user->only(['id', 'name', 'email', 'role', 'status', 'created_at', 'updated_at', 'deleted_at']);
    }

    public function update(Request $request, User $user)
    {
        $this->assertManager($request);

        $data = $request->validate([
            'name'  => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role'  => ['sometimes', 'required', Rule::in(['filho-de-santo', 'gerente', 'desenvolvedor'])],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
            'password' => 'sometimes|nullable|string|min:8',
        ]);

        if (array_key_exists('password', $data)) {
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
        }

        $user->fill($data)->save();

        return $user->fresh()->only(['id', 'name', 'email', 'role', 'status', 'created_at', 'updated_at', 'deleted_at']);
    }

    public function updateStatus(Request $request, User $user)
    {
        $this->assertManager($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $user->status = $data['status'];
        $user->save();

        return $user->fresh()->only(['id', 'name', 'email', 'role', 'status', 'created_at', 'updated_at', 'deleted_at']);
    }

    public function destroy(Request $request, User $user)
    {
        $this->assertManager($request);

        $user->delete();
        return response()->noContent();
    }

    private function assertManager(Request $request): void
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['gerente', 'desenvolvedor'], true)) {
            abort(403, 'Access denied.');
        }
    }
}
