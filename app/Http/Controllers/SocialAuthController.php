<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirectToProvider($provider)
    {
        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function handleProviderCallback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            $user = User::where('email', $socialUser->getEmail())->first();

            if (!$user) {
                $user = User::create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                    'email' => $socialUser->getEmail(),
                    'password' => Hash::make(Str::random(16)),
                    'role' => 'patient',
                ]);
            }

            $token = $user->createToken('med_api_token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token,
                'dashboard' => $user->role === 'doctor' ? 'doctor_dashboard' : 'patient_dashboard',
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Authentication failed', 'details' => $e->getMessage()], 500);
        }
    }
}
