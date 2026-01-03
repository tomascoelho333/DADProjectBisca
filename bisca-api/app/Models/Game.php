<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    // Admins cannot participate in games
    // Games are only for players (type 'P')
    
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
}
