<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountTier extends Model
{
    protected $fillable = ['product_id', 'min', 'max', 'discount', 'print_side'];

    protected $casts = [
        'min' => 'integer',
        'max' => 'integer',
        'discount' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
