<?php

namespace App\Services;

use App\Http\Controllers\BotController;
use App\Models\User;
use App\Models\CoinTransaction;
use Illuminate\Support\Str;

class SinglePlayerGameService
{
    private static $gamesFile = null;

    /**
     * Get the games file path
     */
    private static function getGamesFile()
    {
        if (self::$gamesFile === null) {
            self::$gamesFile = storage_path('app/single_player_games.json');
        }
        return self::$gamesFile;
    }

    /**
     * Load games from file
     */
    private static function loadGames()
    {
        $file = self::getGamesFile();
        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        return $content ? json_decode($content, true) : [];
    }

    /**
     * Save games to file
     */
    private static function saveGames($games)
    {
        $file = self::getGamesFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($file, json_encode($games));
    }

    /**
     * Create a new single-player game (in memory only)
     */
    public static function createGame($type, $userId = null)
    {
        \Log::info('SinglePlayerGameService::createGame called', [
            'type' => $type,
            'userId' => $userId
        ]);

        // Singleplayer games are FREE for everyone (registered and anonymous users)
        // No coin transactions needed as per project specification
        
        $gameId = Str::uuid()->toString();

        // For anonymous players, use 'anonymous' as the player ID
        $player1Id = $userId ?? 'anonymous';

        $isAnonymous = $userId === null;
        \Log::info('Creating free singleplayer game', [
            'userId' => $userId,
            'is_anonymous' => $isAnonymous,
            'gameId' => $gameId
        ]);

        $game = [
            'id' => $gameId,
            'type' => $type,
            'player1_id' => $player1Id,
            'player2_id' => 'bot',
            'status' => 'playing',
            'began_at' => now()->toISOString(),
            'ended_at' => null,
            'winner' => null,
            'is_draw' => false,
            'is_anonymous' => $isAnonymous,
        ];

        // Initialize game state
        $gameState = self::initializeGameState($type, $player1Id, 'bot');
        $game = array_merge($game, $gameState);

        // Save to file
        $games = self::loadGames();
        $games[$gameId] = $game;
        self::saveGames($games);

        return $game;
    }

    /**
     * Get a single-player game by ID
     */
    public static function getGame($gameId)
    {
        $games = self::loadGames();
        return $games[$gameId] ?? null;
    }    /**
     * Make a move in a single-player game
     */
    public static function makeMove($gameId, $playerId, $cardId)
    {
        $games = self::loadGames();
        $game = $games[$gameId] ?? null;

        if (!$game) {
            return ['success' => false, 'message' => 'Game not found'];
        }

        if ($game['status'] !== 'playing') {
            return ['success' => false, 'message' => 'Game is not active'];
        }

        if ($game['current_player'] !== $playerId) {
            return ['success' => false, 'message' => 'Not your turn'];
        }

        // Check if current player has cards to play
        if ($playerId === $game['player1_id'] && empty($game['player1_hand'])) {
            // Player has no cards but this shouldn't happen unless game is already finished
            // Let the trick resolution handle game ending
            return ['success' => false, 'message' => 'No cards to play'];
        }

        if ($playerId === 'bot' && empty($game['player2_hand'])) {
            // Bot has no cards but this shouldn't happen unless game is already finished
            // Let the trick resolution handle game ending
            return ['success' => false, 'message' => 'No cards to play'];
        }

        // Handle bot auto-play request
        if ($playerId === 'bot' || $cardId === 'auto') {
            $result = self::processBotMove($game);
            if (!$result['success']) {
                return $result;
            }
            $game = $result['game'];
        } else {
            // Process player move
            $result = self::processPlayerMove($game, $playerId, $cardId);
            if (!$result['success']) {
                return $result;
            }
            $game = $result['game'];

            // Don't auto-play bot moves immediately - let frontend handle timing
            // The bot will play on the next API call or after a delay

            // Don't auto-process bot moves - let frontend control timing
            // Just set current_player to 'bot' if it's bot's turn
        }

        // Update stored game
        $games[$gameId] = $game;
        self::saveGames($games);

        return ['success' => true, 'game' => $game];
    }

