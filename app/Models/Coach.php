<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coach extends Model
{
    protected $fillable = ['name', 'role', 'nickname', 'specialty', 'years_experience', 'bio', 'certifications', 'specialties', 'pt_hours', 'color', 'photo'];

    public function scopeCoaches($q)
    {
        return $q->where('role', 'coach');
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['staff', 'owner'], true);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }

    /** Short name used on the schedule, e.g. "Coach Mark". */
    public function shortName(): string
    {
        return ($this->isStaff() ? '' : 'Coach ') . explode(' ', trim($this->name))[0];
    }

    public function certificationList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $this->certifications))));
    }

    /** @return array<int, array{0: string, 1: string}> [name, description] */
    public function specialtyList(): array
    {
        return array_map(fn ($l) => array_map('trim', explode(':', $l, 2)) + [1 => ''],
            array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $this->specialties)))));
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? asset($this->photo) : null;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));
        return count($parts) === 1
            ? strtoupper(mb_substr($parts[0], 0, 2))
            : strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }
}
