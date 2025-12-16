<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #f97316;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #f97316;
        }
        h1 {
            color: #1f2937;
            font-size: 22px;
            margin-bottom: 15px;
        }
        p {
            margin-bottom: 15px;
            color: #4b5563;
        }
        .button {
            display: inline-block;
            background: #f97316;
            color: white !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
        }
        .button:hover {
            background: #ea580c;
        }
        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px;
            margin: 20px 0;
            font-size: 14px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #9ca3af;
        }
        .link-fallback {
            word-break: break-all;
            font-size: 12px;
            color: #6b7280;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">SIGEC</div>
            <p style="margin: 0; color: #6b7280;">Système Intégré de Gestion</p>
        </div>

        <h1>Réinitialisation de votre mot de passe</h1>

        <p>Bonjour {{ $user->name }},</p>

        <p>Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe :</p>

        <div style="text-align: center;">
            <a href="{{ $resetUrl }}" class="button">Réinitialiser mon mot de passe</a>
        </div>

        <div class="warning">
            <strong>⚠️ Ce lien expire dans 60 minutes.</strong><br>
            Si vous n'avez pas demandé cette réinitialisation, ignorez simplement cet email.
        </div>

        <p class="link-fallback">
            Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
            {{ $resetUrl }}
        </p>

        <div class="footer">
            <p>Cet email a été envoyé automatiquement par SIGEC.</p>
            <p>© {{ date('Y') }} SIGEC - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>
