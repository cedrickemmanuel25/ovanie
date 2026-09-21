<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public $timestamps = true;

    /**
     * Récupère la valeur d'une clé, ou une valeur par défaut si la clé n'existe pas.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Met à jour ou crée une clé avec sa valeur.
     *
     * @param string $key
     * @param mixed $value
     * @return Setting
     */
    public static function setValue(string $key, $value): Setting
    {
        return self::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
