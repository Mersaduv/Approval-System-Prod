<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinanceAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Get the user that owns this finance assignment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Get the assignable entity that owns this finance assignment
     */
    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get all active finance users
     */
    public static function getActiveFinanceUsers()
    {
        return User::whereHas('department', function($query) {
            $query->where('name', 'Finance');
        })->where('is_active', true)->get();
    }

    /**
     * Check if a user is a finance user
     */
    public static function isFinanceUser($userId)
    {
        return User::where('id', $userId)
            ->whereHas('department', function($query) {
                $query->where('name', 'Finance');
            })
            ->exists();
    }
}
