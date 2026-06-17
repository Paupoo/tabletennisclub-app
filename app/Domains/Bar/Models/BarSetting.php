<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarSetting extends Model
{
    // ✅ Key is not auto-incrementing
    public $incrementing = false;

    // ✅ Fillable fields
    protected $fillable = [
        'k',
        'v',
    ];

    // ✅ Key is string
    protected $keyType = 'string';

    // ✅ Primary key is "k" instead of "id"
    protected $primaryKey = 'k';

    protected $table = 'bar_settings';
}
