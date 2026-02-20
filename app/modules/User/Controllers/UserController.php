<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\User;
use App\Modules\User\Requests\CreateUserRequest;
use App\Modules\User\Requests\UpdateUserRequest;
use App\Modules\User\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::paginate(10);

        return response()->json([
            'message' => 'Users retrieved successfully',
            'data' => UserResource::collection($users),
        ]);
    }

    public function show($userId): JsonResponse
    {
        $user = User::find($userId);

        if (! $user) {
            throw new NotFoundHttpException('User not found');
        }

        $this->authorize('view', $user);

        return response()->json([
            'message' => 'User retrieved successfully',
            'data' => UserResource::make($user),
        ]);
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);

        $user->assignRole($validated['role']);

        return response()->json([
            'message' => 'User created successfully',
            'data' => UserResource::make($user),
        ], 201);
    }

    public function update(UpdateUserRequest $request, $userId): JsonResponse
    {
        $user = User::find($userId);

        if (! $user) {
            throw new NotFoundHttpException('User not found');
        }

        $this->authorize('update', $user);

        $validated = $request->validated();

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        if (isset($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }

        $user->save();

        if (isset($validated['role']) && auth()->user()->hasRole('admin_tenant')) {
            $user->syncRoles($validated['role']);
        }

        return response()->json([
            'message' => 'User updated successfully',
            'data' => UserResource::make($user),
        ]);
    }

    public function destroy($userId): JsonResponse
    {
        $user = User::find($userId);

        if (! $user) {
            throw new NotFoundHttpException('User not found');
        }

        $this->authorize('delete', $user);

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ], 200);
    }
}
