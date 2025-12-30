<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class LeaderboardController extends Controller
{
    /**
     * GLOBAL LEADERBOARD (Public)
     * Visible to all users (including anonymous).
     * Tie-breaker: Earlier achiever ranks higher (simulated by user ID/creation date).
     */
    public function global()
    {
        $seconds = 5 * 60; // alter later

        // Retrieve top 10 players for the global leaderboard every 300 seconds
        $leaderboard = Cache::remember('global_leaderboard', $seconds, function () {
            return DB::table('users')
            ->select('users.id', 'users.nickname')
            // Count total Game Wins
            ->selectRaw('(SELECT COUNT(*) FROM games WHERE winner_user_id = users.id) as total_game_wins')
            // Count total Match Wins
            ->selectRaw('(SELECT COUNT(*) FROM matches WHERE winner_user_id = users.id) as total_match_wins')
            ->selectRaw('(SELECT MAX(ended_at) FROM games WHERE winner_user_id = users.id) as last_win_date')

            ->where('users.type', 'P')

            // Ranking Logic
            ->orderByDesc('total_game_wins')
            ->orderByDesc('total_match_wins')
            ->orderBy('last_win_date')  // Tie-breaker: "Earlier achiever ranks higher"

            ->limit(10)
            ->get();
        });

        return response()->json($leaderboard);
    }

    /**
     * PERSONAL LEADERBOARD
     * Game wins, match wins, capotes, bandeiras.
     */
    public function personal()
    {
        $user = Auth::user();

        // Total Game Wins
        $gameWins = Game::where('winner_user_id', $user->id)->count();

        $totalGames = Game::where('player1_user_id', $user->id)
            ->orWhere('player2_user_id', $user->id)
            ->count();

        $totalMatches = DB::table('matches')
            ->where('player1_user_id', $user->id)
            ->orWhere('player2_user_id', $user->id)
            ->count();

        // Total Match Wins
        $matchWins = DB::table('matches')
            ->where('winner_user_id', $user->id)
            ->count();

        // Checks my position on the ranking
        $betterPlayersCount = DB::table('games')
            ->select('winner_user_id')
            ->selectRaw('COUNT(*) as total_wins')
            ->groupBy('winner_user_id')
            ->having('total_wins', '>', $gameWins)
            ->get()
            ->count();

        // Capotes (91-119 pts) & Bandeiras (120 pts)
        $stats = Game::where(function($q) use ($user) {
            $q->where('player1_user_id', $user->id)
                ->orWhere('player2_user_id', $user->id);
        })
            ->where('status', 'Ended')
            ->get()
            ->reduce(function ($carry, $game) use ($user) {
                // Gets user points
                $myPoints = ($game->player1_user_id == $user->id)
                    ? $game->player1_points
                    : $game->player2_points;

                if ($myPoints == 120) {
                    $carry['bandeiras']++;
                } elseif ($myPoints >= 91) {
                    $carry['capotes']++;
                }

                return $carry;
            }, ['capotes' => 0, 'bandeiras' => 0]);

        return response()->json([
            'total_games_played' => $totalGames,
            'total_matches_played' => $totalMatches,
            'rank' => $betterPlayersCount + 1,
            'nickname' => $user->nickname,
            'total_game_wins' => $gameWins,
            'total_match_wins' => $matchWins,
            'total_capotes' => $stats['capotes'],
            'total_bandeiras' => $stats['bandeiras'],
        ]);
    }
}
