<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class B2CProductImage extends Model
{
    use HasFactory;

    protected $table = 'b2c_product_images';

    protected $fillable = [
        'b2c_product_id',
        'file_path',
        'sort_order',
    ];

    protected $appends = ['file_url'];

    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function product()
    {
        return $this->belongsTo(B2CProduct::class, 'b2c_product_id');
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }
}