    /**
     * Handle player resignation
     */
    public static function resignGame($gameId, $playerId)
    {
        $games = self::loadGames();
        $game = $games[$gameId] ?? null;

        if (!$game) {
            return ['success' => false, 'message' => 'Game not found'];
        }

        $game['status'] = 'Ended';
        $game['winner'] = 'bot';
        $game['ended_at'] = now()->toISOString();

        $games[$gameId] = $game;
        self::saveGames($games);

        return ['success' => true, 'game' => $game];
    }

    /**
     * Process a player's move
     */
    private static function processPlayerMove($game, $playerId, $cardId)
    {
        // Find the card in player's hand
        $playerHand = $game['player1_hand'];
        $cardIndex = array_search($cardId, array_column($playerHand, 'id'));

        if ($cardIndex === false) {
            return ['success' => false, 'message' => 'Card not in hand'];
        }

        $playedCard = $playerHand[$cardIndex];
        $playedCard['played_by'] = $playerId;

        // Remove card from hand
        array_splice($game['player1_hand'], $cardIndex, 1);

        // Add to current trick
        $game['current_trick'][] = $playedCard;
        $game['last_move_at'] = now()->toISOString();

        // Don't auto-resolve tricks - let frontend handle timing
        // The frontend will call resolve_trick when ready

        // If trick is complete, just switch to other player for now
        if (count($game['current_trick']) === 2) {
            // Don't change current player yet - will be set when trick is resolved
            // The frontend will handle trick resolution timing

            // IMPORTANT: Do NOT check for game end here!
            // The trick must be resolved first so final points are awarded
        } else {
            // Switch to bot for completing the trick
            $game['current_player'] = 'bot';
            
            \Log::info('After player move - checking bot response capability', [
                'bot_hand_size' => count($game['player2_hand']),
                'deck_size' => count($game['deck']),
                'current_trick_length' => count($game['current_trick'])
            ]);
            
            // Special case: if bot has no cards and deck is empty, auto-complete the trick
            if (empty($game['player2_hand']) && empty($game['deck'])) {
                \Log::info('Bot has no cards to respond to player card - auto-completing trick');
                $game['trick_complete'] = true;
            }
        }

        // Do NOT check for game end when playing cards
        // Game end check happens ONLY after trick resolution

        return ['success' => true, 'game' => $game];
    }

    /**
     * Process bot move
     */
    private static function processBotMove($game)
    {
        // Check if bot has any cards left
        if (empty($game['player2_hand'])) {
            \Log::info("Bot cannot make move: no cards in hand");
            return ['success' => false, 'message' => 'Bot has no cards left'];
        }

        $botCard = BotController::makeBotMove($game);

        if (!$botCard) {
            return ['success' => false, 'message' => 'Bot cannot make move'];
        }

        // Remove card from bot hand
        $cardIndex = array_search($botCard['id'], array_column($game['player2_hand'], 'id'));
        if ($cardIndex !== false) {
            array_splice($game['player2_hand'], $cardIndex, 1);

            // Add to current trick
            $botCard['played_by'] = 'bot';
            $game['current_trick'][] = $botCard;
            $game['last_move_at'] = now()->toISOString();

            // Don't auto-resolve trick - let frontend control timing
            // Just set the current player appropriately
            if (count($game['current_trick']) === 2) {
                // Trick is complete, but don't resolve yet
                // Frontend will handle the 3-second display and then call resolution
                $game['trick_complete'] = true;

                // IMPORTANT: Do NOT check for game end here!
                // The trick must be resolved first so final points are awarded
            } else {
                // Switch to player, but check if player has cards
                $game['current_player'] = $game['player1_id'];
                
                \Log::info('After bot move - checking player response capability', [
                    'player_hand_size' => count($game['player1_hand']),
                    'deck_size' => count($game['deck']),
                    'current_trick_length' => count($game['current_trick'])
                ]);
                
                // Special case: if player has no cards and deck is empty, auto-complete the trick
                if (empty($game['player1_hand']) && empty($game['deck'])) {
                    \Log::info('Player has no cards to respond to bot card - auto-completing trick');
                    $game['trick_complete'] = true;
                }
            }
        }

        // Do NOT check for game end when bot plays cards
        // Game end check happens ONLY after trick resolution

        return ['success' => true, 'game' => $game];
    }

