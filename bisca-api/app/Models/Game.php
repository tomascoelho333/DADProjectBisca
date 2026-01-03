<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
<<<<<<< HEAD
    // Admins cannot participate in games
    // Games are only for players (type 'P')
    
    public $timestamps = false;

    protected $fillable = [
        'type', 'player1_user_id', 'player2_user_id', 'is_draw',
        'winner_user_id', 'loser_user_id', 'match_id', 'status',
        'began_at', 'ended_at', 'total_time', 'player1_points',
        'player2_points', 'custom'
    ];

    protected $casts = [
        'began_at' => 'datetime',
        'ended_at' => 'datetime',
        'total_time' => 'decimal:2',
        'is_draw' => 'boolean',
        'custom' => 'array'
    ];

    //Game always has 2 players (bot or not)
    public function player1()
    {
        return $this->belongsTo(User::class, 'player1_user_id');
    }

    //Game always has 2 players (bot or not)
    public function player2()
    {
        return $this->belongsTo(User::class, 'player2_user_id');
    }

    //Game always has a winner
    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    //Game may have a loser
    public function loser()
    {
        return $this->belongsTo(User::class, 'loser_user_id');
    }

    //Game may belong to a match
    public function match()
    {
        return $this->belongsTo('App\Models\MatchModel', 'match_id');
    }

    //Game may have coin transactions
    public function coinTransactions()
    {
        return $this->hasMany(CoinTransaction::class);
    }
}
