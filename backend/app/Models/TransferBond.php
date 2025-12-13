<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransferBond extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'transfer_id',
        'bond_number',
        'description',
        'issued_at',
        'executed_at',
        'status',
        'issued_by',
        'received_by',
        'notes',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
