<?php

namespace App\Http\Controllers;

use App\Models\CoinTransaction;
use App\Models\Game;
use App\Models\User;
use App\Models\CoinPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Get all users with optional filtering
     * Admins have read-only access to all users (players and administrators)
     */
    public function getAllUsers(Request $request)
    {
        $query = User::query();

        // Filter by type: 'P' for players, 'A' for admins
        if ($request->has('type') && in_array($request->type, ['P', 'A'])) {
            $query->where('type', $request->type);
        }

        // Filter by blocked status
        if ($request->has('blocked')) {
            $query->where('blocked', (bool)$request->blocked);
        }

        // Search by name, email, or nickname
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%");
            });
        }

        // Include soft-deleted users (for admin view)
        if ($request->has('include_deleted') && $request->include_deleted) {
            $query->withTrashed();
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        return response()->json($users);
    }

    /**
     * Get a specific user details with their statistics
     */
    public function getUserDetails(User $user)
    {
        // Include soft-deleted users
        if ($user->trashed()) {
            $user = User::withTrashed()->find($user->id);
        }

        // Get user statistics
        $totalGamesPlayed = Game::where(function ($query) use ($user) {
            $query->where('player1_user_id', $user->id)
                ->orWhere('player2_user_id', $user->id);
        })->count();

        $totalWins = Game::where('winner_user_id', $user->id)->count();
        $winRate = $totalGamesPlayed > 0 ? round(($totalWins / $totalGamesPlayed) * 100, 1) : 0;

        $totalTransactions = CoinTransaction::where('user_id', $user->id)->count();
        $totalSpent = DB::table('coin_purchases')
            ->where('user_id', $user->id)
            ->sum('euros') ?? 0;

        return response()->json([
            'user' => $user,
            'statistics' => [
                'total_games_played' => $totalGamesPlayed,
                'total_wins' => $totalWins,
                'win_rate' => $winRate,
                'total_transactions' => $totalTransactions,
                'total_spent_euros' => $totalSpent,
                'created_at' => $user->created_at,
                'deleted_at' => $user->deleted_at,
            ]
        ]);
    }

    /**
     * Block or unblock a player
     * Admins can block or unblock players
     */
    public function toggleBlockUser(Request $request, User $user)
    {
        // Admins cannot be blocked
        if ($user->type === 'A') {
            return response()->json([
                'message' => 'Administrators cannot be blocked.'
            ], 403);
        }

        $user->blocked = !$user->blocked;
        $user->save();

        Cache::forget("user_profile_{$user->id}");

        return response()->json([
            'message' => $user->blocked ? 'Player blocked successfully.' : 'Player unblocked successfully.',
            'user' => $user
        ]);
    }

    /**
     * Create a new administrator account
     * Only existing administrators can create new admin accounts
     */
    public function createAdmin(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:3|confirmed',
            'nickname' => 'required|string|max:20|unique:users',
            'photo_avatar_filename' => 'nullable|image|max:2048',
        ]);

        $photoFilename = null;

        if ($request->hasFile('photo_avatar_filename')) {
            $path = $request->file('photo_avatar_filename')->store('photos_avatars', 'public');
            $photoFilename = basename($path);
        }

        // Create admin user with type 'A'
        $admin = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'nickname' => $validated['nickname'],
            'photo_avatar_filename' => $photoFilename,
            'blocked' => false,
            'coins_balance' => 0,  // Admins cannot hold coins
            'type' => 'A',  // Set as Administrator
        ]);

        $token = $admin->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Administrator account created successfully.',
            'admin' => $admin,
            'token' => $token,
        ], 201);
    }

    /**
     * Delete a user account
     * Admins can remove any account except their own
     * When removing a player with prior activity, soft-delete is applied
     */
    public function deleteUser(Request $request, User $user)
    {
        // Prevent self-deletion
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own administrator account.'
            ], 403);
        }

        // If player has activity, soft delete; otherwise hard delete
        $hasActivity = Game::where(function ($query) use ($user) {
            $query->where('player1_user_id', $user->id)
                ->orWhere('player2_user_id', $user->id)
                ->orWhere('winner_user_id', $user->id)
                ->orWhere('loser_user_id', $user->id);
        })->exists() || CoinTransaction::where('user_id', $user->id)->exists();

        if ($hasActivity) {
            // Soft delete - preserves data integrity
            $user->delete();
            $message = 'Player account soft-deleted (archived) due to prior activity.';
        } else {
            // Hard delete - no prior activity
            $user->forceDelete();
            // Also delete their transactions if any
            CoinTransaction::where('user_id', $user->id)->forceDelete();
            $message = 'Player account permanently deleted.';
        }

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => $message
        ]);
    }

    /**
     * Get all transactions (read-only access for admins)
     */
    public function getTransactions(Request $request)
    {
        $query = CoinTransaction::with('user');

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by transaction type
        if ($request->has('type_id')) {
            $query->where('coin_transaction_type_id', $request->type_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('transaction_datetime', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('transaction_datetime', '<=', $request->end_date);
        }

        // Pagination
        $perPage = $request->get('per_page', 50);
        $transactions = $query->orderByDesc('transaction_datetime')->paginate($perPage);

        return response()->json($transactions);
    }

    /**
     * Get all multiplayer games and matches (read-only access for admins)
     */
    public function getMatches(Request $request)
    {
        $query = DB::table('matches')
            ->select('matches.*', 'users.nickname as player1_nickname', 'users.nickname as player2_nickname')
            ->join('users', 'matches.player1_user_id', '=', 'users.id');

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('matches.status', $request->status);
        }

        // Filter by type
        if ($request->has('type') && $request->type !== '') {
            $query->where('matches.type', $request->type);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('matches.created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('matches.created_at', '<=', $request->end_date);
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $matches = $query->orderByDesc('matches.created_at')->paginate($perPage);

        return response()->json($matches);
    }

    /**
     * Get match details with all games
     */
    public function getMatchDetails($matchId)
    {
        $match = DB::table('matches')
            ->where('id', $matchId)
            ->first();

        if (!$match) {
            return response()->json(['message' => 'Match not found'], 404);
        }

        // Get all games in this match
        $games = Game::where('match_id', $matchId)->get();

        return response()->json([
            'match' => $match,
            'games' => $games
        ]);
    }

    /**
     * Get comprehensive platform statistics and summaries
     * Admins have access to advanced platform usage summaries and statistics
     */
    public function getPlatformStats()
    {
        // Cache key based on last database updates
        $lastGameUpdate = Game::max('updated_at');
        $lastUserUpdate = User::max('updated_at');
        $lastPurchaseUpdate = DB::table('coin_purchases')->max('purchase_datetime');
        $lastMatchUpdate = DB::table('matches')->max('updated_at');

        $cacheKey = 'admin_platform_stats_' . md5($lastGameUpdate . $lastUserUpdate . $lastPurchaseUpdate . $lastMatchUpdate);

        return Cache::rememberForever($cacheKey, function () {
            // User Statistics
            $totalUsers = User::count();
            $totalPlayers = User::where('type', 'P')->count();
            $totalAdmins = User::where('type', 'A')->count();
            $blockedUsers = User::where('blocked', true)->count();
            $deletedUsers = User::onlyTrashed()->count();

            // Game Statistics
            $totalGames = Game::count();
            $totalMatches = DB::table('matches')->count();
            $gamesPlaying = Game::where('status', 'Playing')->count();
            $gamesPending = Game::where('status', 'Pending')->count();
            $gamesEnded = Game::where('status', 'Ended')->count();
            $gamesInterrupted = Game::where('status', 'Interrupted')->count();
            $avgGameDuration = Game::whereNotNull('total_time')->avg('total_time');

            // Financial Statistics
            $totalCoinsInCirculation = User::where('type', 'P')->sum('coins_balance');
            $totalRevenue = DB::table('coin_purchases')->sum('euros') ?? 0;
            $totalPurchases = DB::table('coin_purchases')->count();
            $avgSpendPerPlayer = $totalPlayers > 0 ? round($totalRevenue / $totalPlayers, 2) : 0;

            // Games Distribution
            $gamesByType = Game::select('type', DB::raw('count(*) as total'))
                ->groupBy('type')
                ->get();

            $gamesByStatus = Game::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get();

            // Purchases Distribution
            $purchasesByType = DB::table('coin_purchases')
                ->select('payment_type', DB::raw('count(*) as total'))
                ->groupBy('payment_type')
                ->get();

            // Purchase trends by month
            $purchasesByMonth = DB::table('coin_purchases')
                ->select(DB::raw("strftime('%Y-%m', purchase_datetime) as month"), DB::raw('count(*) as count'), DB::raw('sum(euros) as revenue'))
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Recent activity
            $recentGames = Game::orderByDesc('created_at')->limit(10)->get();
            $recentTransactions = CoinTransaction::orderByDesc('transaction_datetime')->limit(10)->get();

            return response()->json([
                'user_statistics' => [
                    'total_users' => $totalUsers,
                    'total_players' => $totalPlayers,
                    'total_admins' => $totalAdmins,
                    'blocked_users' => $blockedUsers,
                    'deleted_users' => $deletedUsers,
                ],
                'game_statistics' => [
                    'total_games' => $totalGames,
                    'total_matches' => $totalMatches,
                    'games_playing' => $gamesPlaying,
                    'games_pending' => $gamesPending,
                    'games_ended' => $gamesEnded,
                    'games_interrupted' => $gamesInterrupted,
                    'avg_game_duration_seconds' => round($avgGameDuration ?? 0, 1),
                ],
                'financial_statistics' => [
                    'total_coins_circulation' => $totalCoinsInCirculation,
                    'total_revenue_euros' => $totalRevenue,
                    'total_purchases' => $totalPurchases,
                    'avg_spend_per_player_euros' => $avgSpendPerPlayer,
                ],
                'distribution' => [
                    'games_by_type' => $gamesByType,
                    'games_by_status' => $gamesByStatus,
                    'purchases_by_type' => $purchasesByType,
                    'purchases_by_month' => $purchasesByMonth,
                ],
                'recent_activity' => [
                    'recent_games' => $recentGames,
                    'recent_transactions' => $recentTransactions,
                ],
            ]);
        });
    }

    /**
     * Export comprehensive user audit log
     * For compliance and monitoring purposes
     */
    public function getUserAuditLog($userId)
    {
        $user = User::withTrashed()->find($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Get all transactions
        $transactions = CoinTransaction::where('user_id', $userId)->get();

        // Get all games (as player 1, player 2, or winner)
        $games = Game::where(function ($query) use ($userId) {
            $query->where('player1_user_id', $userId)
                ->orWhere('player2_user_id', $userId)
                ->orWhere('winner_user_id', $userId);
        })->get();

        // Get matches (as player 1 or player 2)
        $matches = DB::table('matches')->where(function ($query) use ($userId) {
            $query->where('player1_user_id', $userId)
                ->orWhere('player2_user_id', $userId);
        })->get();

        // Get all purchases
        $purchases = DB::table('coin_purchases')->where('user_id', $userId)->get();

        return response()->json([
            'user' => $user,
            'transactions' => $transactions,
            'games' => $games,
            'matches' => $matches,
            'purchases' => $purchases,
            'summary' => [
                'total_transactions' => count($transactions),
                'total_games_played' => count($games),
                'total_matches_played' => count($matches),
                'total_purchases' => count($purchases),
            ]
        ]);
    }
}
