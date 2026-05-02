<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $table = 'notifications_log';

    protected $fillable = [
        'user_id', 'title', 'body', 'type', 'data',
        'sent', 'read', 'sent_at', 'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'sent'    => 'boolean',
        'read'    => 'boolean',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
