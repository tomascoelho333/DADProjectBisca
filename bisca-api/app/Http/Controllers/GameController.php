<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GameController extends Controller
{
    const MAX_PLAYERS = 2;

    // Creates a new game
    public function store(Request $request)
    {
        $user = Auth::user();

        // Cria o jogo na BD
        $game = Game::create([
            'type' => $request->type,
            'status' => 'Pending', // Pending
            'player1_user_id' => $user->id, // Player1 is always the lobby creator
        ]);

        // Adds the owner to the game
        $game->users()->attach($user->id);

        return response()->json($game, 201);
    }

    // List games that need players
    public function index()
    {
        // Returns games that are pending
        return Game::where('status', 'Pending')->with('users')->get();
    }

    // Function needed to join a game
    public function join(Game $game)
    {
        $user = Auth::user();

        if ($game->status !== 'Pending') {
            return response()->json(['message' => 'Match not available to join.'], 400);
        }
        if ($game->users()->count() >= self::MAX_PLAYERS) {
            return response()->json(['message' => 'The match is full.'], 400);
        }
        if ($game->users()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are already playing this match.'], 400);
        }

        // If there are no errors, join the game
        $game->update([
            'player2_user_id' => $user->id,
            'status' => 'Playing', // The game is set to Playing
        ]);

        // Sets the game status to active, and adds the current user to the player2

        return response()->json([
            'message' => 'You have joined the game!',
            'game' => $game]);
    }
}
