<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchModel extends Model
{
    protected $fillable = [
        'type', 'player1_user_id', 'player2_user_id', 'winner_user_id',
        'loser_user_id', 'status', 'stake', 'began_at', 'ended_at',
        'total_time', 'player1_marks', 'player2_marks', 'player1_points',
        'player2_points', 'custom'
    ];

    protected $casts = [
        'began_at' => 'datetime',
        'ended_at' => 'datetime',
        'total_time' => 'decimal:2',
        'custom' => 'array'
    ];

    // Match always has player 1
    public function player1()
    {
        return $this->belongsTo(User::class, 'player1_user_id');
    }

    // Match always has player 2
    public function player2()
    {
        return $this->belongsTo(User::class, 'player2_user_id');
    }

    // Match may have a winner
    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    // Match may have a loser
    public function loser()
    {
        return $this->belongsTo(User::class, 'loser_user_id');
    }

    // Match has many games
    public function games()
    {
        return $this->hasMany(Game::class);
    }

    // Match may have coin transactions
    public function coinTransactions()
    {
        return $this->hasMany(CoinTransaction::class);
    }
}
