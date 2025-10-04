<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'reason',
        'start_date',
        'end_date',
        'total_days',
        'status',
        'manager_notes',
        'hr_notes',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id')->withTrashed();
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by')->withTrashed();
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'leave_request_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'leave_request_id');
    }

    // Calculate total days automatically
    public static function calculateTotalDays($startDate, $endDate)
    {
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        return $end->diffInDays($start) + 1; // +1 to include both start and end dates
    }

    // Get common leave reasons (for reference only)
    public static function getCommonReasons()
    {
        return [
            'Annual vacation',
            'Sick leave',
            'Emergency leave',
            'Family emergency',
            'Medical appointment',
            'Personal leave',
            'Maternity leave',
            'Paternity leave',
            'Bereavement leave',
            'Other'
        ];
    }

    // Check if leave request can be cancelled
    public function canBeCancelled()
    {
        return in_array($this->status, ['Pending', 'Pending Approval']);
    }

    // Check if leave request can be approved
    public function canBeApproved()
    {
        return in_array($this->status, ['Pending', 'Pending Approval']);
    }

    // Check if leave request can be rejected
    public function canBeRejected()
    {
        return in_array($this->status, ['Pending', 'Pending Approval']);
    }
}
