<?php

namespace App\Http\Controllers;

use App\Models\CoinTransaction;
use App\Models\User;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
class AuthController extends Controller
{
    use HasApiTokens, HasFactory, Notifiable;

    public function register(Request $request)
    {
        // Validate input data
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:3', // Minimum 3 chars as per requisite sheet
            'nickname' => 'required|string|max:20|unique:users',
        ]);

        // User register
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'nickname' => $validated['nickname'],
            'blocked' => false,
            'coins_balance' => 10,
            'type' => 'P', // Default being (P)layer, A for (A)dmin
        ]);

        // Bonus transaction Register
        CoinTransaction::create([
            'user_id' => $user->id,
            'transaction_datetime' => now(),
            'coins' => 10,
            'coin_transaction_type_id' => 1, // TODO: Check if its 1 for Credit
            'custom' => 'User Register Bonus',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Registered successfully! You received 10 coins as a welcome bonus.'
        ], 201);
    }

    public function login(Request $request)
    {
        // Login attempt
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid Email & Password Combination'], 401);
        }

        $user = User::where('email', $request['email'])->firstOrFail();

        // Verify if the user is blocked
        if ($user->blocked) {
            return response()->json(['message' => 'Your account is blocked.'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

}
