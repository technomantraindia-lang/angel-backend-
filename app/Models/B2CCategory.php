<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2CCategory extends Model
{
    use HasFactory;

    protected $table = 'b2c_categories';

    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
        'image_path',
    ];

    protected $appends = ['image_url'];

    protected $hidden = ['image_path'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function products()
    {
        return $this->hasMany(B2CProduct::class, 'b2c_category_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path) : null;
    }
}