    /**
     * Initialize game state for Bisca
     */
    private static function initializeGameState($type, $player1Id, $player2Id)
    {
        $deck = self::createBiscaDeck();
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
            'last_move_at' => now()->toISOString()
        ];
    }

    /**
     * Create a standard Bisca deck (40 cards)
     */
    private static function createBiscaDeck()
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
                    'name' => $rank['name']
                ];
            }
        }

        return $deck;
    }

    /**
     * Resolve a completed trick
     */
    private static function resolveTrick(&$game)
    {
        $trick = $game['current_trick'];
        $trumpSuit = $game['trump_suit'];

        \Log::info('Resolving trick:', [
            'trick' => $trick,
            'trump_suit' => $trumpSuit,
            'before_scores' => [
                'player1' => $game['player1_points'],
                'player2' => $game['player2_points']
            ]
        ]);

        // Determine winner
        $winner = self::determineTrickWinner($trick[0], $trick[1], $trumpSuit);
        $winnerId = $winner['played_by'];

        \Log::info('Trick winner determined:', [
            'winner_card' => $winner,
            'winner_id' => $winnerId,
            'card1_played_by' => $trick[0]['played_by'],
            'card2_played_by' => $trick[1]['played_by'],
            'player1_id' => $game['player1_id'],
            'player2_id' => $game['player2_id']
        ]);

        // Add trick to winner's tricks
        if ($winnerId === $game['player1_id']) {
            $game['player1_tricks'] = array_merge($game['player1_tricks'], $trick);
            \Log::info('Added trick to player1');
        } else {
            $game['player2_tricks'] = array_merge($game['player2_tricks'], $trick);
            \Log::info('Added trick to player2 (bot)');
        }

        // Update scores after each trick
        self::updateScores($game);

        \Log::info('Scores after trick resolution:', [
            'player1' => $game['player1_points'],
            'player2' => $game['player2_points']
        ]);

        // Clear current trick
        $game['current_trick'] = [];

        // Winner leads next trick
        $game['current_player'] = $winnerId;
        $game['trick_leader'] = $winnerId;
        
        \Log::info('After setting next player:', [
            'current_player' => $game['current_player'],
            'trick_leader' => $game['trick_leader'],
            'winner_id' => $winnerId,
            'is_bot' => $winnerId === 'bot'
        ]);

        // IMPORTANT: Check immediately if the current player (trick winner) can actually play
        // This must happen before any card drawing
        if (empty($game['deck'])) {
            $currentPlayer = $game['current_player'];
            $player1HasCards = !empty($game['player1_hand']);
            $player2HasCards = !empty($game['player2_hand']);
            
            \Log::info('Immediate check if trick winner can play', [
                'current_player' => $currentPlayer,
                'player1_has_cards' => $player1HasCards,
                'player2_has_cards' => $player2HasCards
            ]);
            
            if ($currentPlayer === 'bot' && !$player2HasCards && $player1HasCards) {
                \Log::info('Trick winner (bot) has no cards but player does, switching current player to player');
                $game['current_player'] = $game['player1_id'];
                $game['trick_leader'] = $game['player1_id'];
            } elseif ($currentPlayer === $game['player1_id'] && !$player1HasCards && $player2HasCards) {
                \Log::info('Trick winner (player) has no cards but bot does, switching current player to bot');
                $game['current_player'] = 'bot';
                $game['trick_leader'] = 'bot';
            }
        }
    }

    /**
     * Update player scores based on their tricks
     */
    private static function updateScores(&$game)
    {
        $player1Score = array_sum(array_column($game['player1_tricks'], 'points'));
        $player2Score = array_sum(array_column($game['player2_tricks'], 'points'));

        $game['player1_points'] = $player1Score;
        $game['player2_points'] = $player2Score;
    }

    /**
     * Determine the winner of a trick
     */
    private static function determineTrickWinner($card1, $card2, $trumpSuit)
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
     * Finish the game and calculate results
     */
    private static function finishGame(&$game)
    {
        // Update final scores
        self::updateScores($game);

        $player1Score = $game['player1_points'];
        $player2Score = $game['player2_points']; // This is the bot's score

        $game['status'] = 'Ended';
        $game['ended_at'] = now()->toISOString();

        if ($player1Score > $player2Score) {
            $game['winner'] = $game['player1_id'];
        } elseif ($player2Score > $player1Score) {
            $game['winner'] = 'bot';
        } else {
            $game['is_draw'] = true;
        }

        // Award coins ONLY to registered users (not anonymous) who win against the bot
        // Singleplayer is free to play but still rewards good performance
        $userId = $game['player1_id'];
        $isRegisteredUser = $userId && $userId !== 'anonymous';
        $playerWon = $player1Score > $player2Score;
        
        if ($isRegisteredUser && $playerWon) {
            $coinReward = self::calculateSinglePlayerReward($player1Score, $player2Score);
            
            if ($coinReward > 0) {
                $user = User::find($userId);
                if ($user) {
                    // Award coins to the winning player
                    $user->coins_balance += $coinReward;
                    $user->save();

                    // Create transaction record
                    CoinTransaction::create([
                        'transaction_datetime' => now(),
                        'user_id' => $userId,
                        'game_id' => null, // No multiplayer game ID for singleplayer
                        'coin_transaction_type_id' => 5, // Game payout
                        'coins' => $coinReward,
                        'custom' => json_encode([
                            'singleplayer_game_id' => $game['id'],
                            'description' => self::getRewardDescription($player1Score, $player2Score),
                            'player_score' => $player1Score,
                            'bot_score' => $player2Score,
                            'game_type' => 'Singleplayer vs Bot (Free Play Reward)'
                        ])
                    ]);

                    $game['coin_reward'] = $coinReward;

                    \Log::info('Awarded coins for singleplayer victory', [
                        'user_id' => $userId,
                        'singleplayer_game_id' => $game['id'],
                        'player_score' => $player1Score,
                        'bot_score' => $player2Score,
                        'reward' => $coinReward,
                        'new_balance' => $user->coins_balance
                    ]);
                }
            }
        } elseif ($isRegisteredUser && !$playerWon) {
            \Log::info('Registered user lost singleplayer game - no coin reward', [
                'user_id' => $userId,
                'singleplayer_game_id' => $game['id'],
                'player_score' => $player1Score,
                'bot_score' => $player2Score
            ]);
        } elseif (!$isRegisteredUser) {
            \Log::info('Anonymous user completed singleplayer game - no coin transactions', [
                'player_id' => $userId,
                'singleplayer_game_id' => $game['id'],
                'player_score' => $player1Score,
                'bot_score' => $player2Score
            ]);
        }
    }

    /**
     * Calculate coin reward for singleplayer game (only when player beats the bot)
     * Free to play but rewards good performance to encourage engagement
     */
    private static function calculateSinglePlayerReward($playerScore, $botScore)
    {
        // If player lost or tied, no reward
        if ($playerScore <= $botScore) {
            return 0;
        }

        // Smaller rewards for singleplayer since it's free to play
        if ($playerScore == 120) {
            return 3; // Perfect score against bot
        } elseif ($playerScore >= 91) {
            return 2; // High score against bot
        } elseif ($playerScore >= 61) {
            return 1; // Medium score against bot
        } else {
            return 1; // Basic win against bot
        }
    }

    /**
     * Get description for the singleplayer reward
     */
    private static function getRewardDescription($playerScore, $botScore)
    {
        if ($playerScore <= $botScore) {
            return 'Lost to bot';
        } elseif ($playerScore == 120) {
            return 'Perfect game vs bot! (120 points)';
        } elseif ($playerScore >= 91) {
            return 'Excellent game vs bot! (91+ points)';
        } elseif ($playerScore >= 61) {
            return 'Good game vs bot! (61+ points)';
        } else {
            return 'Victory vs bot!';
        }
    }

    /**
     * Clean up finished games (call periodically)
     */
    public static function cleanupFinishedGames()
    {
        $games = self::loadGames();
        $now = now();
        $updated = false;

        foreach ($games as $gameId => $game) {
            if ($game['status'] === 'Ended' && $game['ended_at']) {
                $endTime = \Carbon\Carbon::parse($game['ended_at']);
                if ($now->diffInMinutes($endTime) > 30) { // Remove games older than 30 minutes
                    unset($games[$gameId]);
                    $updated = true;
                }
            }
        }

        if ($updated) {
            self::saveGames($games);
        }
    }

    /**
     * Trigger a bot move manually (for delayed bot play)
     */
    public static function triggerBotMove($gameId)
    {
        $games = self::loadGames();
        $game = $games[$gameId] ?? null;

        if (!$game) {
            return ['success' => false, 'message' => 'Game not found'];
        }

        if ($game['status'] !== 'playing') {
            return ['success' => false, 'message' => 'Game is not active'];
        }

        if ($game['current_player'] !== 'bot') {
            return ['success' => false, 'message' => 'Not bot turn'];
        }

        // Process bot move
        $result = self::processBotMove($game);

        if (!$result['success']) {
            return $result;
        }

        $game = $result['game'];

        // Save updated game
        $games[$gameId] = $game;
        self::saveGames($games);

        return ['success' => true, 'game' => $game];
    }

    /**
     * Manually resolve a completed trick
     */
    public static function resolveTrickManually($gameId)
    {
        \Log::info('Resolve trick manually called for game: ' . $gameId);

        $games = self::loadGames();
        $game = $games[$gameId] ?? null;

        if (!$game) {
            \Log::error('Game not found for trick resolution: ' . $gameId);
            return ['success' => false, 'message' => 'Game not found'];
        }

        \Log::info('Game found, status: ' . $game['status'] . ', trick count: ' . count($game['current_trick']));

        if ($game['status'] !== 'playing') {
            \Log::error('Game is not active, status: ' . $game['status']);
            return ['success' => false, 'message' => 'Game is not active'];
        }

        if (count($game['current_trick']) === 0) {
            \Log::error('No cards in trick to resolve');
            return ['success' => false, 'message' => 'No cards in trick'];
        }

        // Handle incomplete tricks in final phase (when one player has no cards to respond)
        if (count($game['current_trick']) === 1 && empty($game['deck'])) {
            $playedCard = $game['current_trick'][0];
            $playedBy = $playedCard['played_by'];
            
            \Log::info('Auto-resolving incomplete trick in final phase', [
                'played_by' => $playedBy,
                'card_id' => $playedCard['id'],
                'player1_hand_size' => count($game['player1_hand']),
                'player2_hand_size' => count($game['player2_hand'])
            ]);
            
            // The player who played the card automatically wins the incomplete trick
            $winnerId = $playedBy === 'bot' ? 'bot' : $game['player1_id'];
            
            // Award points and clear trick
            $trickPoints = 0;
            foreach ($game['current_trick'] as $card) {
                $trickPoints += $card['points'];
            }
            
            if ($winnerId === $game['player1_id']) {
                $game['player1_points'] += $trickPoints;
                $game['player1_tricks'] = array_merge($game['player1_tricks'], $game['current_trick']);
            } else {
                $game['player2_points'] += $trickPoints;
                $game['player2_tricks'] = array_merge($game['player2_tricks'], $game['current_trick']);
            }
            
            $game['current_trick'] = [];
            $game['trick_leader'] = $winnerId;
            
            \Log::info('Incomplete trick resolved, winner: ' . $winnerId . ', points awarded: ' . $trickPoints);
            
            // Now check if game should end
            if (empty($game['deck']) && (empty($game['player1_hand']) || empty($game['player2_hand']))) {
                \Log::info('GAME END: At least one player has no cards and deck is empty after incomplete trick resolution');
                self::finishGame($game);
            }
            
            $games[$gameId] = $game;
            self::saveGames($games);
            
            return ['success' => true, 'game' => $game];
        }

        if (count($game['current_trick']) !== 2) {
            \Log::error('Trick is not complete and not in final phase, count: ' . count($game['current_trick']));
            return ['success' => false, 'message' => 'Trick is not complete'];
        }

        \Log::info('Resolving trick with cards: ', $game['current_trick']);

        // Resolve the trick
        self::resolveTrick($game);
        
        \Log::info('After resolveTrick called:', [
            'current_player' => $game['current_player'],
            'trick_leader' => $game['trick_leader'],
            'player1_points' => $game['player1_points'],
            'player2_points' => $game['player2_points']
        ]);

        // Draw new cards if deck has cards (one card per player after each trick)
        if (!empty($game['deck'])) {
            \Log::info('Drawing cards after trick resolution', [
                'deck_size_before' => count($game['deck']),
                'player1_hand_size_before' => count($game['player1_hand']),
                'player2_hand_size_before' => count($game['player2_hand']),
                'trick_winner' => $game['trick_leader']
            ]);

            // Winner draws first, then other player (one card each)
            $winnerId = $game['trick_leader'];
            $winnerIsPlayer1 = $game['player1_id'] == $winnerId;

            if ($winnerIsPlayer1) {
                // Player 1 wins: player1 draws first, then player2
                if (!empty($game['deck'])) {
                    $game['player1_hand'][] = array_shift($game['deck']);
                }
                if (!empty($game['deck'])) {
                    $game['player2_hand'][] = array_shift($game['deck']);
                }
            } else {
                // Player 2 (bot) wins: player2 draws first, then player1
                if (!empty($game['deck'])) {
                    $game['player2_hand'][] = array_shift($game['deck']);
                }
                if (!empty($game['deck'])) {
                    $game['player1_hand'][] = array_shift($game['deck']);
                }
            }

            \Log::info('Cards drawn after trick resolution', [
                'deck_size_after' => count($game['deck']),
                'player1_hand_size_after' => count($game['player1_hand']),
                'player2_hand_size_after' => count($game['player2_hand'])
            ]);

            // IMMEDIATE game end check after card dealing - this is critical!
            // SIMPLIFIED RULE: Game ends when ANY player has no cards AND deck is empty
            if (empty($game['deck']) && (empty($game['player1_hand']) || empty($game['player2_hand']))) {
                \Log::info('IMMEDIATE GAME END: At least one player has no cards and deck is empty after card dealing', [
                    'player1_hand_size' => count($game['player1_hand']),
                    'player2_hand_size' => count($game['player2_hand']),
                    'deck_size' => count($game['deck'])
                ]);
                self::finishGame($game);
                
                // Update stored game and return immediately
                $games[$gameId] = $game;
                self::saveGames($games);
                return ['success' => true, 'game' => $game];
            }
        }

        // CRITICAL: Also check for game end when deck was already empty (no cards drawn)
        // SIMPLIFIED RULE: Game ends when ANY player has no cards AND deck is empty
        if (empty($game['deck']) && (empty($game['player1_hand']) || empty($game['player2_hand']))) {
            \Log::info('IMMEDIATE GAME END: At least one player has no cards and deck is empty', [
                'player1_hand_size' => count($game['player1_hand']),
                'player2_hand_size' => count($game['player2_hand']),
                'deck_size' => count($game['deck'])
            ]);
            self::finishGame($game);
            
            // Update stored game and return immediately
            $games[$gameId] = $game;
            self::saveGames($games);
            return ['success' => true, 'game' => $game];
        }

        // Check if game is finished - SIMPLIFIED RULE: game ends when ANY player has no cards AND deck is empty
        if (empty($game['deck']) && (empty($game['player1_hand']) || empty($game['player2_hand']))) {
            \Log::info('FALLBACK Game ending after trick resolution:', [
                'player1_hand_size' => count($game['player1_hand']),
                'player2_hand_size' => count($game['player2_hand']),
                'deck_size' => count($game['deck']),
                'final_scores' => [
                    'player1' => $game['player1_points'],
                    'player2' => $game['player2_points']
                ]
            ]);
            self::finishGame($game);
        }

        // Update stored game
        $games[$gameId] = $game;
        self::saveGames($games);

        return ['success' => true, 'game' => $game];
    }
}
