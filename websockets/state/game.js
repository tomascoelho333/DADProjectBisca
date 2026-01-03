import { triggerGameUpdate } from "../events/game.js"

const games = new Map()
let currentGameID = 0

export const createGame = (type, user) => {
	currentGameID++
	const game = {
		id: currentGameID,
		type: type, // '3' or '9' for Bisca variants
		creator: user.id,
		player1: user.id,
		player2: null,
		winner: null,
		status: 'waiting', // waiting, playing, finished
		deck: [],
		trumpCard: null,
		trumpSuit: null,
		player1Hand: [],
		player2Hand: [],
		player1Tricks: [],
		player2Tricks: [],
		player1Points: 0,
		player2Points: 0,
		currentTrick: [],
		currentPlayer: user.id,
		trickLeader: user.id,
		beganAt: null,
		endedAt: null,
		moveTimer: null,
		lastMoveAt: null
	}

	// Initialize Bisca deck and deal cards
	initializeBiscaGame(game)
	
	games.set(currentGameID, game)
	return game
} 

export const getGames = () => {
    return Array.from(games.values())
}

const createBiscaDeck = () => {
	const suits = ['copas', 'espadas', 'ouros', 'paus'] // cups, swords, coins, clubs
	const ranks = [
		{ id_value: 1, value: 14, points: 11, name: 'As' },  // Ace is highest in Bisca
		{ id_value: 2, value: 2, points: 0, name: '2' },
		{ id_value: 3, value: 3, points: 0, name: '3' },
		{ id_value: 4, value: 4, points: 0, name: '4' },
		{ id_value: 5, value: 5, points: 0, name: '5' },
		{ id_value: 6, value: 6, points: 0, name: '6' },
		{ id_value: 7, value: 13, points: 10, name: '7' },  // 7 is second highest in Bisca
		{ id_value: 11, value: 8, points: 2, name: 'Valete' },
		{ id_value: 12, value: 9, points: 3, name: 'Dama' },
		{ id_value: 13, value: 10, points: 4, name: 'Rei' }
	]
	
	const deck = []
	for (const suit of suits) {
		for (const rank of ranks) {
			deck.push({
				id: `${suit}_${rank.id_value}`, // Use original value for image file names
				suit,
				value: rank.value, // Use corrected value for comparison
				points: rank.points,
				name: rank.name
			})
		}
	}
	
	return deck
}

const shuffleDeck = (deck) => {
	for (let i = deck.length - 1; i > 0; i--) {
		const j = Math.floor(Math.random() * (i + 1))
		;[deck[i], deck[j]] = [deck[j], deck[i]]
	}
	return deck
}

const initializeBiscaGame = (game) => {
	const deck = createBiscaDeck()
	shuffleDeck(deck)
	
	const handSize = game.type === '3' ? 3 : 9
	
	// Deal cards
	game.player1Hand = deck.splice(0, handSize)
	// Player 2 hand will be dealt when they join
	
	// Trump card
	game.trumpCard = deck.shift()
	game.trumpSuit = game.trumpCard.suit
	
	// Remaining deck
	game.deck = deck
}

export const joinGame = (gameID, player2) => {
    const game = games.get(gameID)
    if (!game || game.player2 !== null) return null
    
    game.player2 = player2
    
    // Deal cards to player 2
    const handSize = game.type === '3' ? 3 : 9
    game.player2Hand = game.deck.splice(0, handSize)
    
    // Start the game
    game.status = 'playing'
    game.beganAt = new Date()
    
    return game
}

export const playCard = (gameID, playerId, cardId) => {
	const game = games.get(gameID)
	if (!game) return { success: false, message: 'Game not found' }
	
	if (game.status !== 'playing') {
		return { success: false, message: 'Game is not active' }
	}
	
	if (game.currentPlayer !== playerId) {
		return { success: false, message: 'Not your turn' }
	}
	
	// Find the card in player's hand
	const playerHand = playerId === game.player1 ? game.player1Hand : game.player2Hand
	const cardIndex = playerHand.findIndex(card => card.id === cardId)
	
	if (cardIndex === -1) {
		return { success: false, message: 'Card not in hand' }
	}
	
	// Remove card from hand and add to current trick
	const playedCard = playerHand.splice(cardIndex, 1)[0]
	playedCard.playedBy = playerId
	game.currentTrick.push(playedCard)
	game.lastMoveAt = new Date()
	
	// Check if trick is complete
	if (game.currentTrick.length === 2) {
		resolveTrick(game)
	} else {
		// Switch to other player
		game.currentPlayer = playerId === game.player1 ? game.player2 : game.player1
	}
	
	// Check if game is finished
	if (game.player1Hand.length === 0 && game.player2Hand.length === 0) {
		finishGame(game)
	}
	
	return { success: true, game }
}

const resolveTrick = (game) => {
	const [card1, card2] = game.currentTrick
	const winner = determineTrickWinner(card1, card2, game.trumpSuit)
	const winnerId = winner.playedBy
	
	// Add trick to winner's tricks
	if (winnerId === game.player1) {
		game.player1Tricks.push(...game.currentTrick)
	} else {
		game.player2Tricks.push(...game.currentTrick)
	}
	
	// Clear current trick
	game.currentTrick = []
	
	// Winner leads next trick
	game.currentPlayer = winnerId
	game.trickLeader = winnerId
	
	// Draw new cards if deck has cards
	const handSize = game.type === '3' ? 3 : 9
	if (game.deck.length > 0 && game.player1Hand.length < handSize) {
		// Winner draws first, then other player
		if (winnerId === game.player1) {
			if (game.deck.length > 0) game.player1Hand.push(game.deck.shift())
			if (game.deck.length > 0) game.player2Hand.push(game.deck.shift())
		} else {
			if (game.deck.length > 0) game.player2Hand.push(game.deck.shift())
			if (game.deck.length > 0) game.player1Hand.push(game.deck.shift())
		}
	}
}

const determineTrickWinner = (card1, card2, trumpSuit) => {
	// Trump beats non-trump
	if (card1.suit === trumpSuit && card2.suit !== trumpSuit) return card1
	if (card2.suit === trumpSuit && card1.suit !== trumpSuit) return card2
	
	// Same suit, higher value wins
	if (card1.suit === card2.suit) {
		return card1.value > card2.value ? card1 : card2
	}
	
	// Different suits, first card wins
	return card1
}

const finishGame = (game) => {
	// Calculate final scores
	game.player1Points = game.player1Tricks.reduce((sum, card) => sum + card.points, 0)
	game.player2Points = game.player2Tricks.reduce((sum, card) => sum + card.points, 0)
	
	// Determine winner
	if (game.player1Points > game.player2Points) {
		game.winner = game.player1
	} else if (game.player2Points > game.player1Points) {
		game.winner = game.player2
	} else {
		game.winner = null // Draw
	}
	
	game.status = 'finished'
	game.endedAt = new Date()
}

export const getGame = (gameID) => {
	return games.get(gameID)
}

export const removeGame = (gameID) => {
	games.delete(gameID)
}
