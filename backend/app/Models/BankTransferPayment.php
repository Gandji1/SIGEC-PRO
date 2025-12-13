<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paiements par virement bancaire (validation manuelle)
 */
class BankTransferPayment extends Model
{
    use BelongsToTenant;

    protected $table = 'bank_transfer_payments';

    protected $fillable = [
        'tenant_id',
        'reference',
        'type',           // sale, subscription
        'related_id',     // sale_id ou subscription_id
        'amount',
        'currency',
        'status',         // pending, confirmed, cancelled
        'bank_reference', // Référence bancaire fournie par client
        'proof_document', // Chemin vers preuve de paiement
        'notes',
        'confirmed_by',
        'confirmed_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // ========================================
    // RELATIONS
    // ========================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Get related sale if type is 'sale'
     */
    public function sale(): ?BelongsTo
    {
        if ($this->type !== 'sale') return null;
        return $this->belongsTo(Sale::class, 'related_id');
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '<', now());
    }

    // ========================================
    // MÉTHODES
    // ========================================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isExpired(): bool
    {
        return $this->status === 'pending' && $this->expires_at && $this->expires_at->isPast();
    }
}
