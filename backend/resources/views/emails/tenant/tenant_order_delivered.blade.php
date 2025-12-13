<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande Livrée - Action Requise</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #f97316, #ea580c); padding: 30px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 24px; }
        .header .urgent { background: #fff; color: #ea580c; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; display: inline-block; margin-top: 10px; }
        .content { padding: 30px; }
        .alert-box { background: #fff7ed; border: 2px solid #f97316; padding: 20px; margin: 20px 0; border-radius: 8px; text-align: center; }
        .alert-box .icon { font-size: 40px; }
        .alert-box .title { font-size: 18px; font-weight: bold; color: #ea580c; margin: 10px 0; }
        .info-grid { display: grid; gap: 15px; margin: 20px 0; }
        .info-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .info-item .label { color: #666; }
        .info-item .value { font-weight: 600; color: #333; }
        .btn { display: inline-block; background: linear-gradient(135deg, #f97316, #ea580c); color: #fff !important; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 20px 0; }
        .footer { background: #f8f9fa; padding: 20px 30px; text-align: center; color: #666; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Commande Livrée</h1>
            <span class="urgent">ACTION REQUISE</span>
        </div>
        <div class="content">
            <p>Bonjour <strong>{{ $user_name }}</strong>,</p>
            
            <div class="alert-box">
                <div class="icon">📦</div>
                <div class="title">Livraison à réceptionner</div>
                <div>La commande <strong>#{{ $reference }}</strong> de <strong>{{ $supplier_name }}</strong> a été livrée.</div>
                <div style="margin-top: 10px; font-size: 20px; font-weight: bold;">{{ $total }}</div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Fournisseur</span>
                    <span class="value">{{ $supplier_name }}</span>
                </div>
                <div class="info-item">
                    <span class="label">Livré le</span>
                    <span class="value">{{ $delivered_at }}</span>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="{{ $direct_link }}" class="btn">Réceptionner maintenant →</a>
            </div>
            
            <p style="color: #ea580c; font-size: 14px; margin-top: 20px; text-align: center;">
                ⚠️ Veuillez vérifier et réceptionner cette livraison dès que possible.
            </p>
        </div>
        <div class="footer">
            <p>© {{ date('Y') }} SIGEC - Système de Gestion Commerciale</p>
        </div>
    </div>
</body>
</html>
