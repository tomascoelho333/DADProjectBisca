<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{

    // We have no created_at nor updated_at
    public $timestamps = false;

    protected $fillable = [
        'type',
        'player1_user_id',
        'player2_user_id',
        'winner_user_id',
        'loser_user_id',
        'is_draw',
        'match_id',
        'status',
        'began_at',
        'ended_at',
        'total_time',
        'player1_points',
        'player2_points',
        'custom',
    ];

    protected $casts = [
        'began_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_draw' => 'boolean',
        'custom' => 'array', // JSON as a PHP array
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

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

}
