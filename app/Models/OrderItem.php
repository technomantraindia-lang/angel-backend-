<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OrderItem extends Model
{
    protected $fillable = ['order_id','product_id','product_name','category','print_copy','print_side','packs','unit_price','line_total','file_path','original_filename'];
    protected function casts(): array { return ['print_copy'=>'integer','packs'=>'integer','unit_price'=>'decimal:2','line_total'=>'decimal:2']; }
    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
