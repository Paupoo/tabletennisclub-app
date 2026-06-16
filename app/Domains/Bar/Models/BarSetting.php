<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarSetting extends Model
{
    protected $table = 'bar_settings';

    // ✅ Primary key is "k" instead of "id"
    protected $primaryKey = 'k';

    // ✅ Key is not auto-incrementing
    public $incrementing = false;

    // ✅ Key is string
    protected $keyType = 'string';

    // ✅ Fillable fields
    protected $fillable = [
        'k',
        'v',
    ];
}