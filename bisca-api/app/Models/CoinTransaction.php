<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinTransaction extends Model
{
    //Makes sure laravel gets the right table as it has an underscore
    protected $table = 'coin_transactions';

    //Makes so laravel doesn't try to fill created_at and updated_at (none of those exist in our table)
    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'transaction_datetime',
        'coins',
        'coin_transaction_type_id',
        'custom',
        //Game related:
        'game_id',
        'match_id',

    ];

    //Makes sure custom is sent as an array of data
    protected $casts = [
        'custom' => 'array',
        'transaction_datetime' => 'datetime',
    ];

    //Always has an user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    //And could be related to a game
    public function game()
    {
        return $this->belongsTo(Game::class);
    }

}
