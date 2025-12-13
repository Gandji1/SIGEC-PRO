<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;

    // Note: Role est une table système sans tenant_id
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    public function hasPermission($permission)
    {
        return $this->permissions()->where('slug', $permission)->exists();
    }
}
