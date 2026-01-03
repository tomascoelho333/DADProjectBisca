<?php

namespace App\Http\Controllers;

use App\Models\MatchModel;
use App\Models\Game;
use App\Models\User;
use App\Models\CoinTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MatchController extends Controller
{
    /**
     * Get all available matches for joining
     */
    public function index()
    {
        $matches = MatchModel::where('status', 'Pending')
            ->with(['player1'])
            ->get();

        return response()->json($matches);
    }

    /**
     * Create a new match
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:3,9',
            'stake' => 'required|integer|min:1'
        ]);

        $user = Auth::user();
        
        // Admins cannot play games or participate in matches per requirement
        if ($user && $user->type === 'A') {
            return response()->json([
                'message' => 'Administrators cannot play games or participate in matches.'
            ], 403);
        }
        
        $stake = $request->stake;

        // Check if user has enough coins for the match stake
        if ($user->coins_balance < $stake) {
            return response()->json(['message' => 'Insufficient coins'], 400);
        }

        DB::beginTransaction();
        try {
            $match = new MatchModel();
            $match->type = $request->type;
            $match->player1_user_id = $user->id;
            $match->stake = $stake;
            $match->status = 'Pending';
            $match->save();

            DB::commit();
            return response()->json($match->load('player1'), 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Failed to create match'], 500);
        }
    }

    /**
     * Join an existing match
     */
    public function join($matchId)
    {
        $user = Auth::user();
        
        // Admins cannot play games or participate in matches per requirement
        if ($user && $user->type === 'A') {
            return response()->json([
                'message' => 'Administrators cannot play games or participate in matches.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $match = MatchModel::findOrFail($matchId);

            if ($match->status !== 'Pending') {
                return response()->json(['message' => 'Match is not available'], 400);
            }

            if ($match->player2_user_id !== null) {
                return response()->json(['message' => 'Match is full'], 400);
            }

            if ($match->player1_user_id === $user->id) {
                return response()->json(['message' => 'Cannot join your own match'], 400);
            }

            // Check if user has enough coins
            if ($user->coins_balance < $match->stake) {
                return response()->json(['message' => 'Insufficient coins'], 400);
            }

            $match->player2_user_id = $user->id;
            $match->status = 'Playing';
            $match->began_at = now();

            // Deduct stakes from both players
            $player1 = User::find($match->player1_user_id);
            $this->deductMatchStake($player1, $match->stake, $match->id);
            $this->deductMatchStake($user, $match->stake, $match->id);

            $match->save();

            // Start the first game of the match
            $this->startMatchGame($match);

            DB::commit();
            return response()->json($match->load(['player1', 'player2']));

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Failed to join match'], 500);
        }
    }

    /**
     * Get match details
     */
    public function show($matchId)
    {
        $user = Auth::user();
        $match = MatchModel::with(['player1', 'player2', 'winner', 'games' => function($query) {
            $query->orderBy('created_at');
        }])->findOrFail($matchId);

        // Check if user is part of this match
        if ($match->player1_user_id !== $user->id && $match->player2_user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($match);
    }

    /**
     * Get user's match history
     */
    public function history()
    {
        $user = Auth::user();

        $matches = MatchModel::where(function($query) use ($user) {
            $query->where('player1_user_id', $user->id)
                  ->orWhere('player2_user_id', $user->id);
        })
        ->where('status', 'Ended')
        ->with(['player1', 'player2', 'winner'])
        ->orderBy('ended_at', 'desc')
        ->paginate(10);

        return response()->json($matches);
    }

    /**
     * Resign from a match
     */
    public function resign($matchId)
    {
        $user = Auth::user();

        DB::beginTransaction();
        try {
            $match = MatchModel::findOrFail($matchId);

            if ($match->status !== 'Playing') {
                return response()->json(['message' => 'Match is not active'], 400);
            }

            if ($match->player1_user_id !== $user->id && $match->player2_user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Determine winner (opponent)
            $opponentId = $match->player1_user_id === $user->id ?
                         $match->player2_user_id : $match->player1_user_id;

            // End current game if in progress
            $currentGame = Game::where('match_id', $match->id)
                              ->where('status', 'Playing')
                              ->first();

            if ($currentGame) {
                $currentGame->status = 'Ended';
                $currentGame->winner_user_id = $opponentId;
                $currentGame->loser_user_id = $user->id;
                $currentGame->ended_at = now();
                $currentGame->total_time = now()->diffInSeconds($currentGame->began_at);
                $currentGame->save();
            }

            // End match
            $match->status = 'Ended';
            $match->winner_user_id = $opponentId;
            $match->loser_user_id = $user->id;
            $match->ended_at = now();
            $match->total_time = now()->diffInSeconds($match->began_at);

            // Calculate final scores
            $this->calculateMatchResults($match);

            // Award match payout to winner
            $payout = $match->stake * 2;
            $winner = User::find($opponentId);
            $this->awardMatchPayout($winner, $payout, $match->id);

            $match->save();

            DB::commit();
            return response()->json($match->load(['player1', 'player2', 'winner']));

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Failed to resign from match'], 500);
        }
    }

    /**
     * Start a new game within a match
     */
    private function startMatchGame($match)
    {
        $game = new Game();
        $game->type = $match->type;
        $game->player1_user_id = $match->player1_user_id;
        $game->player2_user_id = $match->player2_user_id;
        $game->match_id = $match->id;
        $game->status = 'Playing';
        $game->began_at = now();

        // Initialize game state
        $gameState = $this->initializeGameState($match->type, $match->player1_user_id, $match->player2_user_id);
        $game->custom = json_encode($gameState);

        $game->save();

        return $game;
    }

    /**
     * Check if match should continue or end after a game
     */
    public function checkMatchProgress($matchId, $gameResult)
    {
        $match = MatchModel::with('games')->findOrFail($matchId);

        if ($match->status !== 'Playing') {
            return;
        }

        // Count wins (marks) for each player
        $player1Marks = Game::where('match_id', $match->id)
            ->where('winner_user_id', $match->player1_user_id)
            ->where('status', 'Ended')
            ->count();

        $player2Marks = Game::where('match_id', $match->id)
            ->where('winner_user_id', $match->player2_user_id)
            ->where('status', 'Ended')
            ->count();

        // Check if anyone has reached 4 marks (wins the match)
        if ($player1Marks >= 4) {
            $this->endMatch($match, $match->player1_user_id, $player1Marks, $player2Marks);
        } elseif ($player2Marks >= 4) {
            $this->endMatch($match, $match->player2_user_id, $player1Marks, $player2Marks);
        } else {
            // Start next game
            $this->startMatchGame($match);
        }
    }

    /**
     * End the match
     */
    private function endMatch($match, $winnerId, $player1Marks, $player2Marks)
    {
        DB::beginTransaction();
        try {
            $match->status = 'Ended';
            $match->winner_user_id = $winnerId;
            $match->loser_user_id = $winnerId === $match->player1_user_id ?
                                   $match->player2_user_id : $match->player1_user_id;
            $match->ended_at = now();
            $match->total_time = now()->diffInSeconds($match->began_at);
            $match->player1_marks = $player1Marks;
            $match->player2_marks = $player2Marks;

            // Calculate total points from all games
            $this->calculateMatchResults($match);

            // Award match payout to winner
            $payout = $match->stake * 2;
            $winner = User::find($winnerId);
            $this->awardMatchPayout($winner, $payout, $match->id);

            $match->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Calculate final match results
     */
    private function calculateMatchResults($match)
    {
        $games = Game::where('match_id', $match->id)->get();

        $player1TotalPoints = $games->where('player1_user_id', $match->player1_user_id)
                                   ->sum('player1_points') +
                             $games->where('player2_user_id', $match->player1_user_id)
                                   ->sum('player2_points');

        $player2TotalPoints = $games->where('player1_user_id', $match->player2_user_id)
                                   ->sum('player1_points') +
                             $games->where('player2_user_id', $match->player2_user_id)
                                   ->sum('player2_points');

        $match->player1_points = $player1TotalPoints;
        $match->player2_points = $player2TotalPoints;
    }

    /**
     * Initialize game state for match games
     */
    private function initializeGameState($type, $player1Id, $player2Id)
    {
        $deck = $this->createBiscaDeck();
        $handSize = $type == '3' ? 3 : 9;

        // Shuffle deck
        shuffle($deck);

        // Deal cards
        $player1Hand = array_splice($deck, 0, $handSize);
        $player2Hand = array_splice($deck, 0, $handSize);

        // Trump card (next card from deck)
        $trumpCard = array_shift($deck);
        $trumpSuit = $trumpCard['suit'];

        return [
            'type' => $type,
            'player1_id' => $player1Id,
            'player2_id' => $player2Id,
            'deck' => $deck,
            'trump_card' => $trumpCard,
            'trump_suit' => $trumpSuit,
            'player1_hand' => $player1Hand,
            'player2_hand' => $player2Hand,
            'player1_tricks' => [],
            'player2_tricks' => [],
            'player1_points' => 0,
            'player2_points' => 0,
            'current_trick' => [],
            'current_player' => $player1Id,
            'trick_leader' => $player1Id,
            'status' => 'playing',
            'is_bot_game' => false,
            'move_timer' => null,
            'last_move_at' => now()->toISOString()
        ];
    }

    /**
     * Create a standard Bisca deck (40 cards)
     */
    private function createBiscaDeck()
    {
        $suits = ['copas', 'espadas', 'ouros', 'paus']; // cups, swords, coins, clubs
        $ranks = [
            ['id_value' => 1, 'value' => 14, 'points' => 11, 'name' => 'As'],  // Ace is highest in Bisca
            ['id_value' => 2, 'value' => 2, 'points' => 0, 'name' => '2'],
            ['id_value' => 3, 'value' => 3, 'points' => 0, 'name' => '3'],
            ['id_value' => 4, 'value' => 4, 'points' => 0, 'name' => '4'],
            ['id_value' => 5, 'value' => 5, 'points' => 0, 'name' => '5'],
            ['id_value' => 6, 'value' => 6, 'points' => 0, 'name' => '6'],
            ['id_value' => 7, 'value' => 13, 'points' => 10, 'name' => '7'],  // 7 is second highest in Bisca
            ['id_value' => 11, 'value' => 8, 'points' => 2, 'name' => 'Valete'],
            ['id_value' => 12, 'value' => 9, 'points' => 3, 'name' => 'Dama'],
            ['id_value' => 13, 'value' => 10, 'points' => 4, 'name' => 'Rei']
        ];

        $deck = [];
        foreach ($suits as $suit) {
            foreach ($ranks as $rank) {
                $deck[] = [
                    'id' => $suit . '_' . $rank['id_value'], // Use original value for image file names
                    'suit' => $suit,
                    'value' => $rank['value'], // Use corrected value for comparison
                    'points' => $rank['points'],
                    'name' => $rank['name'],
                    'is_trump' => false // Will be set when trump is determined
                ];
            }
        }

        return $deck;
    }

    /**
     * Deduct match stake from player
     */
    private function deductMatchStake($user, $amount, $matchId)
    {
        $user->coins_balance -= $amount;
        $user->save();

        CoinTransaction::create([
            'transaction_datetime' => now(),
            'user_id' => $user->id,
            'match_id' => $matchId,
            'coin_transaction_type_id' => 4, // Match stake
            'coins' => -$amount
        ]);
    }

    /**
     * Award match payout to winner
     */
    private function awardMatchPayout($user, $amount, $matchId)
    {
        $user->coins_balance += $amount;
        $user->save();

        CoinTransaction::create([
            'transaction_datetime' => now(),
            'user_id' => $user->id,
            'match_id' => $matchId,
            'coin_transaction_type_id' => 6, // Match payout
            'coins' => $amount
        ]);
    }
}
