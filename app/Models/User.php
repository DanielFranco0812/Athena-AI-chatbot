<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'plan_id',
        'plan_started',
        'plan_expires',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'plan_started' => 'date:Y-m-d',
            'plan_expires' => 'date:Y-m-d',
        ];
    }

    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function bookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0];
    }

    /** Is the membership valid on the given date (default today)? */
    public function planActive(?string $on = null): bool
    {
        $on = $on ?? now()->toDateString();
        return $this->plan_id && $this->plan_started && $this->plan_expires
            && $this->plan_started->toDateString() <= $on && $this->plan_expires->toDateString() >= $on;
    }

    /** Classes booked within the current plan period. */
    public function classesUsed(): int
    {
        if (! $this->plan_started) {
            return 0;
        }
        return $this->bookings()->where('status', 'booked')
            ->whereBetween('session_date', [$this->plan_started->toDateString(), $this->plan_expires->toDateString()])
            ->count();
    }

    public function memberNo(): string
    {
        return str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }
}
