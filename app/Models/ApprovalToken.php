<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ApprovalToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'request_id',
        'approver_id',
        'action_type',
        'expires_at',
        'usage_count',
        'max_usage',
        'is_used'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean'
    ];

    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    public function isExhausted()
    {
        return $this->usage_count >= $this->max_usage;
    }

    public function isUsed()
    {
        return $this->is_used;
    }

    public function isValid()
    {
        return !$this->is_used && !$this->isExpired() && !$this->isExhausted();
    }

    public function markAsUsed()
    {
        $this->update([
            'usage_count' => $this->usage_count + 1,
            'is_used' => $this->usage_count + 1 >= $this->max_usage
        ]);
    }

    public static function generateToken()
    {
        return bin2hex(random_bytes(32));
    }

    public static function createToken($requestId, $approverId, $actionType = 'approve', $expiryHours = 48)
    {
        return self::create([
            'token' => self::generateToken(),
            'request_id' => $requestId,
            'approver_id' => $approverId,
            'action_type' => $actionType,
            'expires_at' => Carbon::now()->addHours($expiryHours),
            'max_usage' => 1,
            'usage_count' => 0,
            'is_used' => false
        ]);
    }
}
