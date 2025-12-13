<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementDocument extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'document_number',
        'type',
        'description',
        'issued_at',
        'due_date',
        'received_at',
        'status',
        'purchase_id',
        'supplier_id',
        'issued_by',
        'approved_by',
        'received_by',
        'total_amount',
        'terms_conditions',
        'notes',
        'attachment_path',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'due_date' => 'datetime',
        'received_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function getStatusBadgeAttribute()
    {
        return match ($this->status) {
            'draft' => 'secondary',
            'issued' => 'primary',
            'approved' => 'info',
            'received' => 'success',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }
}
