<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSchedule extends Model
{
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    protected $fillable = ['gym_class_id', 'coach_id', 'co_coach_id', 'day_of_week', 'start_time', 'end_time', 'room', 'capacity'];

    protected $casts = ['day_of_week' => 'integer', 'capacity' => 'integer'];

    public function gymClass(): BelongsTo
    {
        return $this->belongsTo(GymClass::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function coCoach(): BelongsTo
    {
        return $this->belongsTo(Coach::class, 'co_coach_id');
    }

    /** "Coach Mark & Coach Alvin" */
    public function coachLabel(): string
    {
        return $this->coach->shortName() . ($this->coCoach ? ' & ' . $this->coCoach->shortName() : '');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function dayName(): string
    {
        return self::DAYS[$this->day_of_week];
    }

    public function timeRange(): string
    {
        $a = strtotime($this->start_time);
        $b = strtotime($this->end_time);
        return date('A', $a) === date('A', $b)
            ? date('g:i', $a) . '–' . date('g:i A', $b)
            : date('g:i A', $a) . '–' . date('g:i A', $b);
    }
}
