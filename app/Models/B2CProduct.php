<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class B2CProduct extends Model
{
    use HasFactory;

    protected $table = 'b2c_products';

    protected $fillable = [
        'b2c_category_id',
        'name',
        'short_description',
        'description',
        'print_copy',
        'quantity_step',
        'amount',
        'front_back_amount',
        'print_side_mode',
        'gsm_options',
        'pricing_tiers',
        'warning',
        'allow_design_serial',
        'sample_pdf_path',
        'is_active',
        'sort_order',
    ];

    protected $with = ['categoryRef', 'images'];

    protected $appends = ['category', 'sample_pdf_url', 'image_urls', 'primary_image_url'];

    protected $hidden = ['sample_pdf_path'];

    protected function casts(): array
    {
        return [
            'print_copy' => 'integer',
            'quantity_step' => 'integer',
            'amount' => 'decimal:2',
            'front_back_amount' => 'decimal:2',
            'gsm_options' => 'array',
            'pricing_tiers' => 'array',
            'warning' => 'string',
            'allow_design_serial' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function categoryRef()
    {
        return $this->belongsTo(B2CCategory::class, 'b2c_category_id');
    }

    public function images()
    {
        return $this->hasMany(B2CProductImage::class, 'b2c_product_id')->orderBy('sort_order')->orderBy('id');
    }

    public function orderItems()
    {
        return $this->hasMany(B2COrderItem::class, 'b2c_product_id');
    }

    public function getCategoryAttribute(): ?string
    {
        return $this->categoryRef?->name;
    }

    public function getSamplePdfUrlAttribute(): ?string
    {
        return $this->sample_pdf_path ? Storage::disk('public')->url($this->sample_pdf_path) : null;
    }

    public function getImageUrlsAttribute(): array
    {
        return $this->images->pluck('file_url')->filter()->values()->all();
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        return $this->images->first()?->file_url;
    }
}
