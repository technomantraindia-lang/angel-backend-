<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlashMessage extends Model
{
    protected $fillable = [
        'type',
        'text',
        'image',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
