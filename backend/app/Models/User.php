<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'role',
        'status',
        'permissions',
        'last_login_at',
        'assigned_pos_id',
        'assigned_warehouse_id',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'permissions' => 'array',
        'last_login_at' => 'datetime',
        'two_factor_enabled' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
    ];

    /**
     * Rôles autorisés à créer des ventes/commandes POS
     */
    const SALES_ALLOWED_ROLES = ['pos_server', 'serveur', 'caissier', 'super_admin', 'owner', 'admin', 'manager', 'gerant'];

    /**
     * Rôles de gestion (ne peuvent pas créer de ventes)
     */
    const MANAGEMENT_ROLES = ['owner', 'admin', 'gerant', 'manager'];

    /**
     * Vérifie si l'utilisateur peut créer des ventes
     */
    public function canCreateSales(): bool
    {
        return in_array($this->role, self::SALES_ALLOWED_ROLES);
    }

    /**
     * Vérifie si l'utilisateur est un gestionnaire
     */
    public function isManagement(): bool
    {
        return in_array($this->role, self::MANAGEMENT_ROLES);
    }

    /**
     * Vérifie si l'utilisateur est un serveur
     */
    public function isServer(): bool
    {
        return in_array($this->role, ['pos_server', 'serveur']);
    }

    /**
     * Vérifie si l'utilisateur est un gérant ou admin/owner
     */
    public function isGerant(): bool
    {
        return in_array($this->role, ['gerant', 'manager', 'admin', 'owner', 'super_admin']);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
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

    public function assignedPos(): BelongsTo
    {
        return $this->belongsTo(Pos::class, 'assigned_pos_id');
    }

    public function assignedWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'assigned_warehouse_id');
    }

    /**
     * Tables affiliées au serveur (relation many-to-many)
     */
    public function affiliatedTables(): BelongsToMany
    {
        return $this->belongsToMany(PosTable::class, 'user_pos_tables', 'user_id', 'pos_table_id')
            ->withTimestamps();
    }

    /**
     * POS affiliés au serveur (relation many-to-many)
     */
    public function affiliatedPos(): BelongsToMany
    {
        return $this->belongsToMany(Pos::class, 'user_pos_affiliations', 'user_id', 'pos_id')
            ->withTimestamps();
    }

    /**
     * Synchroniser les affiliations du serveur
     */
    public function syncAffiliations(array $tableIds = [], array $posIds = []): void
    {
        $tenantId = $this->tenant_id;
        
        // Sync tables
        $tableData = collect($tableIds)->mapWithKeys(fn($id) => [$id => ['tenant_id' => $tenantId]])->toArray();
        $this->affiliatedTables()->sync($tableData);
        
        // Sync POS
        $posData = collect($posIds)->mapWithKeys(fn($id) => [$id => ['tenant_id' => $tenantId]])->toArray();
        $this->affiliatedPos()->sync($posData);
    }

    /**
     * Vérifie si le serveur est affilié à une table
     */
    public function isAffiliatedToTable(int $tableId): bool
    {
        return $this->affiliatedTables()->where('pos_table_id', $tableId)->exists();
    }

    /**
     * Vérifie si le serveur est affilié à un POS
     */
    public function isAffiliatedToPos(int $posId): bool
    {
        return $this->affiliatedPos()->where('pos_id', $posId)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []) || $this->isAdmin();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['admin', 'manager']);
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }
}
