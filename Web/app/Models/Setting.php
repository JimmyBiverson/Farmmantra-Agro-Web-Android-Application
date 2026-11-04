<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value', 'group_name'];

    public function scopeGroup($query, string $group)
    {
        return $query->where('group_name', $group);
    }

    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, $value, string $group = 'general'): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value, 'group_name' => $group]);
    }
}
