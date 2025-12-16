<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EmailVerificationController extends Controller
{
    /**
     * Envoyer un email de vérification
     */
    public function sendVerificationEmail(Request $request): JsonResponse
    {
        $user = auth()->guard('sanctum')->user();

        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Email déjà vérifié.',
                'verified' => true,
            ]);
        }

        // Générer un token unique
        $token = Str::random(64);

        // Supprimer les anciens tokens pour cet utilisateur
        DB::table('email_verification_tokens')->where('user_id', $user->id)->delete();

        // Insérer le nouveau token
        DB::table('email_verification_tokens')->insert([
            'user_id' => $user->id,
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => Carbon::now(),
        ]);

        // Construire l'URL de vérification
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $verifyUrl = "{$frontendUrl}/verify-email?token={$token}&email=" . urlencode($user->email);

        // Envoyer l'email
        try {
            Mail::send('emails.verify-email', [
                'user' => $user,
                'verifyUrl' => $verifyUrl,
            ], function ($message) use ($user) {
                $message->to($user->email, $user->name)
                    ->subject('Vérifiez votre adresse email - SIGEC');
            });

            \Log::info('Verification email sent', ['email' => $user->email]);
        } catch (\Exception $e) {
            \Log::error('Failed to send verification email', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            // En dev, retourner le token directement
            if (env('APP_ENV') === 'local') {
                return response()->json([
                    'success' => true,
                    'message' => 'Email non envoyé (mode dev)',
                    'debug_token' => $token,
                    'debug_url' => $verifyUrl,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Email de vérification envoyé.',
        ]);
    }

    /**
     * Vérifier l'email avec le token
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        $record = DB::table('email_verification_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide ou expiré.',
            ], 400);
        }

        // Vérifier si le token n'est pas expiré (24 heures)
        if (Carbon::parse($record->created_at)->addHours(24)->isPast()) {
            DB::table('email_verification_tokens')->where('email', $validated['email'])->delete();
            return response()->json([
                'success' => false,
                'message' => 'Token expiré. Veuillez demander un nouveau lien.',
            ], 400);
        }

        // Vérifier le token
        if (!Hash::check($validated['token'], $record->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide.',
            ], 400);
        }

        // Marquer l'email comme vérifié
        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé.',
            ], 404);
        }

        $user->update([
            'email_verified_at' => Carbon::now(),
        ]);

        // Supprimer le token utilisé
        DB::table('email_verification_tokens')->where('email', $validated['email'])->delete();

        \Log::info('Email verified', ['email' => $validated['email']]);

        return response()->json([
            'success' => true,
            'message' => 'Email vérifié avec succès.',
            'user' => $user,
        ]);
    }

    /**
     * Vérifier le statut de vérification
     */
    public function status(): JsonResponse
    {
        $user = auth()->guard('sanctum')->user();

        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }

        return response()->json([
            'verified' => $user->email_verified_at !== null,
            'email' => $user->email,
            'verified_at' => $user->email_verified_at,
        ]);
    }
}
