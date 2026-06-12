<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ExtraCharge extends Model
{
    protected $fillable = ['order_id','description','amount','deducted_from_wallet','added_by'];
    protected function casts(): array { return ['amount'=>'decimal:2','deducted_from_wallet'=>'boolean']; }
    public function order() { return $this->belongsTo(Order::class); }
}
