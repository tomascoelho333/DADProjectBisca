<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HistoryController extends Controller
{
    /**
     * LIST HISTORY
     * Player sees only own history; Admin sees all.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Game::with(['player1', 'player2', 'winner']);

        // If not an admin, enters here
        if ($user->type !== 'A') {
            $query->where(function($q) use ($user) {
                $q->where('player1_user_id', $user->id)
                    ->orWhere('player2_user_id', $user->id);
            });
        }

        // Mainly for Admins, but the ordinal players can search for the players they played against
        if ($request->has('player') && $request->player) {
            $searchTerm = $request->player;
            $query->where(function($q) use ($searchTerm) {
                $q->whereHas('player1', function($q2) use ($searchTerm) {
                    $q2->where('nickname', 'like', "%{$searchTerm}%");
                })
                    ->orWhereHas('player2', function($q2) use ($searchTerm) {
                        $q2->where('nickname', 'like', "%{$searchTerm}%");
                    });
            });
        }

        // Admins sees everything
        return response()->json(
            $query->orderByDesc('began_at')->paginate(10)
        );
    }

    /**
     * SHOW GAME DETAILS
     * Visible to administrator AND participating players only
     */
    public function show($id)
    {
        $user = Auth::user();
        $game = Game::with(['player1', 'player2', 'winner'])->findOrFail($id);

        $isParticipant = ($game->player1_user_id == $user->id || $game->player2_user_id == $user->id);
        $isAdmin = ($user->type === 'A');

        if (!$isParticipant && !$isAdmin) {
            return response()->json(['message' => 'Unauthorized access to this game record.'], 403);
        }

        return response()->json($game);
    }
}
