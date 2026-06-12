<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2COrderItem extends Model
{
    use HasFactory;

    protected $table = 'b2c_order_items';

    protected $fillable = [
        'b2c_order_id',
        'b2c_product_id',
        'product_name',
        'category_name',
        'quantity',
        'unit_price',
        'line_total',
        'print_side',
        'gsm',
        'gsm_price',
        'finish',
        'event_date',
        'custom_text',
        'file_path',
        'original_filename',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'gsm_price' => 'decimal:2',
            'event_date' => 'date',
        ];
    }

    public function order()
    {
        return $this->belongsTo(B2COrder::class, 'b2c_order_id');
    }

    public function product()
    {
        return $this->belongsTo(B2CProduct::class, 'b2c_product_id');
    }
}
