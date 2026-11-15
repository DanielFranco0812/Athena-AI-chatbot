<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Editable gym details (hours, address, contact) used by the website and Athena AI.
 */
class Setting extends Model
{
    public const FIELDS = [
        'gym_name' => 'Gym name',
        'address' => 'Address',
        'landmark' => 'Landmark / directions',
        'phone' => 'Phone',
        'email' => 'Email',
        'facebook_url' => 'Facebook page',
        'hours_weekdays' => 'Hours: Monday to Saturday',
        'hours_sunday' => 'Hours: Sunday',
        'hours_holidays' => 'Hours: Holidays',
        'pt_price' => 'Personal training (price or note)',
        'freestyle_note' => 'Freestyle / open gym note',
        'payment_methods' => 'Payment methods',
        'amenities' => 'Amenities',
        'parking' => 'Parking',
        'promo' => 'Current promo with full details (leave empty if none)',
        'promo_teaser' => 'Promo teaser, no details yet (e.g. Christmas surprise)',
    ];

    protected $fillable = ['key', 'value'];

    public static function all_values(): array
    {
        return Cache::remember('gym_settings', 600, fn () => static::query()->pluck('value', 'key')->all());
    }

    public static function get(string $key, $default = null)
    {
        return static::all_values()[$key] ?? $default;
    }

    public static function put(array $values): void
    {
        foreach ($values as $k => $v) {
            static::updateOrCreate(['key' => $k], ['value' => $v]);
        }
        Cache::forget('gym_settings');
    }
}
