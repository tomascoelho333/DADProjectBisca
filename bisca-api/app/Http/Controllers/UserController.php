<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\User;

class UserController extends Controller
{
    // Show own profile
    public function show(Request $request)
    {
        $userId = $request->user()->id;
        return Cache::remember("user_profile_{$userId}", 60, function () use ($userId) {
            // Gets the user if not in cache
            return User::find($userId);
        });
    }

    // Update own profile
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // Email Should be unique but not counting with the current email
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
            // Same thing applies to the nickname
            'nickname' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users')->ignore($user->id),
            ],
            // Password is not obligatory
            'password' => 'sometimes|string|min:3|confirmed',
            // Photo is not obligatory
            'photo_avatar_filename' => 'nullable|image|max:2048', // Max 2MB
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->nickname = $validated['nickname'];

        // Update password
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        if ($request->hasFile('photo_avatar_filename')) {
            if ($user->photo_avatar_filename && Storage::disk('public')->exists('photos_avatars/' . $user->photo_avatar_filename)) {
                Storage::disk('public')->delete('photos_avatars/' . $user->photo_avatar_filename);
            }

            //'storage/app/public/photos_avatars'
            $path = $request->file('photo_avatar_filename')->store('photos_avatars', 'public');

            // Only write the filename on the db
            $user->photo_avatar_filename = basename($path);
        }

        $user->save();

        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user
        ]);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user->type === 'A') {
            return response()->json([
                'message' => 'Admins cannot delete their own accounts'
            ], 403); // Forbidden
        }

        $request->validate([
            'password' => 'required'
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Incorrect Password. Try again.'
            ], 403);
        }

        $user->delete();

        // Forfeits all coins associated with the account
        $user->coins_balance = 0;

        // Revokes tokens, basically logs out forcefully
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Account deleted successfully.'
        ]);
    }

}
