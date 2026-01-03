<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use App\Models\CoinTransaction;
use App\Services\SinglePlayerGameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GameController extends Controller
{
    /**
     * Get all available games for joining
     */
    public function index()
    {
        // Find the bot player ID
        $botPlayer = User::where('email', 'bot@system.local')->first();
        $botPlayerId = $botPlayer ? $botPlayer->id : null;

        $games = Game::where('status', 'Pending')
            ->where(function($query) use ($botPlayerId) {
                // Games where player2 is the bot (meaning waiting for real player)
                if ($botPlayerId) {
                    $query->where('player2_user_id', $botPlayerId);
                } else {
                    // Fallback: look for games with waiting_for_player2 flag
                    $query->whereRaw("JSON_EXTRACT(custom, '$.waiting_for_player2') = true");
                }
            })
            ->with(['player1'])
            ->get();

        // Transform the response to hide the bot player2
        $games->transform(function($game) use ($botPlayerId) {
            if ($game->player2_user_id === $botPlayerId) {
                $game->player2_user_id = null;
                $game->unsetRelation('player2');
            }
            return $game;
        });

        return response()->json($games);
    }

    /**
     * Create a new standalone game
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:3,9',
            'is_multiplayer' => 'boolean',
            'stake' => 'integer|min:1'
        ]);

        $user = Auth::user();
        
        // Admins cannot play games per requirement
        if ($user && $user->type === 'A') {
            return response()->json([
                'message' => 'Administrators cannot play games.'
            ], 403);
        }
        
        $stake = $request->stake ?? 1;

        \Log::info('GameController@store called', [
            'user_id' => $user ? $user->id : 'null',
            'is_multiplayer' => $request->is_multiplayer,
            'type' => $request->type
        ]);

        try {
            if (!$request->is_multiplayer) {
                // Single player against bot - handled in memory, not saved to database
                \Log::info('Creating single player game', ['user_id' => $user->id]);
                $result = SinglePlayerGameService::createGame($request->type, $user->id);

                // Check if game creation failed (e.g., insufficient coins)
                if (isset($result['success']) && !$result['success']) {
                    return response()->json([
                        'message' => $result['message']
                    ], 400);
                }

                \Log::info('Single player game created successfully', ['game_id' => $result['id'] ?? 'unknown']);
                return response()->json($result, 201);
            }
        } catch (\Exception $e) {
            \Log::error('Error creating single player game', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to create single player game'], 500);
        }        // Multiplayer game - save to database
        \Log::info('Creating multiplayer game', ['user_id' => $user->id, 'stake' => $stake]);

        // Check if user has enough coins for standalone multiplayer game (2 coins per player)
        $requiredCoins = 2;
        if ($user->coins_balance < $requiredCoins) {
            \Log::warning('Insufficient coins for multiplayer game', [
                'user_id' => $user->id,
                'required_coins' => $requiredCoins,
                'current_balance' => $user->coins_balance
            ]);
            return response()->json(['message' => 'Insufficient coins. Multiplayer games cost 2 coins per player.'], 400);
        }

        DB::beginTransaction();
        try {
            // Find the bot player to use as placeholder for player2
            \Log::info('Looking for bot player');
            $botPlayer = User::where('email', 'bot@system.local')->first();
            if (!$botPlayer) {
                \Log::error('Bot player not found');
                return response()->json(['message' => 'System error: Bot player not found'], 500);
            }
            \Log::info('Bot player found', ['bot_id' => $botPlayer->id]);

            $game = new Game();
            $game->type = $request->type;
            $game->player1_user_id = $user->id;
            $game->player2_user_id = $botPlayer->id; // Use bot as placeholder
            $game->status = 'Pending';            // Store game fee and required coins for when player2 joins
            // Also store that this is waiting for a real player2
            $game->custom = json_encode([
                'game_fee' => $requiredCoins,
                'waiting_for_player2' => true,
                'placeholder_player2_id' => $botPlayer->id
            ]);

            \Log::info('Attempting to save multiplayer game', [
                'game_type' => $game->type,
                'player1_id' => $game->player1_user_id,
                'bot_placeholder_id' => $game->player2_user_id,
                'game_fee' => $requiredCoins
            ]);

            $game->save();

            // Deduct game fee from player1 when creating the game
            $this->deductGameFee($user, $requiredCoins, $game->id);

            \Log::info('Multiplayer game saved successfully', [
                'game_id' => $game->id,
                'player1_coins_deducted' => $requiredCoins
            ]);

            DB::commit();
            \Log::info('Transaction committed successfully');
            return response()->json($game->load('player1'), 201);

        } catch (\Exception $e) {
            \Log::error('Error creating multiplayer game', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            DB::rollback();
            return response()->json(['message' => 'Failed to create game'], 500);
        }
    }

    /**
     * Join an existing multiplayer game
     */
    public function join($gameId)
    {
        $user = Auth::user();
        
        // Admins cannot play games per requirement
        if ($user && $user->type === 'A') {
            return response()->json([
                'message' => 'Administrators cannot play games.'
            ], 403);
        }

        \Log::info('User attempting to join game', [
            'game_id' => $gameId,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_coins' => $user->coins_balance
        ]);

        DB::beginTransaction();
        try {
            $game = Game::findOrFail($gameId);

            \Log::info('Game found', [
                'game_id' => $gameId,
                'status' => $game->status,
                'player1' => $game->player1_user_id,
                'player2' => $game->player2_user_id,
                'custom' => $game->custom
            ]);

            if ($game->status !== 'Pending') {
                \Log::warning('Game not available', ['status' => $game->status]);
                return response()->json(['message' => 'Game is not available'], 400);
            }

            // Check if game is waiting for player2 (has bot placeholder)
            $gameState = json_decode($game->custom, true) ?? [];
            $botPlayer = User::where('email', 'bot@system.local')->first();
            $isWaitingForPlayer2 = ($gameState['waiting_for_player2'] ?? false) ||
                                   ($botPlayer && $game->player2_user_id === $botPlayer->id);

            \Log::info('Checking if game is waiting for player2', [
                'waiting_for_player2' => $gameState['waiting_for_player2'] ?? false,
                'bot_player_id' => $botPlayer ? $botPlayer->id : null,
                'current_player2' => $game->player2_user_id,
                'is_waiting' => $isWaitingForPlayer2
            ]);

            if (!$isWaitingForPlayer2) {
                \Log::warning('Game is full');
                return response()->json(['message' => 'Game is full'], 400);
            }

            if ($game->player1_user_id === $user->id) {
                \Log::warning('User trying to join own game');
                return response()->json(['message' => 'Cannot join your own game'], 400);
            }

            // Get game fee from custom data
            $gameFee = $gameState['game_fee'] ?? 2; // Default to 2 coins for standalone multiplayer games

            \Log::info('Checking coins', [
                'required' => $gameFee,
                'user_has' => $user->coins_balance
            ]);

            // Check if user has enough coins for the game fee
            if ($user->coins_balance < $gameFee) {
                \Log::warning('Insufficient coins', [
                    'required' => $gameFee,
                    'user_has' => $user->coins_balance
                ]);
                return response()->json([
                    'message' => "Insufficient coins. You need {$gameFee} coins to join this multiplayer game."
                ], 400);
            }

            $game->player2_user_id = $user->id;
            $game->status = 'Playing';
            $game->began_at = now();

            // Initialize game state
            $gameState = $this->initializeGameState($game->type, $game->player1_user_id, $user->id);
            $gameState['game_fee'] = $gameFee;
            // Remove the waiting flag
            unset($gameState['waiting_for_player2']);
            unset($gameState['placeholder_player2_id']);
            $game->custom = json_encode($gameState);

            // Deduct game fee from player2 (player1 already paid when creating the game)
            $this->deductGameFee($user, $gameFee, $game->id);

            $game->save();

            \Log::info('Successfully joined game', ['game_id' => $gameId]);

            DB::commit();
            return response()->json($game->load(['player1', 'player2']));

        } catch (\Exception $e) {
            \Log::error('Error joining game', [
                'game_id' => $gameId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            DB::rollback();
            return response()->json(['message' => 'Failed to join game', 'error' => $e->getMessage()], 500);
        }
    }    /**
     * Get game details
     */
    public function show($gameId)
    {
        $user = Auth::user();

        // First check if it's a single-player game (in memory)
        $singlePlayerGame = SinglePlayerGameService::getGame($gameId);
        if ($singlePlayerGame) {
            // Check if user is authorized to view this game
            if ($singlePlayerGame['player1_id'] !== $user->id && !$singlePlayerGame['is_anonymous']) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            return response()->json($singlePlayerGame);
        }

        // Otherwise, it's a multiplayer game in database
        $game = Game::with(['player1', 'player2', 'winner'])->findOrFail($gameId);

        // Clear any potential query cache and refresh from database to ensure we have the latest data
        DB::connection()->flushQueryLog();
        $game->refresh();

        \Log::info('SHOW: Game fetched for show', [
            'game_id' => $game->id,
            'requested_id' => $gameId,
            'status' => $game->status,
            'winner_id' => $game->winner_user_id,
            'updated_at' => $game->updated_at,
            'custom_length' => strlen($game->custom ?? '')
        ]);

        // Check if user is part of this game
        $botPlayer = User::where('email', 'bot@system.local')->first();
        $gameState = json_decode($game->custom, true) ?? [];
        $isWaitingForPlayer2 = ($gameState['waiting_for_player2'] ?? false) ||
                               ($botPlayer && $game->player2_user_id === $botPlayer->id);

        // Allow access if:
        // 1. User is player1
        // 2. User is player2 (and it's not the bot placeholder)
        // 3. User is player1 and game is waiting for player2
        $hasAccess = ($game->player1_user_id === $user->id) ||
                     ($game->player2_user_id === $user->id && !$isWaitingForPlayer2);

        if (!$hasAccess) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // If game is waiting for player2, hide the bot placeholder
        if ($isWaitingForPlayer2 && $botPlayer && $game->player2_user_id === $botPlayer->id) {
            $game->player2_user_id = null;
            $game->unsetRelation('player2');
        }

        \Log::info('Returning game state', [
            'game_id' => $game->id,
            'status' => $game->status,
            'is_waiting_for_player2' => $isWaitingForPlayer2,
            'has_player2' => $game->player2_user_id !== null,
            'custom_length' => strlen($game->custom ?? ''),
            'custom_preview' => substr($game->custom ?? '', 0, 200)
        ]);

        // For multiplayer games that are playing or ended, merge custom game state with game model
        if (($game->status === 'Playing' || $game->status === 'Ended') && !empty($game->custom)) {
            $customState = json_decode($game->custom, true) ?? [];

            // Auto-resolve stuck tricks (if a trick has been complete for more than 10 seconds)
            if (isset($customState['current_trick']) && count($customState['current_trick']) === 2) {
                $lastMoveTime = isset($customState['last_move_at']) ? strtotime($customState['last_move_at']) : 0;
                $currentTime = time();

                \Log::info('Checking for stuck trick', [
                    'game_id' => $game->id,
                    'trick_length' => count($customState['current_trick']),
                    'last_move_at' => $customState['last_move_at'] ?? 'not_set',
                    'last_move_time' => $lastMoveTime,
                    'current_time' => $currentTime,
                    'time_diff' => $currentTime - $lastMoveTime,
                    'should_auto_resolve' => ($currentTime - $lastMoveTime) > 3
                ]);

                // Auto-resolve if stuck for more than 10 seconds (backup safety net)
                if ($currentTime - $lastMoveTime > 10) {
                    \Log::info('Auto-resolving stuck trick (backup safety)', [
                        'game_id' => $game->id,
                        'time_stuck' => $currentTime - $lastMoveTime,
                        'current_trick' => $customState['current_trick'],
                        'current_player_before' => $customState['current_player']
                    ]);

                    // Resolve the trick automatically
                    $this->resolveTrick($customState);

                    // Save the resolved state
                    $game->custom = json_encode($customState);
                    $game->save();

                    \Log::info('Stuck trick auto-resolved (backup)', [
                        'new_trick_length' => count($customState['current_trick']),
                        'new_current_player' => $customState['current_player'],
                        'new_p1_points' => $customState['player1_points'],
                        'new_p2_points' => $customState['player2_points']
                    ]);
                } else {
                    \Log::info('Trick complete but not stuck yet, skipping auto-resolve', [
                        'game_id' => $game->id,
                        'time_since_last_move' => $currentTime - $lastMoveTime,
                        'current_player' => $customState['current_player']
                    ]);
                }
            }

            \Log::info('Merging custom state', [
                'current_trick_length' => count($customState['current_trick'] ?? []),
                'player1_points' => $customState['player1_points'] ?? 'not_set',
                'player2_points' => $customState['player2_points'] ?? 'not_set'
            ]);

            // Create a comprehensive response that includes both model and custom state
            $response = $game->toArray();

            // Merge custom game state fields for frontend compatibility
            $response = array_merge($response, $customState);

            // Ensure required fields are present
            if (!isset($response['player1_points'])) {
                $response['player1_points'] = $customState['player1_points'] ?? 0;
            }
            if (!isset($response['player2_points'])) {
                $response['player2_points'] = $customState['player2_points'] ?? 0;
            }

            return response()->json($response);
        }

        return response()->json($game);
    }

    /**
     * Make a move in the game
     */
    public function makeMove($gameId, Request $request)
    {
        $request->validate([
            'card_id' => 'nullable|string',
            'action' => 'required|in:play_card,resign,bot_move,resolve_trick'
        ]);

        $user = Auth::user();

        // First check if it's a single-player game (in memory)
        $singlePlayerGame = SinglePlayerGameService::getGame($gameId);
        if ($singlePlayerGame) {
            if ($request->action === 'resign') {
                $result = SinglePlayerGameService::resignGame($gameId, $user->id);
            } elseif ($request->action === 'bot_move') {
                $result = SinglePlayerGameService::triggerBotMove($gameId);
            } elseif ($request->action === 'resolve_trick') {
                \Log::info('Resolve trick action called for game: ' . $gameId);
                $result = SinglePlayerGameService::resolveTrickManually($gameId);
                \Log::info('Resolve trick result: ', $result);
            } else {
                $result = SinglePlayerGameService::makeMove($gameId, $user->id, $request->card_id);
            }

            if (!$result['success']) {
                return response()->json(['message' => $result['message']], 400);
            }

            return response()->json($result['game']);
        }

        // Otherwise, it's a multiplayer game in database
        DB::beginTransaction();
        try {
            // Use lock to prevent race conditions
            $game = Game::where('id', $gameId)->lockForUpdate()->first();

            if (!$game) {
                return response()->json(['message' => 'Game not found'], 404);
            }

            if ($game->status !== 'Playing') {
                return response()->json(['message' => 'Game is not active'], 400);
            }

            if ($game->player1_user_id !== $user->id && $game->player2_user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            if ($request->action === 'resign') {
                \Log::info('RESIGN: Processing resignation request', [
                    'game_id' => $game->id,
                    'user_id' => $user->id,
                    'current_status' => $game->status
                ]);

                $result = $this->handleResignation($game, $user);

                \Log::info('RESIGN: Committing transaction after resignation', [
                    'game_id' => $game->id
                ]);

                DB::commit();

                \Log::info('RESIGN: Transaction committed, returning response', [
                    'game_id' => $game->id
                ]);

                return $result;
            }

            if ($request->action === 'resolve_trick') {
                \Log::info('MULTIPLAYER resolve_trick action received', [
                    'game_id' => $game->id,
                    'user_id' => $user->id,
                    'game_status' => $game->status,
                    'custom_length' => strlen($game->custom ?? '')
                ]);
                return $this->handleMultiplayerTrickResolution($game, $user);
            }

            // Handle card play
            $gameState = json_decode($game->custom, true);
            $result = $this->processMove($gameState, $user->id, $request->card_id);

            if (!$result['success']) {
                return response()->json(['message' => $result['message']], 400);
            }

            $gameState = $result['gameState'];

            \Log::info('Game state after processMove', [
                'current_player' => $gameState['current_player'] ?? 'not_set',
                'status' => $gameState['status'] ?? 'not_set',
                'trump_card' => isset($gameState['trump_card']) ? 'present' : 'missing',
                'player1_hand_count' => count($gameState['player1_hand'] ?? []),
                'player2_hand_count' => count($gameState['player2_hand'] ?? []),
                'current_trick_count' => count($gameState['current_trick'] ?? []),
                'is_bot_game' => $gameState['is_bot_game'] ?? 'not_set'
            ]);

            $game->custom = json_encode($gameState);

            // Check if game is finished
            if ($gameState['status'] === 'finished') {
                $this->finishGame($game, $gameState);
            }

            $game->save();

            \Log::info('Game saved to database', [
                'game_id' => $game->id,
                'custom_length' => strlen($game->custom),
                'custom_preview' => substr($game->custom, 0, 200) . '...'
            ]);

            DB::commit();

            // Return the merged game state like the show method does
            $game->load(['player1', 'player2', 'winner']);
            $response = $game->toArray();

            // Merge custom game state fields for frontend compatibility
            $customState = json_decode($game->custom, true) ?? [];
            $response = array_merge($response, $customState);

            \Log::info('Returning merged game state', [
                'has_trump_card' => isset($response['trump_card']),
                'has_current_player' => isset($response['current_player']),
                'current_player' => $response['current_player'] ?? 'missing',
                'player1_hand_count' => count($response['player1_hand'] ?? []),
                'player2_hand_count' => count($response['player2_hand'] ?? [])
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('MAKEMOVE: Exception occurred, rolling back transaction', [
                'game_id' => $gameId ?? 'unknown',
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? 'unknown'
            ]);

            DB::rollback();
            return response()->json(['message' => 'Failed to make move'], 500);
        }
    }

    /**
     * Get user's game history
     */
    public function history()
    {
        $user = Auth::user();

        $games = Game::where(function($query) use ($user) {
            $query->where('player1_user_id', $user->id)
                  ->orWhere('player2_user_id', $user->id);
        })
        ->where('status', 'Ended')
        ->with(['player1', 'player2', 'winner', 'match'])
        ->orderBy('ended_at', 'desc')
        ->paginate(10);

        return response()->json($games);
    }

    /**
     * Initialize game state for Bisca
     */
    private function initializeGameState($type, $player1Id, $player2Id)
    {
        $deck = $this->createBiscaDeck();
        $handSize = $type == '3' ? 3 : 9;

        // Shuffle deck
        shuffle($deck);

        // Deal cards
        $player1Hand = array_splice($deck, 0, $handSize);
        $player2Hand = $player2Id ? array_splice($deck, 0, $handSize) : [];

        // Trump card (next card from deck)
        $trumpCard = array_shift($deck);
        $trumpSuit = $trumpCard['suit'];

        // Coin flip to determine who goes first (heads = player1, tails = player2)
        $coinFlip = mt_rand(0, 1); // 0 = heads (player1), 1 = tails (player2)
        $firstPlayer = $coinFlip === 0 ? $player1Id : $player2Id;

        \Log::info('Coin flip result', [
            'coinFlip' => $coinFlip === 0 ? 'heads' : 'tails',
            'firstPlayer' => $firstPlayer,
            'player1Id' => $player1Id,
            'player2Id' => $player2Id
        ]);

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
            'current_player' => $firstPlayer,
            'trick_leader' => $firstPlayer,
            'status' => 'playing',
            'is_bot_game' => $player2Id === null,
            'move_timer' => null,
            'last_move_at' => now()->toISOString(),
            'coin_flip_result' => $coinFlip === 0 ? 'heads' : 'tails',
            'first_player' => $firstPlayer
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
     * Process a player's move
     */
    private function processMove($gameState, $playerId, $cardId)
    {
        \Log::info('ProcessMove called', [
            'player_id' => $playerId,
            'card_id' => $cardId,
            'current_player' => $gameState['current_player'],
            'is_bot_game' => $gameState['is_bot_game'] ?? 'not_set',
            'player1_id' => $gameState['player1_id'] ?? 'not_set',
            'player2_id' => $gameState['player2_id'] ?? 'not_set'
        ]);

        // Validate it's player's turn
        if ($gameState['current_player'] !== $playerId) {
            return ['success' => false, 'message' => 'Not your turn'];
        }

        // Check if trick already has 2 cards (should be resolved first)
        if (count($gameState['current_trick']) >= 2) {
            \Log::warning('Attempted to add card to full trick', [
                'current_trick_count' => count($gameState['current_trick']),
                'player_id' => $playerId,
                'card_id' => $cardId
            ]);
            return ['success' => false, 'message' => 'Previous trick must be resolved first'];
        }

        // Determine which hand the player is using
        $isPlayer1 = ($gameState['player1_id'] ?? null) == $playerId;
        $playerHand = $isPlayer1 ? $gameState['player1_hand'] : $gameState['player2_hand'];

        $cardIndex = array_search($cardId, array_column($playerHand, 'id'));
        if ($cardIndex === false) {
            return ['success' => false, 'message' => 'Card not in hand'];
        }

        $playedCard = $playerHand[$cardIndex];

        // Add owner to played card
        $playedCard['played_by'] = $playerId;

        // Remove card from hand
        if ($isPlayer1) {
            array_splice($gameState['player1_hand'], $cardIndex, 1);
        } else {
            array_splice($gameState['player2_hand'], $cardIndex, 1);
        }

        // Add to current trick
        $gameState['current_trick'][] = $playedCard;
        $gameState['last_move_at'] = now()->toISOString();

        \Log::info('Card added to trick', [
            'player_id' => $playerId,
            'card' => $playedCard,
            'trick_count_after' => count($gameState['current_trick']),
            'current_trick' => $gameState['current_trick']
        ]);

        // If this completes a trick (2 cards), don't resolve it yet
        // Let frontend handle the timing like single-player
        if (count($gameState['current_trick']) === 2) {
            // Don't resolve trick automatically - frontend will call resolve_trick
            // Just mark that trick is complete but don't change current player yet
            $gameState['trick_complete'] = true;
            \Log::info('Trick completed, waiting for frontend to resolve', [
                'trick_complete' => true,
                'current_player_unchanged' => $gameState['current_player']
            ]);
        } else {
            // Switch to other player for next card
            $opponentId = $this->getOpponentId($gameState, $playerId);
            \Log::info('Switching to opponent', [
                'current_player_was' => $gameState['current_player'],
                'switching_to' => $opponentId,
                'is_bot_game' => $gameState['is_bot_game'] ?? false
            ]);

            $gameState['current_player'] = $opponentId;

            // If it's a bot game and now it's bot's turn, make bot move
            if ($gameState['is_bot_game'] && $gameState['current_player'] === null) {
                $this->makeBotMove($gameState);
            }
        }

        // Check if game is finished - both players must have no cards AND deck must be empty
        if (empty($gameState['player1_hand']) && empty($gameState['player2_hand']) && empty($gameState['deck'])) {
            $gameState['status'] = 'finished';

            // Calculate final scores
            $player1Score = array_sum(array_column($gameState['player1_tricks'], 'points'));
            $player2Score = array_sum(array_column($gameState['player2_tricks'], 'points'));

            $gameState['player1_points'] = $player1Score;
            $gameState['player2_points'] = $player2Score;
        }

        return ['success' => true, 'gameState' => $gameState];
    }

    /**
     * Resolve a completed trick
     */
    private function resolveTrick(&$gameState)
    {
        $trick = $gameState['current_trick'];
        $trumpSuit = $gameState['trump_suit'];

        // Determine winner
        $winner = $this->determineTrickWinner($trick[0], $trick[1], $trumpSuit);
        $winnerId = $winner['played_by'];

        // Add trick points to winner
        $trickCards = $trick;

        $isPlayer1Winner = ($gameState['player1_id'] ?? null) == $winnerId;

        if ($isPlayer1Winner) {
            $gameState['player1_tricks'] = array_merge($gameState['player1_tricks'], $trickCards);
        } else {
            $gameState['player2_tricks'] = array_merge($gameState['player2_tricks'], $trickCards);
        }

        // Clear current trick
        $gameState['current_trick'] = [];

        // Update scores based on tricks
        $this->updateScores($gameState);

        // Winner leads next trick
        $gameState['current_player'] = $winnerId;
        $gameState['trick_leader'] = $winnerId;

        // Draw cards after trick resolution (one card per player if deck has cards)
        // Winner draws first, then other player
        if (!empty($gameState['deck'])) {
            $isPlayer1Winner = ($gameState['player1_id'] ?? null) == $winnerId;

            if ($isPlayer1Winner) {
                // Player 1 wins: player1 draws first, then player2
                if (!empty($gameState['deck'])) {
                    $gameState['player1_hand'][] = array_shift($gameState['deck']);
                }
                if (!empty($gameState['deck'])) {
                    $gameState['player2_hand'][] = array_shift($gameState['deck']);
                }
            } else {
                // Player 2 wins: player2 draws first, then player1
                if (!empty($gameState['deck'])) {
                    $gameState['player2_hand'][] = array_shift($gameState['deck']);
                }
                if (!empty($gameState['deck'])) {
                    $gameState['player1_hand'][] = array_shift($gameState['deck']);
                }
            }
        }

        // Check if current player has cards to play, if not switch to the other player
        // This can happen in the final phase when deck is empty and cards are uneven
        if (empty($gameState['deck'])) {
            $currentPlayer = $gameState['current_player'];
            $player1HasCards = !empty($gameState['player1_hand']);
            $player2HasCards = !empty($gameState['player2_hand']);

            if ($currentPlayer === $gameState['player2_id'] && !$player2HasCards && $player1HasCards) {
                \Log::info('Player 2 has no cards but player 1 does, switching current player to player 1');
                $gameState['current_player'] = $gameState['player1_id'];
            } elseif ($currentPlayer === $gameState['player1_id'] && !$player1HasCards && $player2HasCards) {
                \Log::info('Player 1 has no cards but player 2 does, switching current player to player 2');
                $gameState['current_player'] = $gameState['player2_id'];
            }
        }

        // Check if game should end (SIMPLIFIED: ANY player has no cards AND deck is empty)
        if (empty($gameState['deck']) && (empty($gameState['player1_hand']) || empty($gameState['player2_hand']))) {
            \Log::info('Multiplayer game ending after trick resolution: at least one player has no cards and deck is empty');
            $this->finishMultiplayerGame($gameState);
            return; // CRITICAL: Return immediately when game ends
        }
    }

    /**
     * Determine the winner of a trick
     */
    private function determineTrickWinner($card1, $card2, $trumpSuit)
    {
        // Trump beats non-trump
        if ($card1['suit'] === $trumpSuit && $card2['suit'] !== $trumpSuit) {
            return $card1;
        }
        if ($card2['suit'] === $trumpSuit && $card1['suit'] !== $trumpSuit) {
            return $card2;
        }

        // If both trump or both same suit, higher value wins
        if ($card1['suit'] === $card2['suit']) {
            return $card1['value'] > $card2['value'] ? $card1 : $card2;
        }

        // Different suits, first card wins
        return $card1;
    }

    /**
     * Make a bot move (simple strategy)
     */
    private function makeBotMove(&$gameState)
    {
        $cardToPlay = BotController::makeBotMove($gameState);

        if (!$cardToPlay) {
            return; // No valid move
        }

        // Remove card from bot hand
        $cardIndex = array_search($cardToPlay['id'], array_column($gameState['player2_hand'], 'id'));
        if ($cardIndex !== false) {
            array_splice($gameState['player2_hand'], $cardIndex, 1);

            // Add to current trick
            $cardToPlay['played_by'] = null; // Bot ID
            $gameState['current_trick'][] = $cardToPlay;
            $gameState['last_move_at'] = now()->toISOString();

            // If trick is complete, resolve it
            if (count($gameState['current_trick']) === 2) {
                $this->resolveTrick($gameState);
            }
        }
    }

    /**
     * Check if a card can win against the lead card
     */
    private function canWinTrick($card, $leadCard, $trumpSuit)
    {
        // Trump beats non-trump
        if ($card['suit'] === $trumpSuit && $leadCard['suit'] !== $trumpSuit) {
            return true;
        }

        // Same suit, higher value wins
        if ($card['suit'] === $leadCard['suit'] && $card['value'] > $leadCard['value']) {
            return true;
        }

        return false;
    }

    /**
     * Get opponent's ID
     */
    private function getOpponentId($gameState, $playerId)
    {
        // In bot games, opponent is null (bot)
        if ($gameState['is_bot_game']) {
            return null;
        }

        // In multiplayer games, return the other player's ID
        $player1Id = $gameState['player1_id'] ?? null;
        $player2Id = $gameState['player2_id'] ?? null;

        return $playerId == $player1Id ? $player2Id : $player1Id;
    }

    /**
     * Handle player resignation
     */
    private function handleResignation($game, $user)
    {
        \Log::info('RESIGN: Starting resignation process', [
            'game_id' => $game->id,
            'user_id' => $user->id,
            'game_status_before' => $game->status,
            'player1_id' => $game->player1_user_id,
            'player2_id' => $game->player2_user_id
        ]);

        $gameState = json_decode($game->custom, true);

        // Update the custom game state to reflect the end
        $gameState['status'] = 'Ended';
        $gameState['ended_at'] = now()->toISOString();
        $gameState['winner'] = $game->player1_user_id === $user->id ?
                               $game->player2_user_id : $game->player1_user_id;
        $gameState['resigned_by'] = $user->id;

        // Opponent wins
        $opponentId = $game->player1_user_id === $user->id ?
                     $game->player2_user_id : $game->player1_user_id;

        $game->winner_user_id = $opponentId;
        $game->loser_user_id = $user->id;
        $game->status = 'Ended';
        $game->ended_at = now();
        $game->total_time = now()->diffInSeconds($game->began_at);
        $game->custom = json_encode($gameState); // Update custom state

        \Log::info('RESIGN: Updated game state', [
            'game_id' => $game->id,
            'new_status' => $game->status,
            'winner_id' => $game->winner_user_id,
            'loser_id' => $game->loser_user_id,
            'custom_state_status' => $gameState['status'],
            'custom_state_winner' => $gameState['winner']
        ]);

        // Award payout to winner (winner gets both players' stakes)
        $gameFee = $gameState['game_fee'] ?? 2; // Get the actual game fee per player
        $payout = $gameFee * 2; // Winner gets both stakes

        if ($opponentId) { // Not a bot game
            $winner = User::find($opponentId);
            $this->awardGamePayout($winner, $payout, $game->id, "Multiplayer resignation win");

            \Log::info('RESIGN: Payout awarded to winner', [
                'game_id' => $game->id,
                'winner_id' => $opponentId,
                'game_fee_per_player' => $gameFee,
                'total_payout' => $payout
            ]);
        }

        $game->save();

        \Log::info('RESIGN: Game saved, resignation complete', [
            'game_id' => $game->id,
            'resigned_by' => $user->id,
            'winner' => $opponentId,
            'status' => $game->status,
            'database_updated' => 'yes'
        ]);

        return response()->json($game->load(['player1', 'player2', 'winner']));
    }

    /**
     * Finish a completed game
     */
    private function finishGame($game, $gameState)
    {
        $player1Score = $gameState['player1_points'];
        $player2Score = $gameState['player2_points'];

        $game->player1_points = $player1Score;
        $game->player2_points = $player2Score;
        $game->status = 'Ended';
        $game->ended_at = now();
        $game->total_time = now()->diffInSeconds($game->began_at);

        if ($player1Score > $player2Score) {
            $game->winner_user_id = $game->player1_user_id;
            $game->loser_user_id = $game->player2_user_id;
        } elseif ($player2Score > $player1Score) {
            $game->winner_user_id = $game->player2_user_id;
            $game->loser_user_id = $game->player1_user_id;
        } else {
            $game->is_draw = true;
        }

        // Award payouts for standalone games
        if (!$game->match_id) {
            $gameFee = $gameState['game_fee'] ?? 2; // Default 2 coins per player for standalone games

            if ($game->is_draw) {
                // In case of draw, refund original stakes to each player
                $player1 = User::find($game->player1_user_id);
                $player2 = User::find($game->player2_user_id);
                $this->awardGamePayout($player1, $gameFee, $game->id, 'Draw refund');
                $this->awardGamePayout($player2, $gameFee, $game->id, 'Draw refund');

                \Log::info('PAYOUT: Draw - refunding stakes to both players', [
                    'game_id' => $game->id,
                    'game_fee_per_player' => $gameFee,
                    'player1_refund' => $gameFee,
                    'player2_refund' => $gameFee
                ]);
            } elseif ($game->winner_user_id) {
                // Winner takes both stakes (simple winner-takes-all system)
                $totalPayout = $gameFee * 2; // Winner gets both players' stakes
                $winner = User::find($game->winner_user_id);

                $winnerScore = $game->winner_user_id === $game->player1_user_id ? $player1Score : $player2Score;
                $this->awardGamePayout($winner, $totalPayout, $game->id, "Multiplayer win ({$winnerScore} points)");

                \Log::info('PAYOUT: Winner takes all stakes', [
                    'game_id' => $game->id,
                    'winner_id' => $game->winner_user_id,
                    'winner_score' => $winnerScore,
                    'game_fee_per_player' => $gameFee,
                    'total_payout' => $totalPayout
                ]);
            }
        }

        // If this is part of a match, check match progression
        if ($game->match_id) {
            $this->onMatchGameEnd($game->id);
        }
    }

    /**
     * Update player scores based on their tricks
     */
    private function updateScores(&$gameState)
    {
        $player1Score = 0;
        if (!empty($gameState['player1_tricks'])) {
            foreach ($gameState['player1_tricks'] as $card) {
                $player1Score += $card['points'] ?? 0;
            }
        }

        $player2Score = 0;
        if (!empty($gameState['player2_tricks'])) {
            foreach ($gameState['player2_tricks'] as $card) {
                $player2Score += $card['points'] ?? 0;
            }
        }

        $gameState['player1_points'] = $player1Score;
        $gameState['player2_points'] = $player2Score;
    }

    /**
     * Deduct game fee from player
     */
    private function deductGameFee($user, $amount, $gameId)
    {
        $user->coins_balance -= $amount;
        $user->save();

        CoinTransaction::create([
            'transaction_datetime' => now(),
            'user_id' => $user->id,
            'game_id' => $gameId,
            'coin_transaction_type_id' => 3, // Game fee
            'coins' => -$amount
        ]);
    }

    /**
     * Award game payout to winner
     */
    private function awardGamePayout($user, $amount, $gameId, $description = null)
    {
        $user->coins_balance += $amount;
        $user->save();

        $transactionData = [
            'transaction_datetime' => now(),
            'user_id' => $user->id,
            'game_id' => $gameId,
            'coin_transaction_type_id' => 5, // Game payout
            'coins' => $amount
        ];

        // Add description to custom field if provided
        if ($description) {
            $transactionData['custom'] = json_encode(['description' => $description]);
        }

        CoinTransaction::create($transactionData);
    }

    /**
     * Create an anonymous single-player game (for non-registered users)
     */
    public function createAnonymousGame(Request $request)
    {
        $request->validate([
            'type' => 'required|in:3,9'
        ]);

        // Anonymous games are always single-player against bot, handled in memory
        $game = SinglePlayerGameService::createGame($request->type, null);

        return response()->json($game, 201);
    }

    /**
     * Callback when a match game ends
     */
    public function onMatchGameEnd($gameId)
    {
        $game = Game::findOrFail($gameId);

        if ($game->match_id) {
            $matchController = new \App\Http\Controllers\MatchController();
            $matchController->checkMatchProgress($game->match_id, $game);
        }
    }

    /**
     * Make a move in an anonymous game (no authentication required)
     */
    public function makeAnonymousMove($gameId, Request $request)
    {
        $request->validate([
            'card_id' => 'nullable|string',
            'action' => 'required|in:play_card,resign,bot_move,resolve_trick'
        ]);

        // Debug logging
        \Log::info('Anonymous move attempt', [
            'game_id' => $gameId,
            'action' => $request->action,
            'card_id' => $request->card_id
        ]);

        // Get the single-player game
        $singlePlayerGame = SinglePlayerGameService::getGame($gameId);

        \Log::info('Game lookup result', [
            'game_found' => $singlePlayerGame ? 'yes' : 'no',
            'is_anonymous' => $singlePlayerGame['is_anonymous'] ?? 'n/a'
        ]);

        if (!$singlePlayerGame || !$singlePlayerGame['is_anonymous']) {
            \Log::warning('Game not found or not anonymous', [
                'game_found' => $singlePlayerGame ? 'yes' : 'no',
                'is_anonymous' => $singlePlayerGame['is_anonymous'] ?? 'n/a'
            ]);
            return response()->json(['message' => 'Game not found or not anonymous'], 404);
        }

        // For anonymous games, always use 'anonymous' as the player ID
        $playerId = 'anonymous';

        if ($request->action === 'resign') {
            $result = SinglePlayerGameService::resignGame($gameId, $playerId);
        } elseif ($request->action === 'bot_move') {
            $result = SinglePlayerGameService::triggerBotMove($gameId);
        } elseif ($request->action === 'resolve_trick') {
            $result = SinglePlayerGameService::resolveTrickManually($gameId);
        } else {
            $result = SinglePlayerGameService::makeMove($gameId, $playerId, $request->card_id);
        }

        if (!$result['success']) {
            \Log::error('Move failed', ['error' => $result['message']]);
            return response()->json(['message' => $result['message']], 400);
        }

        return response()->json($result['game']);
    }

    /**
     * Get anonymous game state (no authentication required)
     */
    public function showAnonymousGame($gameId)
    {
        $singlePlayerGame = SinglePlayerGameService::getGame($gameId);
        if (!$singlePlayerGame || !$singlePlayerGame['is_anonymous']) {
            return response()->json(['message' => 'Game not found or not anonymous'], 404);
        }

        return response()->json($singlePlayerGame);
    }

    /**
     * Get the Bot user ID
     */
    private function getBotUserId()
    {
        static $botUserId = null;

        if ($botUserId === null) {
            $botUser = User::where('email', 'bot@system.local')->first();
            $botUserId = $botUser ? $botUser->id : null;
        }

        return $botUserId;
    }

    /**
     * Handle multiplayer trick resolution (called by frontend after 3 second delay)
     */
    private function handleMultiplayerTrickResolution($game, $user)
    {
        DB::beginTransaction();
        try {
            // Use lock to prevent race conditions during trick resolution
            $game = Game::where('id', $game->id)->lockForUpdate()->first();

            if (!$game || $game->status !== 'Playing') {
                return response()->json(['message' => 'Game is not active'], 400);
            }

            if ($game->player1_user_id !== $user->id && $game->player2_user_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $gameState = json_decode($game->custom, true);

            if (count($gameState['current_trick']) !== 2) {
                \Log::info('Trick is not complete or already resolved', [
                    'trick_length' => count($gameState['current_trick']),
                    'current_trick' => $gameState['current_trick']
                ]);

                // If trick is already resolved (length = 0), return current state instead of error
                if (count($gameState['current_trick']) === 0) {
                    \Log::info('Trick already resolved, returning current state');

                    // Return merged game state like a normal response
                    $game = $game->load(['player1', 'player2', 'winner']);
                    $response = $game->toArray();
                    $response = array_merge($response, $gameState);
                    return response()->json($response);
                }

                return response()->json(['message' => 'Trick is not complete'], 400);
            }

            \Log::info('Resolving multiplayer trick', [
                'game_id' => $game->id,
                'trick' => $gameState['current_trick'],
                'before_scores' => [
                    'player1' => $gameState['player1_points'],
                    'player2' => $gameState['player2_points']
                ]
            ]);

            // Resolve the trick
            $this->resolveTrick($gameState);

            \Log::info('After resolveTrick called', [
                'current_trick_length' => count($gameState['current_trick']),
                'current_trick' => $gameState['current_trick'],
                'current_player' => $gameState['current_player'],
                'trick_leader' => $gameState['trick_leader'] ?? 'not_set'
            ]);

            // Draw new cards if deck has cards
            $handSize = $gameState['type'] == '3' ? 3 : 9;
            if (!empty($gameState['deck']) && count($gameState['player1_hand']) < $handSize) {
                \Log::info('Drawing cards after multiplayer trick resolution', [
                    'deck_size_before' => count($gameState['deck']),
                    'player1_hand_size_before' => count($gameState['player1_hand']),
                    'player2_hand_size_before' => count($gameState['player2_hand']),
                    'trick_winner' => $gameState['trick_leader']
                ]);

                // Winner draws first, then other player
                $winnerId = $gameState['trick_leader'];
                $winnerIsPlayer1 = ($gameState['player1_id'] ?? null) == $winnerId;

                if ($winnerIsPlayer1) {
                    if (!empty($gameState['deck'])) {
                        $gameState['player1_hand'][] = array_shift($gameState['deck']);
                    }
                    if (!empty($gameState['deck'])) {
                        $gameState['player2_hand'][] = array_shift($gameState['deck']);
                    }
                } else {
                    if (!empty($gameState['deck'])) {
                        $gameState['player2_hand'][] = array_shift($gameState['deck']);
                    }
                    if (!empty($gameState['deck'])) {
                        $gameState['player1_hand'][] = array_shift($gameState['deck']);
                    }
                }

                \Log::info('Cards drawn after multiplayer trick resolution', [
                    'deck_size_after' => count($gameState['deck']),
                    'player1_hand_size_after' => count($gameState['player1_hand']),
                    'player2_hand_size_after' => count($gameState['player2_hand'])
                ]);
            }

            // Check if game is finished (either player has no cards)
            if (empty($gameState['player1_hand']) || empty($gameState['player2_hand'])) {
                \Log::info('Multiplayer game ending after trick resolution');
                $this->finishMultiplayerGame($gameState);
            }

            // Update game state
            $game->custom = json_encode($gameState);

            // Set a flag to indicate recent trick resolution (to prevent stale reads)
            $gameState['last_trick_resolved_at'] = now()->timestamp;
            $game->custom = json_encode($gameState);

            \Log::info('About to save game after trick resolution', [
                'current_trick_length' => count($gameState['current_trick']),
                'current_player' => $gameState['current_player'],
                'trick_leader' => $gameState['trick_leader'] ?? 'not_set',
                'game_id' => $game->id,
                'gameState_preview' => [
                    'current_trick_length' => count($gameState['current_trick']),
                    'player1_points' => $gameState['player1_points'],
                    'player2_points' => $gameState['player2_points'],
                    'current_player' => $gameState['current_player']
                ],
                'json_length' => strlen(json_encode($gameState)),
                'custom_field_length' => strlen($game->custom)
            ]);

            // Check if game is finished
            if ($gameState['status'] === 'finished') {
                $this->finishGame($game, $gameState);
            }

            $game->save();

            DB::commit();

            // Force connection flush to ensure data is written
            DB::connection()->getPdo()->exec('COMMIT');

            \Log::info('Transaction committed successfully');

            // Return merged game state
            $response = $game->toArray();
            $response = array_merge($response, $gameState);

            \Log::info('Returning response after commit', [
                'response_trick_length' => count($response['current_trick'] ?? []),
                'response_p1_points' => $response['player1_points'] ?? 'missing',
                'response_p2_points' => $response['player2_points'] ?? 'missing',
                'response_current_player' => $response['current_player'] ?? 'missing',
                'response_p1_hand_size' => count($response['player1_hand'] ?? []),
                'response_p2_hand_size' => count($response['player2_hand'] ?? [])
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error resolving multiplayer trick', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to resolve trick'], 500);
        }
    }

    /**
     * Finish multiplayer game and calculate results
     */
    private function finishMultiplayerGame(&$gameState)
    {
        $gameState['status'] = 'Ended';
        $gameState['ended_at'] = now()->toISOString();

        $player1Score = $gameState['player1_points'];
        $player2Score = $gameState['player2_points'];

        if ($player1Score > $player2Score) {
            $gameState['winner'] = $gameState['player1_id'];
        } elseif ($player2Score > $player1Score) {
            $gameState['winner'] = $gameState['player2_id'];
        } else {
            $gameState['is_draw'] = true;
        }
    }

    /**
     * Give coins to a user (Admin only)
     */
    public function giveCoins(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|integer|min:1|max:1000',
            'reason' => 'string|max:255'
        ]);

        $user = User::find($request->user_id);

        if ($user->type !== 'P') {
            return response()->json(['message' => 'Cannot give coins to administrators'], 400);
        }

        $oldBalance = $user->coins_balance;
        $user->coins_balance += $request->amount;
        $user->save();

        // Create transaction record
        CoinTransaction::create([
            'transaction_datetime' => now(),
            'user_id' => $user->id,
            'game_id' => null,
            'coin_transaction_type_id' => 1, // Bonus
            'coins' => $request->amount,
            'custom' => json_encode([
                'description' => $request->reason ?? 'Admin bonus',
                'admin_action' => true,
                'admin_id' => Auth::id(),
                'previous_balance' => $oldBalance,
                'new_balance' => $user->coins_balance
            ])
        ]);

        return response()->json([
            'message' => 'Coins awarded successfully',
            'user' => $user->name,
            'amount' => $request->amount,
            'new_balance' => $user->coins_balance
        ]);
    }
}
