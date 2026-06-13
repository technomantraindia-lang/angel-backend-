<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $fillable = ['name','company_name','email','phone','address','pincode','gst_number','password','plain_password','role','approval_status','wallet_balance'];
    protected $hidden = ['password','remember_token'];
    protected function casts(): array { return ['password' => 'hashed', 'wallet_balance' => 'decimal:2', 'email_verified_at' => 'datetime']; }
    public function orders() { return $this->hasMany(Order::class, 'dealer_id'); }
    public function walletTransactions() { return $this->hasMany(WalletTransaction::class); }
    public function notifications() { return $this->hasMany(PortalNotification::class); }
}
