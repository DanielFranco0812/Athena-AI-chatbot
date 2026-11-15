<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = ['user_id', 'class_schedule_id', 'session_date', 'status'];

    protected $casts = ['session_date' => 'date:Y-m-d'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class, 'class_schedule_id');
    }

    public function startsAt(): int
    {
        return strtotime($this->session_date->format('Y-m-d') . ' ' . $this->schedule->start_time);
    }
}
