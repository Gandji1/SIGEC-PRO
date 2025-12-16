<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de votre email</title>
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
            background: #10b981;
            color: white !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
        }
        .button:hover {
            background: #059669;
        }
        .info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
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

        <h1>Bienvenue sur SIGEC !</h1>

        <p>Bonjour {{ $user->name }},</p>

        <p>Merci de vous être inscrit sur SIGEC. Pour activer votre compte et accéder à toutes les fonctionnalités, veuillez vérifier votre adresse email en cliquant sur le bouton ci-dessous :</p>

        <div style="text-align: center;">
            <a href="{{ $verifyUrl }}" class="button">Vérifier mon email</a>
        </div>

        <div class="info">
            <strong>ℹ️ Ce lien est valide pendant 24 heures.</strong><br>
            Si vous n'avez pas créé de compte sur SIGEC, ignorez simplement cet email.
        </div>

        <p class="link-fallback">
            Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
            {{ $verifyUrl }}
        </p>

        <div class="footer">
            <p>Cet email a été envoyé automatiquement par SIGEC.</p>
            <p>© {{ date('Y') }} SIGEC - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>
