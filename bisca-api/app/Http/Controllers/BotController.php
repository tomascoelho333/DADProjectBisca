<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BotController extends Controller
{
    /**
     * Simple bot strategy for Bisca
     * The bot follows the rules: when playing second in a trick, attempt to win;
     * if it cannot, it plays its lowest-value card.
     */
    public static function makeBotMove($gameState)
    {
        $botHand = $gameState['player2_hand'];
        $currentTrick = $gameState['current_trick'];
        $trumpSuit = $gameState['trump_suit'];

        // Check if bot has any cards
        if (empty($botHand)) {
            \Log::info('Bot cannot make move: no cards in hand');
            return null;
        }

        if (empty($currentTrick)) {
            // Leading: play the lowest value card (simple strategy)
            $cardToPlay = self::getLowestCard($botHand);
        } else {
            // Following: try to win if possible, otherwise play lowest
            $leadCard = $currentTrick[0];
            $winningCards = self::getWinningCards($botHand, $leadCard, $trumpSuit);

            if (!empty($winningCards)) {
                // Play lowest winning card
                $cardToPlay = self::getLowestCard($winningCards);
            } else {
                // Cannot win, play lowest card
                $cardToPlay = self::getLowestCard($botHand);
            }
        }

        return $cardToPlay;
    }

    /**
     * Get cards that can win against the lead card
     */
    private static function getWinningCards($hand, $leadCard, $trumpSuit)
    {
        $winningCards = [];

        foreach ($hand as $card) {
            if (self::canWinTrick($card, $leadCard, $trumpSuit)) {
                $winningCards[] = $card;
            }
        }

        return $winningCards;
    }

    /**
     * Check if a card can win against the lead card
     */
    private static function canWinTrick($card, $leadCard, $trumpSuit)
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
     * Get the card with lowest value from a hand
     */
    private static function getLowestCard($hand)
    {
        if (empty($hand)) {
            return null;
        }

        $lowestCard = $hand[0];

        foreach ($hand as $card) {
            if ($card['value'] < $lowestCard['value']) {
                $lowestCard = $card;
            }
        }

        return $lowestCard;
    }

    /**
     * Get the card with highest value from a hand
     */
    private static function getHighestCard($hand)
    {
        if (empty($hand)) {
            return null;
        }

        $highestCard = $hand[0];

        foreach ($hand as $card) {
            if ($card['value'] > $highestCard['value']) {
                $highestCard = $card;
            }
        }

        return $highestCard;
    }
}
