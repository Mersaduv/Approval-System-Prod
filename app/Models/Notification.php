<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'receiver_id',
        'channel',
        'message',
        'link',
        'status',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id')->withTrashed();
    }
}
