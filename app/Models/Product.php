<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Product extends Model
{
    use HasFactory;

    protected $fillable = ['category','name','print_copy','amount','front_back_amount','is_active','is_b2b','is_b2c','sort_order'];

    protected $with = ['discountTiers'];
    protected $appends = ['pricing_tiers'];

    protected function casts(): array { 
        return [
            'amount'=>'decimal:2',
            'front_back_amount'=>'decimal:2',
            'is_active'=>'boolean',
            'is_b2b'=>'boolean',
            'is_b2c'=>'boolean',
            'print_copy'=>'integer',
            'sort_order'=>'integer'
        ]; 
    }

    public function items() { 
        return $this->hasMany(OrderItem::class); 
    }

    public function discountTiers() {
        return $this->hasMany(DiscountTier::class);
    }

    public function getPricingTiersAttribute() {
        return $this->discountTiers;
    }
}
