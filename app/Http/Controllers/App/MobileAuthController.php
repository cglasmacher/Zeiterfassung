<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:120'],
        ]);

        $user = User::with('employee')->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Die Zugangsdaten sind nicht korrekt.'],
            ]);
        }

        if (! $user->employee || ! $user->employee->active) {
            throw ValidationException::withMessages([
                'email' => ['Für diesen Zugang ist kein aktiver Mitarbeiter hinterlegt.'],
            ]);
        }

        $token = $user->createToken($credentials['device_name'], ['mobile-app'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('employee.departments');

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'employee' => $user->employee ? [
                'id' => $user->employee->id,
                'employee_number' => $user->employee->employee_number,
                'first_name' => $user->employee->first_name,
                'last_name' => $user->employee->last_name,
                'position' => $user->employee->position,
            ] : null,
        ];
    }
}
