<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Order extends Model
{
    protected $fillable = ['order_number','dealer_id','assigned_staff_id','staff_status','receipt_shared','status','deadline_at','subtotal','extra_total','grand_total','customer_note','pickup_note','called_at','completed_at','picked_up_at'];
    protected function casts(): array { return ['deadline_at'=>'datetime','called_at'=>'datetime','completed_at'=>'datetime','picked_up_at'=>'datetime','subtotal'=>'decimal:2','extra_total'=>'decimal:2','grand_total'=>'decimal:2','receipt_shared'=>'boolean']; }
    public function dealer() { return $this->belongsTo(User::class, 'dealer_id'); }
    public function assignedStaff() { return $this->belongsTo(User::class, 'assigned_staff_id'); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function extraCharges() { return $this->hasMany(ExtraCharge::class); }
}
