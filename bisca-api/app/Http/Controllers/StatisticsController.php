<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    const MIN_GAMES_TO_COUNT = 3;

    /**
     * Public Statistics
     * Generic data. No financial info.
     */
    public function index()
    {
        return response()->json([
            'total_players' => User::where('type', 'P')->count(),
            'total_games' => Game::count(),
            'total_playing' => Game::where('status', 'Playing')->count(),
            'avg_game_duration' => round(Game::whereNotNull('total_time')->avg('total_time') / 60, 1),
        ]);
    }

    /**
     * Admin Statistics
     * Detailed business metrics.
     */
    public function adminStats()
    {
        /**
         * Stupid smart cache idea that I had so that the data would never be not updated
         * It essentially updates only when something has been updated on the database.
         * Prevents seconds of loading time on the webpage when the user presses F5
         */

        $lastGameUpdate = Game::max('updated_at');
        $lastUserUpdate = User::max('updated_at');
        $lastPurchaseUpdate = DB::table('coin_purchases')
            ->max('purchase_datetime');

        $cacheKey = 'admin_stats_' . md5($lastGameUpdate . $lastUserUpdate . $lastPurchaseUpdate);

        // this function can be remberforever because the cache key updates everytime something changes
        // would be stupid if the data was the same for minutes even if something changed
        return Cache::rememberForever($cacheKey, function () {

        // Games Distribution (Pie Chart)
        $gamesByType = Game::select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->get();

        // Games Status (Bar Chart)
        $gamesByStatus = Game::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        // Purchases by Type (Pie Chart)
        $purchasesByType = DB::table('coin_purchases')
            ->select('payment_type', DB::raw('count(*) as total'))
            ->groupBy('payment_type')
            ->get();

        // Total revenue in euros
        $totalRevenue = DB::table('coin_purchases')->sum('euros');

        // Total coins in circulations
        $totalCoins = User::sum('coins_balance');

        // Leaderboard top WR
        $topWinRate = DB::table('users')
            ->select('users.id', 'nickname', 'email')
            ->selectRaw('(SELECT COUNT(*) FROM games WHERE winner_user_id = users.id) as total_wins')
            ->selectRaw('(SELECT COUNT(*) FROM games WHERE player1_user_id = users.id OR player2_user_id = users.id) as total_played')
            ->where('type', 'P')
            ->groupBy('users.id', 'nickname', 'email')
            ->having('total_played', '>=', self::MIN_GAMES_TO_COUNT)
            ->orderByRaw('(total_wins * 1.0 / NULLIF(total_played, 0)) DESC')
            ->limit(5)
            ->get()
            ->map(function ($player) {
                $player->win_rate = $player->total_played > 0
                    ? round(($player->total_wins / $player->total_played) * 100, 1)
                    : 0;
                return $player;
            });

        // Leaderboard the richest players
        $richPlayers = User::where('type', 'P')
            ->orderByDesc('coins_balance')
            ->limit(5)
            ->get(['nickname', 'coins_balance']);

        // Purchases per month
        $purchasesByMonth = DB::table('coin_purchases')
            ->select(DB::raw("strftime('%Y-%m', purchase_datetime) as month"), DB::raw('sum(euros) as total'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Who spent more IRL money
        $topSpenders = DB::table('coin_purchases')
            ->join('users', 'coin_purchases.user_id', '=', 'users.id')
            ->select('users.nickname', 'users.email', DB::raw('sum(coin_purchases.euros) as total_spent'))
            ->groupBy('users.id', 'users.nickname', 'users.email')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get();

        return response()->json([
            'games_by_type' => $gamesByType,
            'games_by_status' => $gamesByStatus,
            'purchases_by_type' => $purchasesByType,
            'total_revenue' => $totalRevenue,
            'total_coins' => $totalCoins,
            'top_rich_players' => $richPlayers,
            'top_win_rate' => $topWinRate,
            'purchases_by_month' => $purchasesByMonth,
            'top_spenders' => $topSpenders,
        ]);
        });
    }
}
