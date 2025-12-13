<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande Expédiée</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #8b5cf6, #7c3aed); padding: 30px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .shipping-box { background: #f5f3ff; border-left: 4px solid #8b5cf6; padding: 15px 20px; margin: 20px 0; border-radius: 0 8px 8px 0; }
        .info-grid { display: grid; gap: 15px; margin: 20px 0; }
        .info-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .info-item .label { color: #666; }
        .info-item .value { font-weight: 600; color: #333; }
        .btn { display: inline-block; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: #fff !important; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; margin: 20px 0; }
        .footer { background: #f8f9fa; padding: 20px 30px; text-align: center; color: #666; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚚 Commande Expédiée</h1>
        </div>
        <div class="content">
            <p>Bonjour <strong>{{ $user_name }}</strong>,</p>
            <p>Votre commande a été préparée et est en cours de livraison.</p>
            
            <div class="shipping-box">
                <div>Commande <strong>#{{ $reference }}</strong> expédiée par <strong>{{ $supplier_name }}</strong></div>
                @if($tracking_number && $tracking_number !== 'Non disponible')
                <div style="margin-top: 10px;">N° de suivi: <strong>{{ $tracking_number }}</strong></div>
                @endif
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Fournisseur</span>
                    <span class="value">{{ $supplier_name }}</span>
                </div>
                <div class="info-item">
                    <span class="label">Expédié le</span>
                    <span class="value">{{ $shipped_at }}</span>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="{{ $direct_link }}" class="btn">Suivre la commande →</a>
            </div>
            
            <p style="color: #666; font-size: 14px; margin-top: 20px;">
                Préparez-vous à réceptionner cette livraison.
            </p>
        </div>
        <div class="footer">
            <p>© {{ date('Y') }} SIGEC - Système de Gestion Commerciale</p>
        </div>
    </div>
</body>
</html>
