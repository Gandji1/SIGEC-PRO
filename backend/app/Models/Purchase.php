<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, Auditable;

    // Statuts du flux de commande:
    // draft -> pending_approval (attente approbation Tenant) -> submitted (envoyé au fournisseur)
    // -> confirmed (fournisseur confirme) -> shipped (préparé) -> delivered (livré) -> received (réceptionné)
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_APPROVAL = 'pending_approval'; // Attente approbation Tenant
    const STATUS_SUBMITTED = 'submitted'; // Envoyé au fournisseur
    const STATUS_CONFIRMED = 'confirmed'; // Fournisseur a confirmé
    const STATUS_SHIPPED = 'shipped'; // Fournisseur a préparé
    const STATUS_DELIVERED = 'delivered'; // Fournisseur a livré
    const STATUS_RECEIVED = 'received'; // Réceptionné par le tenant/gérant
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'warehouse_id',
        'supplier_id',
        'reference',
        'supplier_name',
        'supplier_phone',
        'supplier_email',
        'subtotal',
        'tax_amount',
        'shipping_cost',
        'total',
        'amount_paid',
        'payment_method',
        'status',
        'expected_date',
        'expected_delivery_date',
        'received_date',
        'notes',
        'metadata',
        // Flux d'approbation
        'created_by_user_id', // Gérant qui a créé
        'approved_by_user_id', // Tenant qui a approuvé
        'approved_at', // Date d'approbation par Tenant
        'submitted_at',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'received_at',
        'paid_at',
        'supplier_notes',
        'tracking_number',
        'delivery_proof',
        'payment_validated_by_supplier', // Flag: fournisseur confirme avoir reçu le paiement
        'payment_validated_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'expected_date' => 'date',
        'expected_delivery_date' => 'date',
        'received_date' => 'date',
        'metadata' => 'array',
        'approved_at' => 'datetime',
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'received_at' => 'datetime',
        'paid_at' => 'datetime',
        'payment_validated_by_supplier' => 'boolean',
        'payment_validated_at' => 'datetime',
    ];

    // Relations supplémentaires
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function receive(): void
    {
        $this->status = 'received';
        $this->received_date = now();
        $this->save();

        // Update supplier totals if linked
        if ($this->supplier_id) {
            $this->supplier->updateTotals();
        }
    }

    public function calculateTotals(): void
    {
        $this->subtotal = $this->items()->sum('line_subtotal') ?: 0;
        $this->tax_amount = $this->items()->sum('tax_amount') ?: 0;
        $this->total = $this->subtotal + ($this->tax_amount ?: 0) + ($this->shipping_cost ?: 0);
        $this->save();
    }
}
