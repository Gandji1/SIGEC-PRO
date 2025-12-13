<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;

class PosOrder extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'pos_id',
        'sale_id',
        'customer_id',
        'table_number',
        'reference',
        'status',
        'preparation_status',
        'payment_status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'amount_paid',
        'payment_method',
        'payment_reference',
        'external_payment_ref',
        'created_by',
        'served_by',
        'validated_by',
        'approved_by',
        'served_at',
        'paid_at',
        'validated_at',
        'approved_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'served_at' => 'datetime',
        'paid_at' => 'datetime',
        'validated_at' => 'datetime',
        'approved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function servedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    public function validatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosOrderItem::class);
    }

    public function remises(): HasMany
    {
        return $this->hasMany(PosRemise::class);
    }

    public function calculateTotals(): void
    {
        $this->subtotal = $this->items()->sum('line_total');
        $this->tax_amount = $this->items()
            ->selectRaw('SUM(line_total * tax_percent / 100) as tax')
            ->value('tax') ?? 0;
        $this->total = $this->subtotal + $this->tax_amount - $this->discount_amount;
        $this->save();
    }

    public function recalculateTotal(): void
    {
        $subtotal = $this->items()->sum(\DB::raw('quantity * unit_price'));
        $this->subtotal = $subtotal;
        $this->total = $subtotal + ($this->tax_amount ?? 0) - ($this->discount_amount ?? 0);
        $this->save();
    }

    /**
     * Calculer le coût des marchandises vendues (CMV / Charges Variables)
     * CMV = somme(quantité * coût unitaire) pour chaque item
     */
    public function getCostOfGoodsSold(): float
    {
        return (float) $this->items()->sum(\DB::raw('COALESCE(quantity_ordered, 1) * COALESCE(unit_cost, 0)'));
    }

    /**
     * Calculer le bénéfice brut (CA - CMV)
     */
    public function getGrossProfit(): float
    {
        return (float) $this->total - $this->getCostOfGoodsSold();
    }

    /**
     * Calculer la marge brute en pourcentage
     */
    public function getGrossMarginPercent(): float
    {
        if ($this->total <= 0) return 0;
        return ($this->getGrossProfit() / $this->total) * 100;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPreparing(): bool
    {
        return $this->status === 'preparing';
    }

    public function isServed(): bool
    {
        return in_array($this->status, ['served', 'paid', 'validated']);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'validated']);
    }

    public function isValidated(): bool
    {
        return $this->status === 'validated';
    }

    public function markServed(int $userId): void
    {
        $this->status = 'served';
        $this->served_by = $userId;
        $this->served_at = now();
        $this->save();
    }

    public function markPaid(string $paymentMethod, string $paymentReference = null): void
    {
        $this->status = 'paid';
        $this->payment_method = $paymentMethod;
        $this->payment_reference = $paymentReference;
        $this->amount_paid = $this->total;
        $this->paid_at = now();
        $this->save();

        // Enregistrer le mouvement de caisse
        $this->recordCashMovement($paymentMethod);
    }

    /**
     * Enregistrer le mouvement de caisse pour cette commande
     */
    protected function recordCashMovement(string $paymentMethod): void
    {
        try {
            $session = CashRegisterSession::getOpenSession($this->tenant_id);
            CashMovement::record(
                $this->tenant_id,
                $this->created_by ?? auth()->id(),
                'in',
                'sale',
                $this->total,
                "Commande POS {$this->reference}",
                $session?->id,
                $this->pos_id,
                $paymentMethod,
                $this->id,
                'pos_order'
            );
        } catch (\Exception $e) {
            \Log::warning("Cash movement recording failed for POS order {$this->id}: " . $e->getMessage());
        }
    }

    public function validate(int $userId): void
    {
        $this->status = 'validated';
        $this->validated_by = $userId;
        $this->validated_at = now();
        $this->save();
    }

    public static function generateReference(int $tenantId): string
    {
        $today = now()->format('Ymd');
        $count = self::where('tenant_id', $tenantId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return "ORD-$today-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}
