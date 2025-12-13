<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosTable extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'pos_tables';

    protected $fillable = [
        'tenant_id',
        'pos_id',
        'number',
        'capacity',
        'zone',
        'status',
        'current_order_id',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pos()
    {
        return $this->belongsTo(Pos::class);
    }

    public function currentOrder()
    {
        return $this->belongsTo(PosOrder::class, 'current_order_id');
    }
}
