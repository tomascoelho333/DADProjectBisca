<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinPurchase extends Model
{
    protected $table = 'coin_purchases';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'coin_transaction_id',
        'purchase_datetime',
        'euros',
        'payment_type',
        'payment_reference',
        'custom',
    ];

    protected $casts = [
        'purchase_datetime' => 'datetime',
        'custom' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaction()
    {
        return $this->belongsTo(CoinTransaction::class, 'coin_transaction_id');
    }
}
