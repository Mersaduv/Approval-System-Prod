<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'department_id',
        'role_id',
        'permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'permissions' => 'array',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin()
    {
        return $this->role && $this->role->isAdmin();
    }

    /**
     * Check if user is manager
     */
    public function isManager()
    {
        return $this->role && $this->role->isManager();
    }

    /**
     * Check if user is employee
     */
    public function isEmployee()
    {
        return $this->role && $this->role->isEmployee();
    }

    /**
     * Check if user is procurement
     */
    public function isProcurement()
    {
        return $this->role && $this->role->isProcurement();
    }

    /**
     * Check if user can approve requests
     */
    public function canApprove()
    {
        return $this->isAdmin() || $this->isManager();
    }

    /**
     * Check if user can process procurement
     */
    public function canProcessProcurement()
    {
        return $this->isProcurement() || $this->isAdmin();
    }

    /**
     * Check if user can manage department
     */
    public function canManageDepartment($departmentId)
    {
        return $this->isAdmin() || ($this->isManager() && $this->department_id == $departmentId);
    }

    public function requests()
    {
        return $this->hasMany(Request::class, 'employee_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'receiver_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
