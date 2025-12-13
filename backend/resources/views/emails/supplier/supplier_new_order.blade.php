<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle Commande</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #f97316, #ea580c); padding: 30px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 24px; }
        .header p { color: rgba(255,255,255,0.9); margin: 10px 0 0; }
        .content { padding: 30px; }
        .highlight-box { background: #fff7ed; border-left: 4px solid #f97316; padding: 15px 20px; margin: 20px 0; border-radius: 0 8px 8px 0; }
        .highlight-box .amount { font-size: 28px; font-weight: bold; color: #ea580c; }
        .info-grid { display: grid; gap: 15px; margin: 20px 0; }
        .info-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .info-item .label { color: #666; }
        .info-item .value { font-weight: 600; color: #333; }
        .btn { display: inline-block; background: linear-gradient(135deg, #f97316, #ea580c); color: #fff !important; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; margin: 20px 0; }
        .btn:hover { opacity: 0.9; }
        .footer { background: #f8f9fa; padding: 20px 30px; text-align: center; color: #666; font-size: 13px; }
        .footer a { color: #f97316; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Nouvelle Commande</h1>
            <p>de {{ $tenant_name }}</p>
        </div>
        <div class="content">
            <p>Bonjour <strong>{{ $supplier_name }}</strong>,</p>
            <p>Vous avez reçu une nouvelle commande d'achat. Veuillez la consulter et confirmer sa prise en charge.</p>
            
            <div class="highlight-box">
                <div>Commande <strong>#{{ $reference }}</strong></div>
                <div class="amount">{{ $total }}</div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Client</span>
                    <span class="value">{{ $tenant_name }}</span>
                </div>
                <div class="info-item">
                    <span class="label">Nombre d'articles</span>
                    <span class="value">{{ $items_count }} produit(s)</span>
                </div>
                <div class="info-item">
                    <span class="label">Livraison souhaitée</span>
                    <span class="value">{{ $expected_date }}</span>
                </div>
                @if($notes)
                <div class="info-item">
                    <span class="label">Notes</span>
                    <span class="value">{{ $notes }}</span>
                </div>
                @endif
            </div>
            
            <div style="text-align: center;">
                <a href="{{ $direct_link }}" class="btn">Voir la commande →</a>
            </div>
            
            <p style="color: #666; font-size: 14px; margin-top: 30px;">
                Cliquez sur le bouton ci-dessus pour accéder directement à la commande et la confirmer.
            </p>
        </div>
        <div class="footer">
            <p>Cet email a été envoyé automatiquement par <a href="#">SIGEC</a></p>
            <p>© {{ date('Y') }} SIGEC - Système de Gestion Commerciale</p>
        </div>
    </div>
</body>
</html>
