<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Procurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'status',
        'final_cost',
    ];

    protected $casts = [
        'final_cost' => 'decimal:2',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class);
    }
}
