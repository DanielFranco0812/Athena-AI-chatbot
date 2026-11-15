<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GymClass extends Model
{
    public const CATEGORIES = [
        'strength' => ['label' => 'Strength', 'color' => 'red'],
        'cardio' => ['label' => 'Cardio', 'color' => 'yellow'],
        'combat' => ['label' => 'Combat', 'color' => 'blue'],
        'recovery' => ['label' => 'Recovery', 'color' => 'green'],
    ];

    protected $fillable = ['name', 'category', 'intensity', 'description'];

    public function color(): string
    {
        return self::CATEGORIES[$this->category]['color'] ?? 'blue';
    }
}
