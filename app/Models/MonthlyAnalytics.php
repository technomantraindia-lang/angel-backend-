<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyAnalytics extends Model
{
    protected $table = 'monthly_analytics';
    protected $fillable = ['year', 'month', 'total_orders', 'total_earnings', 'estimated_profit'];
    protected function casts(): array { return ['total_earnings' => 'decimal:2', 'estimated_profit' => 'decimal:2']; }
}
