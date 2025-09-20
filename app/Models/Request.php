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
        'procurement_status',
        'final_price',
        'procurement_notes',
        'verified_by',
        'verified_at',
        'bill_number',
        'bill_printed_at',
        'bill_printed_by',
        'bill_notes',
        'bill_amount',
        'bill_status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'final_price' => 'decimal:2',
        'verified_at' => 'datetime',
        'bill_printed_at' => 'datetime',
        'bill_amount' => 'decimal:2',
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
        return $this->hasMany(AuditLog::class, 'request_id');
    }

    public function procurement()
    {
        return $this->hasOne(Procurement::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function billPrintedBy()
    {
        return $this->belongsTo(User::class, 'bill_printed_by');
    }
}
