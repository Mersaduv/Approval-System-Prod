<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Request extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'item',
        'description',
        'amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function procurement()
    {
        return $this->hasOne(Procurement::class);
    }
}
