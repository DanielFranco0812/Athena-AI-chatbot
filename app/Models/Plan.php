<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['name', 'price', 'period', 'class_limit', 'description', 'perks', 'is_featured', 'sort_order'];

    protected $casts = ['price' => 'decimal:2', 'class_limit' => 'integer', 'is_featured' => 'boolean'];

    public function perkList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $this->perks))));
    }

    public function priceLabel(): string
    {
        return '₱' . number_format((float) $this->price, fmod((float) $this->price, 1) ? 2 : 0);
    }

    /** Last valid day for a plan that starts on $start (Y-m-d). */
    public function endDateFrom(string $start): string
    {
        return $this->period === 'day'
            ? $start
            : date('Y-m-d', strtotime("$start +1 month -1 day"));
    }
}
