<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_b2b', 'is_b2c'];

    protected function casts(): array {
        return [
            'is_b2b' => 'boolean',
            'is_b2c' => 'boolean'
        ];
    }
}
