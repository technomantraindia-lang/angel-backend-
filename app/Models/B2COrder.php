<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2COrder extends Model
{
    use HasFactory;

    protected $table = 'b2c_orders';

    protected $fillable = [
        'order_number',
        'customer_id',
        'assigned_staff_id',
        'staff_status',
        'deadline_at',
        'contact_name',
        'contact_email',
        'contact_phone',
        'status',
        'subtotal',
        'grand_total',
        'customer_note',
        'pickup_note',
        'completed_at',
        'picked_up_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'deadline_at' => 'datetime',
            'completed_at' => 'datetime',
            'picked_up_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function items()
    {
        return $this->hasMany(B2COrderItem::class, 'b2c_order_id');
    }

    public function assignedStaff()
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }
}
