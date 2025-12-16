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

class PasswordResetController extends Controller
{
    /**
     * Envoyer un lien de réinitialisation de mot de passe
     */
    public function sendResetLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Toujours retourner succès pour éviter l'énumération d'emails
        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'Si cette adresse email existe, un lien de réinitialisation a été envoyé.',
            ]);
        }

        // Générer un token unique
        $token = Str::random(64);

        // Supprimer les anciens tokens pour cet email
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        // Insérer le nouveau token
        DB::table('password_reset_tokens')->insert([
            'email' => $validated['email'],
            'token' => Hash::make($token),
            'created_at' => Carbon::now(),
        ]);

        // Construire l'URL de réinitialisation
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $resetUrl = "{$frontendUrl}/reset-password?token={$token}&email=" . urlencode($validated['email']);

        // Envoyer l'email
        try {
            Mail::send('emails.password-reset', [
                'user' => $user,
                'resetUrl' => $resetUrl,
                'token' => $token,
            ], function ($message) use ($user) {
                $message->to($user->email, $user->name)
                    ->subject('Réinitialisation de votre mot de passe - SIGEC');
            });

            \Log::info('Password reset email sent', ['email' => $validated['email']]);
        } catch (\Exception $e) {
            \Log::error('Failed to send password reset email', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);
            
            // En dev, retourner le token directement
            if (env('APP_ENV') === 'local') {
                return response()->json([
                    'success' => true,
                    'message' => 'Email non envoyé (mode dev)',
                    'debug_token' => $token,
                    'debug_url' => $resetUrl,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Si cette adresse email existe, un lien de réinitialisation a été envoyé.',
        ]);
    }

    /**
     * Vérifier la validité d'un token
     */
    public function verifyToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$record) {
            return response()->json([
                'valid' => false,
                'message' => 'Token invalide ou expiré.',
            ], 400);
        }

        // Vérifier si le token n'est pas expiré (60 minutes)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            return response()->json([
                'valid' => false,
                'message' => 'Token expiré. Veuillez demander un nouveau lien.',
            ], 400);
        }

        // Vérifier le token
        if (!Hash::check($validated['token'], $record->token)) {
            return response()->json([
                'valid' => false,
                'message' => 'Token invalide.',
            ], 400);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Token valide.',
        ]);
    }

    /**
     * Réinitialiser le mot de passe
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide ou expiré.',
            ], 400);
        }

        // Vérifier si le token n'est pas expiré (60 minutes)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
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

        // Mettre à jour le mot de passe
        $user = User::where('email', $validated['email'])->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé.',
            ], 404);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Supprimer le token utilisé
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        // Révoquer tous les tokens d'accès existants (déconnexion de toutes les sessions)
        $user->tokens()->delete();

        \Log::info('Password reset successful', ['email' => $validated['email']]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.',
        ]);
    }
}
