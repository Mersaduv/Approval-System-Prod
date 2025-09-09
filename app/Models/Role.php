<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    // Role constants
    const ADMIN = 'admin';
    const MANAGER = 'manager';
    const EMPLOYEE = 'employee';
    const PROCUREMENT = 'procurement';

    protected $fillable = [
        'name',
        'description',
        'permissions',
        'is_active'
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function hasPermission($permission)
    {
        if (!$this->permissions) {
            return false;
        }

        return in_array($permission, $this->permissions) || in_array('*', $this->permissions);
    }

    public function addPermission($permission)
    {
        $permissions = $this->permissions ?? [];
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->update(['permissions' => $permissions]);
        }
    }

    public function removePermission($permission)
    {
        $permissions = $this->permissions ?? [];
        $permissions = array_filter($permissions, function($p) use ($permission) {
            return $p !== $permission;
        });
        $this->update(['permissions' => array_values($permissions)]);
    }

    /**
     * Check if role is admin
     */
    public function isAdmin()
    {
        return $this->name === self::ADMIN;
    }

    /**
     * Check if role is manager
     */
    public function isManager()
    {
        return $this->name === self::MANAGER;
    }

    /**
     * Check if role is employee
     */
    public function isEmployee()
    {
        return $this->name === self::EMPLOYEE;
    }

    /**
     * Check if role is procurement
     */
    public function isProcurement()
    {
        return $this->name === self::PROCUREMENT;
    }
}
