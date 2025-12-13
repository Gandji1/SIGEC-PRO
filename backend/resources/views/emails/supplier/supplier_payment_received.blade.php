<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Reçu</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #3b82f6, #2563eb); padding: 30px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .payment-box { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 20px; margin: 20px 0; border-radius: 0 8px 8px 0; text-align: center; }
        .payment-box .amount { font-size: 32px; font-weight: bold; color: #2563eb; }
        .payment-box .label { color: #666; font-size: 14px; }
        .info-grid { display: grid; gap: 15px; margin: 20px 0; }
        .info-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .info-item .label { color: #666; }
        .info-item .value { font-weight: 600; color: #333; }
        .btn { display: inline-block; background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff !important; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; margin: 20px 0; }
        .footer { background: #f8f9fa; padding: 20px 30px; text-align: center; color: #666; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Paiement Reçu</h1>
        </div>
        <div class="content">
            <p>Bonjour <strong>{{ $supplier_name }}</strong>,</p>
            <p>Vous avez reçu un paiement pour la commande #{{ $reference }}.</p>
            
            <div class="payment-box">
                <div class="label">Montant reçu</div>
                <div class="amount">{{ $amount }}</div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Commande</span>
                    <span class="value">#{{ $reference }}</span>
                </div>
                <div class="info-item">
                    <span class="label">Client</span>
                    <span class="value">{{ $tenant_name }}</span>
                </div>
                <div class="info-item">
                    <span class="label">Total commande</span>
                    <span class="value">{{ $total }}</span>
                </div>
                <div class="info-item">
                    <span class="label">Total payé</span>
                    <span class="value">{{ $amount_paid }}</span>
                </div>
                <div class="info-item">
                    <span class="label">Reste à payer</span>
                    <span class="value">{{ $remaining }}</span>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="{{ $direct_link }}" class="btn">Voir la commande →</a>
            </div>
        </div>
        <div class="footer">
            <p>© {{ date('Y') }} SIGEC - Système de Gestion Commerciale</p>
        </div>
    </div>
</body>
</html>
