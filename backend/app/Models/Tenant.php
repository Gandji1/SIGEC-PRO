<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'logo',
        'currency',
        'country',
        'phone',
        'email',
        'address',
        'tax_id',
        'registration_number',
        'business_type',
        'accounting_setup_complete',
        'settings',
        'status',
        'plan_id',
        'storage_used_mb',
        'last_activity_at',
        'subscription_expires_at',
        'mode_pos',
        'accounting_enabled',
        'tva_rate',
        'default_markup',
        'stock_policy',
        'payment_methods',
        'pos_configuration',
    ];

    protected $casts = [
        'settings' => 'array',
        'subscription_expires_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function chartOfAccounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class);
    }

    public function pos(): HasMany
    {
        return $this->hasMany(Pos::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && 
               (!$this->subscription_expires_at || $this->subscription_expires_at->isFuture());
    }

    /**
     * Relation vers le plan d'abonnement
     */
    public function plan()
    {
        return $this->belongsTo(\App\Models\System\SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Relation vers les abonnements
     */
    public function subscriptions()
    {
        return $this->hasMany(\App\Models\System\Subscription::class);
    }

    /**
     * Relation vers les modules activés
     */
    public function enabledModules()
    {
        return $this->hasMany(\App\Models\System\TenantModule::class)->where('is_enabled', true);
    }

    /**
     * Abonnement actif
     */
    public function activeSubscription()
    {
        return $this->hasOne(\App\Models\System\Subscription::class)
            ->whereIn('status', ['active', 'trial'])
            ->latest();
    }

    /**
     * Mode A = Commerce/Magasin (vente directe sans workflow POS)
     */
    public function isModeA(): bool
    {
        return $this->business_type === 'commerce' || $this->business_type === 'retail' || $this->business_type === 'A';
    }

    /**
     * Mode B = Restaurant/Bar (workflow POS avec serveur/cuisine/gérant)
     */
    public function isModeB(): bool
    {
        return $this->business_type === 'restaurant' || $this->business_type === 'bar' || $this->business_type === 'B';
    }

    /**
     * Relation vers les fournisseurs
     */
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }
}
