<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'role_id',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function approvalRules()
    {
        return $this->hasMany(ApprovalRule::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
