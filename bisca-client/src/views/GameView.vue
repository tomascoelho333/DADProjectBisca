<template>
  <div class="game-container">
    <div class="game-header">
      <h1>Bisca Game</h1>
      <div class="game-info">
        <p>Game Mode: {{ props.gameMode === 'single' ? 'Single Player' : 'Multiplayer' }}</p>
        <p>Game Type: {{ gameType === '3' ? 'Bisca dos 3' : 'Bisca dos 9' }}</p>
        <p v-if="gameState">Status: {{ gameState.status }}</p>
        <p v-if="gameState">Your Points: {{ playerPoints }}</p>
        <p v-if="gameState">Opponent Points: {{ opponentPoints }}</p>
      </div>
      <button @click="goBackToDashboard" class="btn btn-secondary">← Back</button>
    </div>

    <div v-if="!gameStarted" class="game-setup">
      <h2>{{ props.gameMode === 'single' ? 'Single Player Game' : 'Multiplayer Game' }}</h2>
      <div class="game-options">
        <label>
          <input type="radio" v-model="gameType" value="3"> Bisca dos 3
        </label>
        <label>
          <input type="radio" v-model="gameType" value="9"> Bisca dos 9
        </label>
      </div>

      <!-- Single Player Mode -->
      <div v-if="props.gameMode === 'single'" class="game-mode">
        <button @click="createSinglePlayerGame" class="btn btn-primary">
          Start Game vs Bot
        </button>
      </div>

      <!-- Multiplayer Mode -->
      <div v-else class="game-mode">
        <button @click="createMultiplayerGame" class="btn btn-primary">
          Create Multiplayer Game
        </button>

        <div v-if="availableGames.length > 0" class="available-games">
          <h3>Join Existing Games</h3>
          <div v-for="game in availableGames" :key="game.id" class="game-item">
            <span>{{ game.type === '3' ? 'Bisca dos 3' : 'Bisca dos 9' }} by {{ game.player1?.nickname }}</span>
            <button @click="joinGame(game.id)" class="btn btn-sm">Join</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="gameStarted && gameState" class="game-board">
      <!-- Game Background -->
      <div class="game-background"></div>

      <!-- Bot Hand (dynamic number of cards based on game type) -->
      <div class="bot-hand">
        <div class="hand-label">{{ getOpponentDisplayName() }} Cards ({{ botHandSize }})</div>
        <div class="bot-cards">
          <div
            v-for="n in botHandSize"
            :key="`bot-card-${n}`"
            class="card bot-card-back"
            :class="{
              'card-shake': isBotThinking,
              'card-draw-animation': cardDrawAnimating && n === botHandSize
            }"
            :style="{
              animationDelay: `${n * 0.1}s`,
              zIndex: n
            }"
          >
            <img src="/cards/semFace.png" alt="Bot card">
          </div>
        </div>
      </div>

      <!-- Game Info Panel -->
      <div class="game-info-panel">
        <div class="game-rules">
          <small>🎯 Each round: 2 cards played (1 per player)</small>
          <small>🏆 Win tricks to score points!</small>
        </div>
      </div>

      <!-- Right Side Panel - Points & Controls -->
      <div class="right-panel">
        <div class="score-display">
          <div class="player-score">
            <h4>You</h4>
            <span class="points">{{ playerPoints }}</span>
          </div>
          <div class="vs">VS</div>
          <div class="opponent-score">
            <h4>{{ isGamePending ? 'Waiting...' : (props.gameMode === 'single' ? 'Bot' : 'Opponent') }}</h4>
            <span class="points">{{ opponentPoints }}</span>
          </div>
        </div>

        <div class="game-controls">
          <button v-if="canResign" @click="resignGame" class="btn btn-danger resign-btn">
            Resign
          </button>

          <button
            v-if="showTrickResult"
            @click="nextRound"
            class="btn btn-primary next-round-btn"
          >
            🎲 Continue Game
          </button>
        </div>
      </div>

      <!-- Trump Card Section -->
      <div class="trump-section">
        <div class="trump-container">
          <div class="trump-label">Trump</div>
          <div class="trump-card" v-if="gameState.trump_card">
            <img :src="getCardImage(gameState.trump_card)" :alt="gameState.trump_card.name" @error="onImageError">
          </div>
          <div class="trump-suit">{{ gameState.trump_suit }}</div>
        </div>
      </div>

      <!-- Current Trick Section -->
      <div class="current-trick">
        <div class="trick-area">
          <div v-if="!gameState?.current_trick || (gameState.current_trick.length === 0 && !showTrickResult)" class="empty-trick">
            <span v-if="isGamePending" class="waiting-for-player">Waiting for another player to join...</span>
            <div v-else-if="showCoinFlip" class="coin-flip-container">
              <div class="coin-flip-text">Flipping coin to decide who goes first...</div>
              <div class="coin" :class="{ 'flipping': isCoinFlipping, 'final-heads': !isCoinFlipping && coinFlipResult === 'heads', 'final-tails': !isCoinFlipping && coinFlipResult === 'tails' }">
                <div class="coin-side heads">
                  {{ props.gameMode === 'single' ? '👤' : '1️⃣' }}
                </div>
                <div class="coin-side tails">
                  {{ props.gameMode === 'single' ? '🤖' : '2️⃣' }}
                </div>
              </div>
              <div v-if="coinFlipResult" class="coin-result">
                {{ getCoinFlipResultText() }}
              </div>
            </div>
            <span v-else-if="isBotThinking" class="bot-thinking">🤖 Bot is thinking...</span>
            <span v-else>{{ isPlayerTurn ? 'Your turn' : 'Waiting for opponent...' }}</span>
          </div>

          <!-- Show trick result before clearing -->
          <div v-if="showTrickResult" class="trick-result">
            <h3>🎉 {{ getPlayerDisplayName(trickWinner) }} won this trick!</h3>
            <div class="trick-cards-result">
              <div
                v-for="(card, index) in lastCompletedTrick"
                :key="`result-${card.id}-${index}`"
                class="played-card result-card"
                :class="{
                  'winner-card': card.played_by === trickWinner
                }"
              >
                <img :src="getCardImage(card)" :alt="card.name" @error="onImageError">
                <div class="player-label">{{ getPlayerDisplayName(card.played_by) }}</div>
              </div>
            </div>
          </div>

          <div v-else-if="gameState?.current_trick && gameState.current_trick.length > 0" class="trick-cards">
            <div
              v-for="(card, index) in gameState.current_trick"
              :key="`${card.id}-${card.played_by}-${index}`"
              class="played-card"
              :class="{
                'player-card': card.played_by === 'anonymous' || card.played_by === userId,
                'bot-card': (props.gameMode === 'single' && card.played_by === 'bot') ||
                           (props.gameMode === 'multiplayer' && card.played_by !== userId && card.played_by !== 'anonymous'),
                'newest-card': index === gameState.current_trick.length - 1,
                'stacked-first': index === 0,
                'stacked-second': index === 1,
                'bot-animation': isBotAnimating &&
                                ((props.gameMode === 'single' && card.played_by === 'bot') ||
                                 (props.gameMode === 'multiplayer' && card.played_by !== userId && card.played_by !== 'anonymous')) &&
                                index === gameState.current_trick.length - 1,
                'player-animation': isPlayerAnimating && (card.played_by === 'anonymous' || card.played_by === userId) && index === gameState.current_trick.length - 1
              }"
              :style="{ zIndex: index + 1 }"
            >
              <img :src="getCardImage(card)" :alt="card.name" @error="onImageError">
              <div class="player-label">{{ getPlayerDisplayName(card.played_by) }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Player Hand -->
      <div class="player-hand">
        <div class="hand-label">Your Cards</div>
        <div class="hand-cards">
          <div
            v-for="card in playerHand"
            :key="card.id"
            class="card player-card"
            :class="{
              disabled: !isPlayerTurn,
              playable: isPlayerTurn,
              animating: animatingCardId === card.id,
              'play-animation': isPlayerAnimating && lastPlayedCard?.id === card.id
            }"
            @click="playCard(card)"
          >
            <img :src="getCardImage(card)" :alt="card.name" @error="onImageError">
          </div>
        </div>
      </div>

      <!-- Game Over Overlay -->
      <div v-if="gameState.status === 'Ended'" class="game-over-overlay">
        <div class="game-over-content">
          <h2>🎮 Game Over!</h2>
          <div class="result">
            <span v-if="gameState.winner === 'anonymous' || gameState.winner === userId" class="win">🏆 You Won!</span>
            <span v-else-if="gameState.winner === 'bot'" class="lose">😔 Bot Won!</span>
            <span v-else-if="gameState.winner && gameState.winner !== userId" class="lose">😔 {{ getPlayerDisplayName(gameState.winner) }} Won!</span>
            <span v-else class="draw">🤝 Draw!</span>
          </div>
          <div class="final-scores">
            <div>Your Score: {{ playerPoints }}</div>
            <div>{{ props.gameMode === 'single' ? 'Bot' : 'Opponent' }} Score: {{ opponentPoints }}</div>
          </div>
          <button @click="goBackToDashboard" class="btn btn-primary new-game-btn">
            🏠 Back to Menu
          </button>
          <!-- Debug info -->
          <div style="margin-top: 10px; font-size: 12px; opacity: 0.7;">
            Debug: Status={{ gameState.status }}, Winner={{ gameState.winner }}, UserID={{ userId }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useUserStore } from '@/stores/user'
import { useRouter } from 'vue-router'
import axios from 'axios'

// Props
const props = defineProps({
  gameMode: {
    type: String,
    default: 'multiplayer', // 'single' or 'multiplayer'
    validator: (value) => ['single', 'multiplayer'].includes(value)
  }
})

const userStore = useUserStore()
const router = useRouter()

// Game state
const gameStarted = ref(false)
const gameState = ref(null)
const gameType = ref('3')
const availableGames = ref([])
const currentGameId = ref(null)

// Animation state
const isPlayerAnimating = ref(false)
const isBotAnimating = ref(false)
const lastPlayedCard = ref(null)
const animatingCardId = ref(null)
const isBotThinking = ref(false)
const cardDrawAnimating = ref(false)
const trickWinner = ref(null)
const showTrickResult = ref(false)
const lastCompletedTrick = ref([])
const isResolvingTrick = ref(false)
const lastTrickResolutionTime = ref(0) // Track when last trick was resolved to prevent polling conflicts

// Coin flip animation state
const showCoinFlip = ref(false)
const isCoinFlipping = ref(false)
const coinFlipResult = ref(null)
const hasShownCoinFlip = ref(false)

const isBotMoveInProgress = ref(false)
const isManualBotTrigger = ref(false)

// Polling state
const pollInterval = ref(null)
const playerJoinPollInterval = ref(null) // For checking if other player has joined

// Computed properties
const userId = computed(() => userStore.user?.id)
const playerHand = computed(() => {
  if (!gameState.value) return []

  if (props.gameMode === 'single') {
    // For single player mode, human is always player1
    return gameState.value.player1_hand
  } else {
    // For multiplayer mode, use the original logic
    return gameState.value.player1_id === userId.value
      ? gameState.value.player1_hand
      : gameState.value.player2_hand
  }
})

const playerPoints = computed(() => {
  if (!gameState.value) return 0

  if (props.gameMode === 'single') {
    // For single player mode, human is always player1
    return gameState.value.player1_points
  } else {
    // For multiplayer mode, use the original logic
    return gameState.value.player1_id === userId.value
      ? gameState.value.player1_points
      : gameState.value.player2_points
  }
})

const opponentPoints = computed(() => {
  if (!gameState.value) return 0

  if (props.gameMode === 'single') {
    // For single player mode, bot is always player2
    return gameState.value.player2_points
  } else {
    // For multiplayer mode, use the original logic
    return gameState.value.player1_id === userId.value
      ? gameState.value.player2_points
      : gameState.value.player1_points
  }
})

const isPlayerTurn = computed(() => {
  if (!gameState.value) return false
  
  // Don't allow moves during trick resolution or when showing trick results
  if (showTrickResult.value || isResolvingTrick.value) return false

  if (props.gameMode === 'single') {
    // For single player mode, check if current_player is 'anonymous' or matches our player ID
    return gameState.value.current_player === 'anonymous' ||
           gameState.value.current_player === userId.value ||
           gameState.value.current_player === gameState.value.player1_id
  } else {
    // For multiplayer mode, use the original logic
    return gameState.value.current_player === userId.value
  }
})

// Check if multiplayer game is pending (waiting for second player)
const isGamePending = computed(() => {
  return props.gameMode === 'multiplayer' &&
         gameState.value &&
         gameState.value.status === 'Pending'
})

const canResign = computed(() => {
  const canResignValue = gameState.value?.status === 'playing'
  console.log('🏳️ Can resign check:', {
    status: gameState.value?.status,
    canResign: canResignValue
  })
  return canResignValue
})

const botHandSize = computed(() => {
  if (!gameState.value) return 3

  // Get bot hand size from game state
  const botHand = gameState.value.player2_hand
  return botHand ? botHand.length : (gameType.value === '3' ? 3 : 9)
})

// Helper function to get player display name
const getPlayerDisplayName = (playerId) => {
  if (!gameState.value || !playerId) return 'Unknown'

  console.log('🔍 DEBUG getPlayerDisplayName:', {
    playerId: playerId,
    userId: userId.value,
    gameMode: props.gameMode,
    player1_id: gameState.value.player1_id,
    player2_id: gameState.value.player2_id,
    player1_data: gameState.value.player1,
    player2_data: gameState.value.player2
  })

  // Handle anonymous players
  if (playerId === 'anonymous') return 'You'

  // Handle bot in single player
  if (playerId === 'bot') return 'Bot'

  // Handle multiplayer - show actual nicknames or fallback
  if (props.gameMode === 'multiplayer') {
    if (playerId === userId.value) {
      return 'You'
    }

    // Find the other player's name
    if (gameState.value.player1_id === playerId) {
      const name = gameState.value.player1?.nickname || gameState.value.player1?.name || 'Player 1'
      console.log('🔍 Found player1 name:', name)
      return name
    } else if (gameState.value.player2_id === playerId) {
      const name = gameState.value.player2?.nickname || gameState.value.player2?.name || 'Player 2'
      console.log('🔍 Found player2 name:', name)
      return name
    }

    return 'Opponent'
  }

  // Single player fallback
  if (playerId === userId.value) {
    return 'You'
  }

  return 'Bot'
}

// Helper function to get opponent display name for hand label
const getOpponentDisplayName = () => {
  if (props.gameMode === 'single') {
    return 'Bot'
  }
  
  if (!gameState.value) return 'Opponent'
  
  // Find the opponent (the player who is not the current user)
  const opponentId = gameState.value.player1_id === userId.value ? gameState.value.player2_id : gameState.value.player1_id
  return getPlayerDisplayName(opponentId)
}

// Helper function to get coin flip result text
const getCoinFlipResultText = () => {
  if (!gameState.value || !coinFlipResult.value) return ''
  
  // In the backend, coin flip result determines current_player directly
  const currentPlayerId = gameState.value.current_player
  const currentPlayerName = getPlayerDisplayName(currentPlayerId)
  
  console.log('🪙 Coin flip result text:', {
    coinFlipResult: coinFlipResult.value,
    currentPlayer: currentPlayerId,
    currentPlayerName: currentPlayerName
  })
  
  return `${currentPlayerName} ${currentPlayerName === 'You' ? 'go' : 'goes'} first!`
}

// Helper function to determine trick winner
const determineTrickWinner = (card1, card2) => {
  if (!gameState.value) return null

  const trumpSuit = gameState.value.trump_suit

  console.log('Determining trick winner:', {
    card1: { suit: card1.suit, value: card1.value, played_by: card1.played_by },
    card2: { suit: card2.suit, value: card2.value, played_by: card2.played_by },
    trumpSuit: trumpSuit
  })

  // Check if either card is trump
  if (card1.suit === trumpSuit && card2.suit !== trumpSuit) {
    console.log('Card1 is trump, card2 is not - card1 wins')
    return card1.played_by
  }
  if (card2.suit === trumpSuit && card1.suit !== trumpSuit) {
    console.log('Card2 is trump, card1 is not - card2 wins')
    return card2.played_by
  }

  // If both trump or neither trump, compare values within same suit
  if (card1.suit === card2.suit) {
    const winner = card1.value > card2.value ? card1.played_by : card2.played_by
    console.log(`Both cards same suit (${card1.suit}), comparing values: ${card1.value} vs ${card2.value} - winner: ${winner}`)
    return winner
  }

  // Different suits, neither trump - first card wins
  console.log('Different suits, neither trump - first card wins:', card1.played_by)
  return card1.played_by
}

// Methods
const goBackToDashboard = () => {
  if (props.gameMode === 'single') {
    router.push('/login')
  } else {
    router.push('/dashboard')
  }
}

const loadAvailableGames = async () => {
  // Only load available games for multiplayer mode
  if (props.gameMode !== 'multiplayer') return

  try {
    const response = await axios.get('/api/games', {
      headers: { Authorization: `Bearer ${userStore.token}` }
    })
    availableGames.value = response.data
  } catch (error) {
    console.error('Error loading games:', error)
  }
}

const createSinglePlayerGame = async () => {
  try {
    // Wait for authentication state to be fully restored
    if (userStore.token && !userStore.user) {
      console.log('Waiting for user data to be restored...')
      await new Promise(resolve => {
        const unwatch = watch(() => userStore.user, (newUser) => {
          if (newUser) {
            unwatch()
            resolve()
          }
        })
        // Timeout after 3 seconds
        setTimeout(() => {
          unwatch()
          resolve()
        }, 3000)
      })
    }

    let response

    // Check if user is logged in
  console.log('User store state:', {
    user: userStore.user,
    token: userStore.token ? 'has_token' : 'no_token',
    isAuthenticated: userStore.isAuthenticated
  })

  if (userStore.user && userStore.token) {
    // Authenticated single player game
    console.log('Creating authenticated single player game')

    // Ensure axios has the authorization header set
    if (!axios.defaults.headers.common['Authorization']) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${userStore.token}`
    }

    response = await axios.post('/api/games', {
      type: gameType.value,
      is_multiplayer: false,
      stake: 1
    }, {
      headers: { Authorization: `Bearer ${userStore.token}` }
    })
  } else {
    // Anonymous single player game
    console.log('Creating anonymous single player game')
    response = await axios.post('/api/games/anonymous', {
      type: gameType.value,
      stake: 1
    })
  }

    console.log('Created single player game:', response.data)
    gameState.value = response.data
    currentGameId.value = response.data.id
    gameStarted.value = true
    hasShownCoinFlip.value = false

    // Reset trick tracking for new game
    lastTrickLength = 0
    processedTrickIds.clear()
    console.log('Reset trick tracking for new single player game')

    console.log('Game ID set to:', currentGameId.value)
    console.log('Is anonymous:', response.data.is_anonymous)

    // No need to load game state for single player - game is managed in memory
  } catch (error) {
    console.error('Error creating single player game:', error)
    alert('Error creating game: ' + (error.response?.data?.message || 'Unknown error'))
  }
}

const createMultiplayerGame = async () => {
  try {
    const response = await axios.post('/api/games', {
      type: gameType.value,
      is_multiplayer: true,
      stake: 1
    }, {
      headers: { Authorization: `Bearer ${userStore.token}` }
    })

    gameState.value = response.data
    currentGameId.value = response.data.id
    gameStarted.value = true
    hasShownCoinFlip.value = false

    // Reset trick tracking for new game
    lastTrickLength = 0
    processedTrickIds.clear()
    console.log('Reset trick tracking for new multiplayer game')

    // Refresh available games
    loadAvailableGames()
  } catch (error) {
    console.error('Error creating game:', error)
    alert('Error creating game: ' + (error.response?.data?.message || 'Unknown error'))
  }
}

const joinGame = async (gameId) => {
  try {
    const response = await axios.post(`/api/games/${gameId}/join`, {}, {
      headers: { Authorization: `Bearer ${userStore.token}` }
    })

    gameState.value = response.data
    currentGameId.value = gameId
    gameStarted.value = true
    hasShownCoinFlip.value = false

    // Reset trick tracking for joined game
    lastTrickLength = 0
    processedTrickIds.clear()
    console.log('Reset trick tracking for joined multiplayer game')

    // Load initial game state
    loadGameState()
  } catch (error) {
    console.error('Error joining game:', error)
    alert('Error joining game: ' + (error.response?.data?.message || 'Unknown error'))
  }
}

const playCard = async (card) => {
  if (!isPlayerTurn.value) return
  if (isPlayerAnimating.value) return // Prevent multiple clicks during animation

  try {
    console.log('Playing card:', card.id, 'Game ID:', currentGameId.value, 'Game mode:', props.gameMode)
    console.log('Is anonymous game:', gameState.value?.is_anonymous)
    console.log('Current player hand size:', playerHand.value.length)
    console.log('Cards in hand:', playerHand.value.map(c => c.id))
    console.log('Attempting to play card:', card.id)

    let response
    if (props.gameMode === 'single') {
      // Check if it's an anonymous single player game or authenticated
      if (gameState.value?.is_anonymous) {
        // Use anonymous API for anonymous single player games
        response = await axios.post(`/api/games/anonymous/${currentGameId.value}/move`, {
          card_id: card.id,
          action: 'play_card'
        })
      } else {
        // Use authenticated API for logged-in single player games
        response = await axios.post(`/api/games/${currentGameId.value}/move`, {
          card_id: card.id,
          action: 'play_card'
        }, {
          headers: { Authorization: `Bearer ${userStore.token}` }
        })
      }
    } else {
      // Use authenticated API for multiplayer games
      response = await axios.post(`/api/games/${currentGameId.value}/move`, {
        card_id: card.id,
        action: 'play_card'
      }, {
        headers: { Authorization: `Bearer ${userStore.token}` }
      })
    }

    // Start player animation first
    isPlayerAnimating.value = true
    lastPlayedCard.value = card
    animatingCardId.value = card.id

    // Update game state immediately so card appears in middle with animation
    console.log('🎴 UPDATING GAME STATE AFTER PLAYING CARD:', {
      before_player1_hand_size: gameState.value?.player1_hand?.length || 0,
      before_player2_hand_size: gameState.value?.player2_hand?.length || 0,
      after_player1_hand_size: response.data.player1_hand?.length || 0,
      after_player2_hand_size: response.data.player2_hand?.length || 0,
      deck_size: response.data.deck?.length || 0,
      current_player: response.data.current_player,
      source: 'PLAY_CARD_UPDATE'
    })
    gameState.value = response.data
    
    console.log('After playing card, game state:', {
      current_trick_length: response.data.current_trick?.length || 0,
      current_player: response.data.current_player,
      status: response.data.status,
      trick_cards: response.data.current_trick
    })

    console.log('Player card animation state:', {
      isPlayerAnimating: isPlayerAnimating.value,
      lastPlayedCard: lastPlayedCard.value,
      animatingCardId: animatingCardId.value,
      currentTrickLength: response.data.current_trick?.length
    })

    // End player animation after delay to show the transition
    setTimeout(() => {
      isPlayerAnimating.value = false
      animatingCardId.value = null
      lastPlayedCard.value = null
    }, 1200) // Longer delay to see the card animation

  } catch (error) {
    // Reset animation state on error
    isPlayerAnimating.value = false
    animatingCardId.value = null
    lastPlayedCard.value = null

    console.error('Error playing card:', error)
    console.error('Game ID:', currentGameId.value)
    console.error('Error response:', error.response?.data)
    
    // If we get "Game is not active" error, immediately check game state
    if (error.response?.data?.message === 'Game is not active') {
      console.log('🔍 Game not active error - checking current game state immediately')
      await loadGameState()
    }
    
    alert('Error playing card: ' + (error.response?.data?.message || 'Unknown error'))
  }
}

const resignGame = async () => {
  console.log('🏳️ Resign button clicked')
  if (!confirm('Are you sure you want to resign?')) {
    console.log('🏳️ Resign cancelled by user')
    return
  }

  console.log('🏳️ User confirmed resignation, proceeding...', {
    gameMode: props.gameMode,
    currentGameId: currentGameId.value,
    isAnonymous: gameState.value?.is_anonymous
  })

  try {
    let response
    if (props.gameMode === 'single') {
      // Check if it's an anonymous single player game or authenticated
      if (gameState.value?.is_anonymous) {
        // Use anonymous API for anonymous single player games
        console.log('🏳️ Resigning anonymous single player game')
        response = await axios.post(`/api/games/anonymous/${currentGameId.value}/move`, {
          action: 'resign'
        })
      } else {
        // Use authenticated API for logged-in single player games
        console.log('🏳️ Resigning authenticated single player game')
        response = await axios.post(`/api/games/${currentGameId.value}/move`, {
          action: 'resign'
        }, {
          headers: { Authorization: `Bearer ${userStore.token}` }
        })
      }
    } else {
      // Use authenticated API for multiplayer games
      console.log('🏳️ Resigning multiplayer game', {
        gameId: currentGameId.value,
        token: userStore.token ? 'present' : 'missing',
        userId: userStore.user?.id
      })
      
      response = await axios.post(`/api/games/${currentGameId.value}/move`, {
        action: 'resign'
      }, {
        headers: { Authorization: `Bearer ${userStore.token}` }
      })
      
      console.log('🏳️ Multiplayer resignation response received:', {
        status: response.data.status,
        winner_user_id: response.data.winner_user_id,
        resigned_by: response.data.resigned_by || response.data.custom?.resigned_by,
        ended_at: response.data.ended_at
      })
    }

    console.log('🏳️ Resignation successful - updating game state:', response.data)
    gameState.value = response.data
    
    // Force stop polling since game is now ended
    if (response.data.status === 'Ended' || (response.data.custom && response.data.custom.status === 'Ended')) {
      console.log('🏳️ Game ended after resignation, stopping polling')
      if (pollInterval.value) {
        clearInterval(pollInterval.value)
        pollInterval.value = null
      }
    }
  } catch (error) {
    console.error('🏳️ Error resigning:', error)
    alert('Error resigning: ' + (error.response?.data?.message || 'Unknown error'))
  }
}

const loadGameState = async () => {
  if (!currentGameId.value) return
  if (showTrickResult.value || isResolvingTrick.value) {
    console.log('Skipping multiplayer poll during trick result display/resolution')
    return
  }
  
  // Check cooldown period after trick resolution to prevent race conditions
  const now = Date.now()
  if (lastTrickResolutionTime.value > 0 && (now - lastTrickResolutionTime.value) < 5000) {
    console.log('Skipping poll during trick resolution cooldown period', {
      lastResolution: lastTrickResolutionTime.value,
      now: now,
      timeDiff: now - lastTrickResolutionTime.value
    })
    return
  }

  try {
    console.log('Polling game state...')
    const response = await axios.get(`/api/games/${currentGameId.value}`, {
      headers: { Authorization: `Bearer ${userStore.token}` }
    })

    const oldStatus = gameState.value?.status
    const newStatus = response.data.status

    console.log('🔍 RAW BACKEND RESPONSE:', {
      status: response.data.status,
      winner: response.data.winner,
      ended_at: response.data.ended_at,
      resigned_by: response.data.resigned_by,
      winner_user_id: response.data.winner_user_id,
      loser_user_id: response.data.loser_user_id
    })
    
    console.log('Polled game state received:', {
      current_trick_length: response.data.current_trick?.length || 0,
      player1_points: response.data.player1_points,
      player2_points: response.data.player2_points,
      player1_hand_size: response.data.player1_hand?.length || 0,
      player2_hand_size: response.data.player2_hand?.length || 0,
      deck_size: response.data.deck?.length || 0,
      status: newStatus,
      oldStatus: oldStatus,
      statusChanged: oldStatus !== newStatus
    })

    // Check if game just started (Pending -> Playing) OR if game is playing and we haven't shown coin flip yet
    if ((oldStatus === 'Pending' && newStatus === 'playing') || 
        (newStatus === 'playing' && !hasShownCoinFlip.value)) {
      console.log('🎉 Game started! Showing coin flip animation.')
      
      // Mark that we've shown the coin flip to avoid showing it multiple times
      hasShownCoinFlip.value = true
      
      // Trigger coin flip animation when game starts
      showCoinFlip.value = true
      isCoinFlipping.value = true
      
      // Get coin flip result from game state
      const flipResult = response.data.coin_flip_result || 'heads'
      console.log('🪙 Coin flip result from backend:', flipResult)
      
      setTimeout(() => {
        isCoinFlipping.value = false
        coinFlipResult.value = flipResult
        console.log('🪙 Coin flip animation completed, showing result:', flipResult)
        
        setTimeout(() => {
          showCoinFlip.value = false
          console.log('Game is now active with both players!')
        }, 2000)
      }, 3000)
    }

    // Only update if we're not showing trick results or resolving
    if (!showTrickResult.value && !isResolvingTrick.value) {
      console.log('🔄 UPDATING GAME STATE FROM POLLING:', {
        before_player1_hand_size: gameState.value?.player1_hand?.length || 0,
        before_player2_hand_size: gameState.value?.player2_hand?.length || 0,
        after_player1_hand_size: response.data.player1_hand?.length || 0,
        after_player2_hand_size: response.data.player2_hand?.length || 0,
        deck_size: response.data.deck?.length || 0,
        current_player: response.data.current_player,
        source: 'POLLING_UPDATE'
      })
      
      // Check if hand sizes are decreasing unexpectedly
      const beforeP1 = gameState.value?.player1_hand?.length || 0
      const afterP1 = response.data.player1_hand?.length || 0
      const beforeP2 = gameState.value?.player2_hand?.length || 0
      const afterP2 = response.data.player2_hand?.length || 0
      
      if (afterP1 < beforeP1 || afterP2 < beforeP2) {
        console.error('🚨 CARDS BEING REMOVED BY POLLING!', {
          beforeP1, afterP1, beforeP2, afterP2,
          responseDeckSize: response.data.deck?.length,
          responseData: response.data
        })
      }
      
      gameState.value = response.data
      
      // Add debug logging for game end detection
      if (response.data.status === 'Ended') {
        console.log('🎮 GAME ENDED DETECTED - STOPPING POLLING:', {
          status: response.data.status,
          winner: response.data.winner,
          player1_points: response.data.player1_points,
          player2_points: response.data.player2_points,
          player1_hand_size: response.data.player1_hand?.length || 0,
          player2_hand_size: response.data.player2_hand?.length || 0,
          deck_size: response.data.deck?.length || 0,
          userId: userId.value
        })
        
        // Stop polling when game ends
        if (pollInterval.value) {
          clearInterval(pollInterval.value)
          pollInterval.value = null
          console.log('✋ Polling stopped - game ended')
        }
      }
    } else {
      console.log('⏸️ Skipping game state update due to trick resolution state:', {
        showTrickResult: showTrickResult.value,
        isResolvingTrick: isResolvingTrick.value,
        source: 'POLLING_SKIPPED'
      })
    }
  } catch (error) {
    console.error('Error loading game state:', error)
  }
}

const pollBotMove = async () => {
  if (!currentGameId.value || !gameState.value) return
  if (gameState.value.status === 'Ended') return
  if (gameState.value.current_player !== 'bot') return
  if (showTrickResult.value) {
    console.log('Skipping poll during trick result display')
    return
  }

  try {
    let response
    if (gameState.value?.is_anonymous) {
      response = await axios.get(`/api/games/anonymous/${currentGameId.value}`)
    } else {
      response = await axios.get(`/api/games/${currentGameId.value}`, {
        headers: { Authorization: `Bearer ${userStore.token}` }
      })
    }

    // Only update if game state actually changed and we're not showing trick results
    if (JSON.stringify(response.data) !== JSON.stringify(gameState.value) && !showTrickResult.value) {
      gameState.value = response.data
    }
  } catch (error) {
    console.error('Error polling for bot move:', error)
  }
}

const triggerBotMove = async () => {
  if (!currentGameId.value || !gameState.value) {
    console.log('Cannot trigger bot move: missing game data')
    isBotThinking.value = false
    return
  }
  if (gameState.value.status === 'Ended') {
    console.log('Cannot trigger bot move: game has ended')
    isBotThinking.value = false
    return
  }
  if (gameState.value.current_player !== 'bot') {
    console.log('Cannot trigger bot move: not bot\'s turn')
    isBotThinking.value = false
    return
  }
  if (isBotMoveInProgress.value) {
    console.log('Bot move already in progress, skipping')
    return
  }
  if (showTrickResult.value) {
    console.log('Trick result is being shown, skipping bot move')
    return
  }

  // Check if bot has any cards left
  if (!gameState.value.player2_hand || gameState.value.player2_hand.length === 0) {
    console.log('Cannot trigger bot move: bot has no cards left')
    isBotThinking.value = false
    return
  }

  const trickLength = gameState.value?.current_trick?.length || 0
  if (trickLength >= 2) {
    console.log('Cannot trigger bot move: trick already complete')
    isBotThinking.value = false
    return
  }

  try {
    console.log('Triggering bot move...')
    isBotMoveInProgress.value = true

    let response
    if (gameState.value?.is_anonymous) {
      response = await axios.post(`/api/games/anonymous/${currentGameId.value}/move`, {
        action: 'bot_move'
      })
    } else {
      response = await axios.post(`/api/games/${currentGameId.value}/move`, {
        action: 'bot_move'
      }, {
        headers: { Authorization: `Bearer ${userStore.token}` }
      })
    }

    gameState.value = response.data
    console.log('Bot move successful')
  } catch (error) {
    console.error('Error triggering bot move:', error)
  } finally {
    isBotMoveInProgress.value = false
    isBotThinking.value = false // Clear thinking state when bot move completes
  }
}

const resetGame = () => {
  gameStarted.value = false
  gameState.value = null
  currentGameId.value = null
  hasShownCoinFlip.value = false
  loadAvailableGames()
}

const getCardImage = (card) => {
  if (!card) return ''

  // Use the card ID directly for image mapping
  // Card IDs are in format: 'copas_1', 'espadas_11', etc.
  if (card.id) {
    // Map card suits to image naming convention
    const suitMap = {
      'copas': 'c',     // cups
      'espadas': 'e',   // swords
      'ouros': 'o',     // coins
      'paus': 'p'       // clubs
    }

    // Extract suit and value from ID (e.g., 'copas_1' -> 'copas', '1')
    const [suitName, idValue] = card.id.split('_')
    const suit = suitMap[suitName] || 'c'

    return `/cards/${suit}${idValue}.png`
  }

  // Fallback to old method if no ID (shouldn't happen with new cards)
  const suitMap = {
    'copas': 'c',     // cups
    'espadas': 'e',   // swords
    'ouros': 'o',     // coins
    'paus': 'p'       // clubs
  }

  const suit = suitMap[card.suit] || 'c'
  let value = card.value

  return `/cards/${suit}${value}.png`
}

const onImageError = (event) => {
  // Fallback to a placeholder card image
  event.target.src = '/cards/semFace.png'
  console.warn('Card image not found, using fallback')
}

// Debug method to restart game if stuck
// Lifecycle
onMounted(() => {
  // Only load available games for multiplayer mode
  loadAvailableGames()

  // Poll for game state updates
  pollInterval.value = setInterval(() => {
    if (gameStarted.value && currentGameId.value) {
      // Stop polling if game is over
      const activeStatuses = ['Pending', 'playing', 'Playing']
      const currentStatus = gameState.value?.status
      
      if (currentStatus && !activeStatuses.includes(currentStatus)) {
        console.log('🛑 Game ended in polling check, stopping polling.', {
          status: currentStatus,
          activeStatuses: activeStatuses,
          isInActiveList: activeStatuses.includes(currentStatus)
        })
        if (pollInterval.value) {
          clearInterval(pollInterval.value)
          pollInterval.value = null
        }
        return
      }
      
      // Additional explicit check for ended status
      if (currentStatus === 'Ended' || currentStatus === 'ended') {
        console.log('🛑 Game ENDED status detected in polling, stopping polling. Status:', currentStatus)
        if (pollInterval.value) {
          clearInterval(pollInterval.value)
          pollInterval.value = null
        }
        return
      }
      
      if (props.gameMode === 'multiplayer') {
        // Poll if:
        // 1. Game is pending (waiting for player2 to join)
        // 2. Game is playing and waiting for other player's move (but not during trick resolution)
        const shouldPoll = gameState.value?.status === 'Pending' || 
                          (gameState.value?.status === 'playing' && 
                           !showTrickResult.value && 
                           !isResolvingTrick.value && 
                           gameState.value?.current_player !== userId.value)
        
        if (shouldPoll) {
          console.log('Polling multiplayer game state', {
            status: gameState.value?.status,
            currentPlayer: gameState.value?.current_player,
            userId: userId.value,
            reason: gameState.value?.status === 'Pending' ? 'waiting for player2' : 'waiting for other player move'
          })
          loadGameState()
        }
      } else if (props.gameMode === 'single' && gameState.value?.status === 'playing' && gameState.value?.current_player === 'bot' && !isBotThinking.value) {
        // For single player, check for bot moves when it's bot's turn and game is still playing
        pollBotMove()
      }
    }
  }, 1500) // Check every 1.5 seconds for faster resignation detection

  // Cleanup on unmount
  onUnmounted(() => {
    if (pollInterval.value) {
      clearInterval(pollInterval.value)
      pollInterval.value = null
    }
  })
})

// Watch for game state changes to trigger bot animations and score updates
let lastTrickLength = 0
let lastPlayerPoints = 0
let lastOpponentPoints = 0
let processedTrickIds = new Set() // Track which trick combinations we've already shown results for

watch(() => gameState.value?.current_trick, (newTrick, oldTrick) => {
  if (!newTrick) return

  console.log('Trick changed:', {
    newLength: newTrick.length,
    lastLength: lastTrickLength,
    newTrick: newTrick.map(c => ({ id: c.id, played_by: c.played_by })),
    gameStatus: gameState.value?.status
  })

  // Don't process tricks if game is over
  if (gameState.value?.status && !['playing', 'Playing'].includes(gameState.value.status)) {
    console.log('Game not playing, skipping trick processing. Status:', gameState.value.status)
    return
  }

  // Check if trick was just completed (reaches 2 cards)
  if (newTrick.length === 2 && lastTrickLength < 2 && !showTrickResult.value && !isResolvingTrick.value) {
    // Create a unique identifier for this trick combination
    const trickId = newTrick.map(c => c.id).sort().join('-')
    
    // Check if we've already processed this exact trick combination
    if (processedTrickIds.has(trickId)) {
      console.log('Trick already processed, skipping:', trickId)
      lastTrickLength = 2 // Update length to prevent further processing
      return
    }
    
    console.log('🎉 TRICK COMPLETED: Starting trick winner animation sequence')
    
    // Mark this trick as processed
    processedTrickIds.add(trickId)

    // Store the completed trick for display
    lastCompletedTrick.value = [...newTrick]

    // Determine winner using helper function
    const [card1, card2] = newTrick
    const winnerPlayerId = determineTrickWinner(card1, card2)

    console.log('🎉 TRICK WINNER DETERMINED:', winnerPlayerId, 'from cards:', {
      card1: { id: card1.id, suit: card1.suit, value: card1.value, played_by: card1.played_by },
      card2: { id: card2.id, suit: card2.suit, value: card2.value, played_by: card2.played_by },
      trump: gameState.value.trump_suit
    })

    trickWinner.value = winnerPlayerId
    showTrickResult.value = true
    console.log('🎉 TRICK WINNER ANIMATION: Starting animation for', getPlayerDisplayName(winnerPlayerId))

    // Ensure the animation is shown for at least 4 seconds before clearing
    const animationDuration = 4000
    const animationStartTime = Date.now()
    
    setTimeout(() => {
      const actualDuration = Date.now() - animationStartTime
      console.log('🎉 TRICK WINNER ANIMATION: Clearing result display after', actualDuration, 'ms')
      showTrickResult.value = false
      trickWinner.value = null
      lastCompletedTrick.value = []
    }, animationDuration)

    // Call backend to resolve trick after a brief delay (but allow animation to complete)
    setTimeout(async () => {
      // Try manual resolution but don't block if it fails - auto-resolve will handle it
      try {
        console.log('🎯 Attempting manual resolve_trick (with auto-resolve backup)')
        
        if (isResolvingTrick.value) {
          console.log('Trick resolution already in progress, skipping')
          return
        }

        isResolvingTrick.value = true

        let response
        if (gameState.value?.is_anonymous) {
          response = await axios.post(`/api/games/anonymous/${currentGameId.value}/move`, {
            action: 'resolve_trick'
          })
        } else {
          response = await axios.post(`/api/games/${currentGameId.value}/move`, {
            action: 'resolve_trick'
          }, {
            headers: { Authorization: `Bearer ${userStore.token}` }
          })
        }

        console.log('Manual resolve_trick successful:', response.data.player1_points, response.data.player2_points)
        
        // Set cooldown to prevent polling conflicts
        lastTrickResolutionTime.value = Date.now()

        // Update game state with resolved data - this should include the newly dealt cards
        console.log('🎯 UPDATING GAME STATE AFTER RESOLVE_TRICK:', {
          before_player1_hand_size: gameState.value?.player1_hand?.length || 0,
          before_player2_hand_size: gameState.value?.player2_hand?.length || 0,
          after_player1_hand_size: response.data.player1_hand?.length || 0,
          after_player2_hand_size: response.data.player2_hand?.length || 0,
          deck_size: response.data.deck?.length || 0,
          current_player: response.data.current_player,
          source: 'RESOLVE_TRICK_UPDATE'
        })
        gameState.value = response.data

      } catch (error) {
        console.log('Manual resolve_trick failed, auto-resolve will handle it:', error.message)
        // Don't set error state - let auto-resolve handle it
      } finally {
        // Clear the resolution flag after trick resolution completes
        isResolvingTrick.value = false
      }
    }, 500) // Call backend after 0.5 seconds (faster to avoid race conditions)

    // Update last length to prevent re-triggering
    lastTrickLength = 2
    return
  }

  // Check if trick was just cleared by backend
  if (oldTrick && oldTrick.length > 0 && newTrick.length === 0) {
    console.log('Backend cleared trick - checking if we missed the completed trick animation')
    
    // If the trick went from 1 to 0, we missed the opponent's card and the trick completion
    // We need to show the trick result based on the score change
    if (oldTrick.length === 1 && !showTrickResult.value && !isResolvingTrick.value) {
      console.log('🎉 MISSED TRICK COMPLETION: Trick went from 1 to 0, showing result retroactively')
      
      // Check for score changes to determine the winner
      const currentP1Score = gameState.value?.player1_points || 0
      const currentP2Score = gameState.value?.player2_points || 0
      const previousP1Score = lastPlayerPoints || 0
      const previousP2Score = lastOpponentPoints || 0
      
      console.log('Score analysis:', {
        currentP1Score,
        currentP2Score, 
        previousP1Score,
        previousP2Score,
        p1ScoreIncrease: currentP1Score - previousP1Score,
        p2ScoreIncrease: currentP2Score - previousP2Score
      })
      
      // Determine winner based on who got points
      let retroactiveWinner = null
      if (currentP1Score > previousP1Score && currentP2Score === previousP2Score) {
        retroactiveWinner = gameState.value.player1_id
      } else if (currentP2Score > previousP2Score && currentP1Score === previousP1Score) {
        retroactiveWinner = gameState.value.player2_id
      } else if (currentP1Score > previousP1Score && currentP2Score > previousP2Score) {
        // Both got points, determine who got more (shouldn't happen in normal bisca)
        retroactiveWinner = currentP1Score - previousP1Score >= currentP2Score - previousP2Score ? 
                           gameState.value.player1_id : gameState.value.player2_id
      }
      
      if (retroactiveWinner) {
        console.log('🎉 RETROACTIVE TRICK WINNER:', getPlayerDisplayName(retroactiveWinner))
        trickWinner.value = retroactiveWinner
        showTrickResult.value = true
        lastCompletedTrick.value = [...oldTrick] // Use the partial trick we saw
        
        // Show the result for a shorter time since it's retroactive
        setTimeout(() => {
          console.log('🎉 RETROACTIVE TRICK ANIMATION: Clearing result display')
          showTrickResult.value = false
          trickWinner.value = null
          lastCompletedTrick.value = []
        }, 2500)
      }
    }
    
    lastTrickLength = 0
    // Don't clear processedTrickIds here - we want to keep them to prevent re-processing the same trick
    return
  }

  // Handle card play animations for opponents
  if (newTrick.length > lastTrickLength && newTrick.length > 0) {
    const lastPlayedCard = newTrick[newTrick.length - 1]
    console.log('New card played by:', lastPlayedCard.played_by, 'Card:', lastPlayedCard.id)

    // Check if this card was played by an opponent (bot or other player)
    const isOpponentCard = (props.gameMode === 'single' && lastPlayedCard.played_by === 'bot') ||
                           (props.gameMode === 'multiplayer' && lastPlayedCard.played_by !== userId.value && lastPlayedCard.played_by !== 'anonymous')

    if (isOpponentCard) {
      // Clear bot thinking and trigger opponent animation
      isBotThinking.value = false
      isBotAnimating.value = true
      console.log('Starting opponent animation for player:', lastPlayedCard.played_by)

      // Clear animation after delay
      setTimeout(() => {
        isBotAnimating.value = false
        console.log('Opponent animation ended')
      }, 1200)
    }
  }

  // Update the last known trick length
  lastTrickLength = newTrick.length

  // Reset states when trick is empty
  if (newTrick.length === 0 && !showTrickResult.value && !isResolvingTrick.value) {
    console.log('Trick cleared - resetting states')
    lastTrickLength = 0
    isBotThinking.value = false
    isBotAnimating.value = false
    isPlayerAnimating.value = false
    isBotMoveInProgress.value = false
  }
}, { deep: true })

// Watch for score changes to ensure immediate updates
watch(() => [gameState.value?.player1_points, gameState.value?.player2_points], ([newP1Points, newP2Points], [oldP1Points, oldP2Points]) => {
  if (newP1Points !== lastPlayerPoints || newP2Points !== lastOpponentPoints) {
    console.log('Scores updated:', {
      player1: newP1Points,
      player2: newP2Points,
      previousP1: lastPlayerPoints,
      previousP2: lastOpponentPoints,
      oldP1Points: oldP1Points,
      oldP2Points: oldP2Points
    })
    
    // Update the tracked scores
    lastPlayerPoints = newP1Points || 0
    lastOpponentPoints = newP2Points || 0
  }
}, { deep: true })

// Watch for current player changes to show bot thinking with delay
watch(() => gameState.value?.current_player, (newPlayer, oldPlayer) => {
  console.log('🤖 Current player changed:', {
    from: oldPlayer,
    to: newPlayer,
    gameMode: props.gameMode,
    status: gameState.value?.status,
    trickLength: gameState.value?.current_trick?.length || 0,
    showTrickResult: showTrickResult.value,
    isBotMoveInProgress: isBotMoveInProgress.value
  })

  if (props.gameMode === 'single' && gameState.value?.status === 'playing') {
    // Bot should play when: it's bot's turn, trick is not complete, not showing results, and no move in progress
    const trickLength = gameState.value?.current_trick?.length || 0
    
    if (newPlayer === 'bot') {
      console.log('🤖 Bot detected as current player. Checking conditions:', {
        trickLength: trickLength,
        showTrickResult: showTrickResult.value,
        isBotMoveInProgress: isBotMoveInProgress.value,
        isManualBotTrigger: isManualBotTrigger.value,
        willTrigger: trickLength < 2 && !showTrickResult.value && !isBotMoveInProgress.value && !isManualBotTrigger.value
      })
      
      // If trick result is showing but bot won, queue the bot move for after the result is cleared
      if (showTrickResult.value) {
        console.log('🤖 Bot turn detected but trick result is showing. Queueing bot move for after result clears.')
        // Queue the bot move to trigger after trick result is cleared
        setTimeout(() => {
          if (gameState.value?.current_player === 'bot' && 
              !showTrickResult.value && 
              !isBotMoveInProgress.value &&
              (gameState.value?.current_trick?.length || 0) < 2) {
            console.log('🤖 Trick result cleared, now triggering queued bot move')
            isBotThinking.value = true
            
            const delay = Math.random() * 2000 + 2000 // 2-4 seconds
            setTimeout(async () => {
              if (isBotThinking.value &&
                  gameState.value?.current_player === 'bot' &&
                  !showTrickResult.value &&
                  !isBotMoveInProgress.value &&
                  (gameState.value?.current_trick?.length || 0) < 2) {
                console.log('🤖 Triggering queued bot move after trick result cleared')
                await triggerBotMove()
              } else {
                console.log('🤖 Conditions changed while waiting for queued bot move')
                isBotThinking.value = false
              }
            }, delay)
          }
        }, 100) // Check shortly after
        return
      }
    }
    
    if (newPlayer === 'bot' &&
        trickLength < 2 &&
        !showTrickResult.value &&
        !isBotMoveInProgress.value &&
        !isManualBotTrigger.value) {  // Don't interfere with manual triggers
      console.log('🤖 Bot turn detected, starting thinking animation. Trick length:', trickLength)
      isBotThinking.value = true

      // Add delay before bot plays (2-4 seconds for realism)
      const delay = Math.random() * 2000 + 2000 // 2-4 seconds

      // Set up backup timeout that's longer than the delay + move time
      const backupTimeoutId = setTimeout(() => {
        if (isBotThinking.value && !isBotMoveInProgress.value && !isManualBotTrigger.value) {
          console.log('Forcing clear of bot thinking state (backup timeout)')
          isBotThinking.value = false
        }
      }, delay + 8000)  // Delay + 8 seconds backup timeout

      setTimeout(async () => {
        // Double check conditions before triggering bot move
        const currentTrickLength = gameState.value?.current_trick?.length || 0
        if (isBotThinking.value &&
            gameState.value?.current_player === 'bot' &&
            !showTrickResult.value &&
            !isBotMoveInProgress.value &&
            currentTrickLength < 2) {
          console.log('Conditions met, triggering bot move. Current trick length:', currentTrickLength)
          // Clear the backup timeout since we're executing the move
          clearTimeout(backupTimeoutId)
          // Trigger bot move manually
          await triggerBotMove()
        } else {
          console.log('Conditions not met for bot move:', {
            isBotThinking: isBotThinking.value,
            currentPlayer: gameState.value?.current_player,
            showTrickResult: showTrickResult.value,
            isBotMoveInProgress: isBotMoveInProgress.value,
            currentTrickLength: currentTrickLength
          })
          // Clear thinking state if conditions not met
          isBotThinking.value = false
          clearTimeout(backupTimeoutId)
        }
      }, delay)
    } else {
      // Log why bot was not triggered
      if (newPlayer === 'bot') {
        console.log('Bot not triggered because:', {
          trickLength: gameState.value?.current_trick?.length,
          showTrickResult: showTrickResult.value,
          isBotMoveInProgress: isBotMoveInProgress.value,
          isManualBotTrigger: isManualBotTrigger.value,
          status: gameState.value?.status
        })
      }
      isBotThinking.value = false
    }
  }
})

// Watch for trick result being cleared to trigger bot move if needed
watch(() => showTrickResult.value, (showing, wasShowing) => {
  if (!showing && wasShowing) {
    console.log('🎯 Trick result cleared. Checking if bot should move:', {
      currentPlayer: gameState.value?.current_player,
      trickLength: gameState.value?.current_trick?.length || 0,
      gameStatus: gameState.value?.status,
      isBotMoveInProgress: isBotMoveInProgress.value
    })
    
    // If bot is current player and trick is empty/incomplete, trigger bot move
    if (props.gameMode === 'single' && 
        gameState.value?.status === 'playing' &&
        gameState.value?.current_player === 'bot' &&
        !isBotMoveInProgress.value &&
        (gameState.value?.current_trick?.length || 0) < 2) {
      console.log('🤖 Bot should move after trick result cleared')
      isBotThinking.value = true
      
      const delay = Math.random() * 2000 + 2000 // 2-4 seconds
      setTimeout(async () => {
        if (isBotThinking.value &&
            gameState.value?.current_player === 'bot' &&
            !showTrickResult.value &&
            !isBotMoveInProgress.value &&
            (gameState.value?.current_trick?.length || 0) < 2) {
          console.log('🤖 Triggering bot move after trick result was cleared')
          await triggerBotMove()
        } else {
          console.log('🤖 Conditions changed while waiting to trigger bot after trick clear')
          isBotThinking.value = false
        }
      }, delay)
    }
  }
})

// Next Round Method - Show trick result before clearing
const nextRound = () => {
  if (!showTrickResult.value) {
    console.log('Cannot proceed to next round - trick result not shown yet')
    return
  }

  console.log('Clearing trick display for next round')

  // Clear the trick result display
  showTrickResult.value = false
  trickWinner.value = null
  lastCompletedTrick.value = []

  // Reset animation states
  isBotAnimating.value = false
  isPlayerAnimating.value = false
  isBotThinking.value = false
  lastPlayedCard.value = null
  animatingCardId.value = null

  console.log('Trick cleared, ready for next round')
}

// Watch for game status changes to trigger coin flip animation
watch(() => gameState.value?.status, (newStatus, oldStatus) => {
  if (props.gameMode === 'multiplayer' &&
      oldStatus === 'Pending' &&
      newStatus === 'Playing' &&
      gameState.value?.coin_flip_result) {

    // Show coin flip animation
    showCoinFlip.value = true
    isCoinFlipping.value = true

    // Animation sequence
    setTimeout(() => {
      isCoinFlipping.value = false
      coinFlipResult.value = gameState.value.coin_flip_result
    }, 2000) // 2 seconds of flipping

    // Hide coin flip after showing result
    setTimeout(() => {
      showCoinFlip.value = false
      coinFlipResult.value = null
    }, 4000) // Show result for 2 more seconds
  }
})

// Watch for trick_complete flag to auto-resolve incomplete tricks when one player has no cards
watch(() => gameState.value?.trick_complete, (isComplete) => {
  if (!isComplete || !gameState.value) return

  const trickLength = gameState.value.current_trick?.length || 0
  
  console.log('🎯 Trick marked as complete by backend:', {
    trickLength: trickLength,
    currentPlayer: gameState.value.current_player,
    isComplete: isComplete
  })

  // If trick is incomplete (1 card) but marked complete, auto-resolve it
  if (trickLength === 1 && !showTrickResult.value && !isResolvingTrick.value) {
    console.log('🎯 Auto-resolving incomplete trick (opponent has no cards)')
    
    // The single card in the trick automatically wins
    showTrickResult.value = true
    trickWinner.value = gameState.value.current_trick[0].played_by === 'bot' ? 'bot' : gameState.value.player1_id
    
    console.log('🎉 TRICK WINNER (auto-resolved):', trickWinner.value)
    
    // Clear the display after 4 seconds and resolve
    setTimeout(() => {
      showTrickResult.value = false
      
      setTimeout(async () => {
        try {
          console.log('🎯 Resolving auto-completed trick')
          
          let response
          if (gameState.value?.is_anonymous) {
            response = await axios.post(`/api/games/anonymous/${currentGameId.value}/move`, {
              action: 'resolve_trick'
            })
          } else {
            response = await axios.post(`/api/games/${currentGameId.value}/move`, {
              action: 'resolve_trick'
            }, {
              headers: { Authorization: `Bearer ${userStore.token}` }
            })
          }
          
          gameState.value = response.data
          console.log('🎯 Auto-completed trick resolved successfully')
        } catch (error) {
          console.error('Error resolving auto-completed trick:', error)
        }
      }, 500)
    }, 4000)
  }
})

// Watch for authentication changes
watch(() => userStore.token, (newToken) => {
  if (!newToken) {
    resetGame()
  }
})

// Watch for game state to check if current player has no cards (game should end)
watch(() => gameState.value, (newGameState) => {
  if (!newGameState || newGameState.status !== 'playing') return

  const playerHasCards = newGameState.player1_hand && newGameState.player1_hand.length > 0
  const botHasCards = newGameState.player2_hand && newGameState.player2_hand.length > 0

  // Game should only end when both players have no cards AND deck is empty
  if ((!playerHasCards && !botHasCards) && !showTrickResult.value) {
    const deckSize = newGameState.deck?.length || 0
    console.log('Game should end - both players have no cards:', {
      playerCards: newGameState.player1_hand?.length || 0,
      botCards: newGameState.player2_hand?.length || 0,
      deckSize: deckSize,
      currentPlayer: newGameState.current_player,
      trickLength: newGameState.current_trick?.length || 0
    })

    // If there's no active trick and the deck is empty, the game should have ended already
    // This is a safeguard in case the backend didn't catch it
    if (newGameState.current_trick?.length === 0 && deckSize === 0) {
      console.log('No active trick, no deck cards, and both players have no cards - game should have ended')
      // Refresh the game state to get the updated status
      setTimeout(() => {
        loadGameState()
      }, 500)
    }
  }
}, { deep: true })

// Polling function specifically for checking if the other player has joined
const startPlayerJoinPolling = () => {
  if (playerJoinPollInterval.value) {
    clearInterval(playerJoinPollInterval.value)
  }
  
  console.log('🔄 Starting player join polling...')
  playerJoinPollInterval.value = setInterval(async () => {
    if (!currentGameId.value || !gameState.value) {
      console.log('🔄 No game ID or state, stopping player join polling')
      stopPlayerJoinPolling()
      return
    }

    // Only poll if game is still pending
    if (gameState.value.status !== 'Pending') {
      console.log('🔄 Game no longer pending, stopping player join polling. Status:', gameState.value.status)
      stopPlayerJoinPolling()
      return
    }

    console.log('🔄 Checking if other player has joined or resigned...')
    try {
      await loadGameState()
      
      // Check if game has ended (someone resigned before game started)
      if (gameState.value && gameState.value.status === 'Ended') {
        console.log('🏳️ Game ended during waiting (player resigned):', {
          resigned_by: gameState.value.resigned_by,
          winner_id: gameState.value.winner_user_id,
          current_user_id: userStore.user?.id
        })
        stopPlayerJoinPolling()
        
        // Check if current user was the one who resigned
        if (gameState.value.resigned_by && gameState.value.resigned_by === userStore.user?.id) {
          console.log('🏳️ Current user resigned during waiting')
        } else {
          console.log('🏳️ Other player resigned before game started')
        }
        return
      }
      
      // If status changed from Pending to something else, the other player joined
      if (gameState.value && gameState.value.status !== 'Pending') {
        console.log('🎉 Other player has joined! Status changed to:', gameState.value.status)
        stopPlayerJoinPolling()
        
        // Start main polling if game is now playing
        if (gameState.value.status === 'playing' || gameState.value.status === 'Playing') {
          console.log('🔄 Game is now playing, main polling will take over')
        }
      } else {
        console.log('⏳ Still waiting for other player to join...')
      }
    } catch (error) {
      console.error('❌ Error checking player join/resign status:', error)
    }
  }, 5000) // Check every 5 seconds (slower than main game polling)
}

const stopPlayerJoinPolling = () => {
  if (playerJoinPollInterval.value) {
    console.log('🛑 Stopping player join polling')
    clearInterval(playerJoinPollInterval.value)
    playerJoinPollInterval.value = null
  }
}
</script>

<style scoped>
.game-container {
  min-height: 100vh;
  background: linear-gradient(135deg, #0f4c3a 0%, #1a6b4f 50%, #2d8659 100%);
  padding: 20px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.game-header {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 15px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  backdrop-filter: blur(10px);
}

.game-header h1 {
  color: #0f4c3a;
  margin: 0;
  text-align: center;
  font-size: 2.5em;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
}

.game-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 15px;
  flex-wrap: wrap;
  gap: 15px;
}

.game-info p {
  margin: 0;
  font-weight: 600;
  color: #2d8659;
  background: rgba(45, 134, 89, 0.1);
  padding: 8px 15px;
  border-radius: 20px;
  border: 2px solid rgba(45, 134, 89, 0.3);
}

.game-setup {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 15px;
  padding: 30px;
  text-align: center;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  backdrop-filter: blur(10px);
}

.game-setup h2 {
  color: #0f4c3a;
  margin-bottom: 25px;
  font-size: 2em;
}

.game-options {
  display: flex;
  justify-content: center;
  gap: 20px;
  margin-bottom: 25px;
}

.game-options label {
  background: linear-gradient(135deg, #2d8659, #1a6b4f);
  color: white;
  padding: 12px 20px;
  border-radius: 25px;
  cursor: pointer;
  transition: all 0.3s ease;
  border: 2px solid transparent;
  font-weight: 600;
}

.game-options label:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(45, 134, 89, 0.4);
}

.game-options input[type="radio"] {
  margin-right: 8px;
}

.game-board {
  position: relative;
  display: grid;
  grid-template-areas:
    "bot-hand bot-hand bot-hand"
    "info trick right-panel"
    "trump trick right-panel"
    "hand hand hand";
  grid-template-columns: 220px 1fr 220px;
  grid-template-rows: auto auto 1fr auto;
  gap: 20px;
  min-height: 700px;
  max-width: 1400px;
  margin: 0 auto;
}

.game-background {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background:
    radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
    radial-gradient(circle at 70% 70%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
  border-radius: 20px;
  pointer-events: none;
}

/* Bot Hand Styles */
.bot-hand {
  grid-area: bot-hand;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 15px;
  padding: 20px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  backdrop-filter: blur(10px);
  text-align: center;
}

.bot-cards {
  display: flex;
  justify-content: center;
  gap: 10px;
  margin-top: 10px;
}

.bot-card-back {
  width: 70px;
  height: 105px;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
  background: linear-gradient(135deg, #8b4513, #a0522d);
  border: 2px solid #654321;
  transition: all 0.3s ease;
  transform-origin: center bottom;
}

.bot-card-back.card-shake {
  animation: cardShake 0.5s infinite alternate;
}

.bot-card-back.card-draw-animation {
  animation: cardDrawFromDeck 1s ease-out;
}

@keyframes cardShake {
  0% { transform: rotate(-2deg) translateY(0); }
  100% { transform: rotate(2deg) translateY(-2px); }
}

@keyframes cardDrawFromDeck {
  0% {
    transform: translateY(50px) scale(0.8) rotate(0deg);
    opacity: 0;
  }
  50% {
    transform: translateY(25px) scale(0.9) rotate(5deg);
    opacity: 0.7;
  }
  100% {
    transform: translateY(0) scale(1) rotate(0deg);
    opacity: 1;
  }
}

.bot-card-back img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Right Panel Styles */
.right-panel {
  grid-area: right-panel;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 15px;
  padding: 20px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  backdrop-filter: blur(10px);
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.game-info-panel {
  grid-area: info;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 15px;
  padding: 15px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  backdrop-filter: blur(10px);
}

.score-display {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 30px;
}

.player-score, .opponent-score {
  text-align: center;
  padding: 15px 25px;
  border-radius: 15px;
  background: linear-gradient(135deg, #2d8659, #1a6b4f);
  color: white;
  box-shadow: 0 4px 15px rgba(45, 134, 89, 0.3);
}

.player-score h4, .opponent-score h4 {
  margin: 0 0 5px 0;
  font-size: 1em;
  opacity: 0.9;
}

.points {
  font-size: 2em;
  font-weight: bold;
  display: block;
}

.vs {
  font-size: 1.5em;
  font-weight: bold;
  color: #0f4c3a;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
}

.game-rules {
  text-align: center;
  margin-top: 15px;
}

.game-rules small {
  display: inline-block;
  color: #666;
  font-weight: 500;
  margin: 2px 5px;
  padding: 4px 8px;
  background: rgba(45, 134, 89, 0.1);
  border-radius: 10px;
}

.trump-section {
  grid-area: trump;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.trump-container {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 15px;
  padding: 20px;
  text-align: center;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  backdrop-filter: blur(10px);
}

.trump-label {
  font-size: 1.2em;
  font-weight: bold;
  color: #0f4c3a;
  margin-bottom: 10px;
}

.trump-card {
  width: 80px;
  height: 120px;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
  margin: 0 auto 10px;
  background: white;
}

.trump-card img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.trump-suit {
  font-weight: bold;
  color: #2d8659;
  text-transform: capitalize;
}

.current-trick {
  grid-area: trick;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.trick-area {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 20px;
  padding: 30px;
  min-height: 200px;
  min-width: 450px !important;
  width: auto !important;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  backdrop-filter: blur(10px);
  border: 3px dashed rgba(45, 134, 89, 0.3);
}

.empty-trick {
  text-align: center;
  color: #666;
  font-size: 1.2em;
  font-weight: 600;
}

.trick-cards {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 150px;
  min-width: 280px !important;
  width: 100%;
}

.played-card {
  text-align: center;
  transition: all 0.4s ease;
  position: absolute;
  transform-origin: center center;
  min-width: 90px;
  min-height: 135px;
}

/* Ensure only two cards are ever displayed with proper stacking */
.played-card.stacked-first {
  transform: translateX(-40px) translateY(0px) rotate(-5deg);
  z-index: 1;
}

.played-card.stacked-second {
  transform: translateX(40px) translateY(0px) rotate(5deg);
  z-index: 2;
}

.played-card:hover {
  z-index: 10 !important;
  transform: scale(1.1) !important;
  transition: all 0.2s ease;
}

/* Base animation for all cards entering the trick area */
.played-card.newest-card {
  animation: cardSlideToCenter 0.8s ease-out;
}

/* Specific animations for bot cards */
.played-card.bot-animation {
  animation: botCardDrop 1.0s ease-out !important;
}

/* Specific animations for player cards */
.played-card.player-animation {
  animation: cardSlideToCenter 0.8s ease-out !important;
}

/* Remove old transform rules and ensure clear stacking */

.trick-cards .played-card img,
.trick-cards-result .played-card img,
.played-card img {
  width: 90px !important;
  height: 135px !important;
  border-radius: 10px;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
  border: 3px solid #fff;
  object-fit: cover;
  flex-shrink: 0;
}

.player-label {
  margin-top: 8px;
  font-weight: bold;
  color: #0f4c3a;
  font-size: 0.9em;
}

.game-controls {
  grid-area: controls;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.resign-btn {
  background: linear-gradient(135deg, #e74c3c, #c0392b);
  border: none;
  color: white;
  padding: 12px 25px;
  border-radius: 25px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
}

.resign-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
}

.next-round-btn {
  background: linear-gradient(135deg, #3498db, #2980b9);
  border: none;
  color: white;
  padding: 12px 25px;
  border-radius: 25px;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
  margin-top: 10px;
}

.next-round-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
  background: linear-gradient(135deg, #5dade2, #3498db);
}

.next-round-btn:active {
  transform: translateY(0);
  box-shadow: 0 3px 10px rgba(52, 152, 219, 0.4);
}

.player-hand {
  grid-area: hand;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 15px;
  padding: 20px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  backdrop-filter: blur(10px);
}

.hand-label {
  text-align: center;
  font-size: 1.3em;
  font-weight: bold;
  color: #0f4c3a;
  margin-bottom: 15px;
}

.hand-cards {
  display: flex;
  justify-content: center;
  gap: 10px;
  flex-wrap: wrap;
}

.card {
  width: 70px;
  height: 105px;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
  background: white;
  border: 2px solid #fff;
  transition: all 0.3s ease;
  cursor: pointer;
}

.card img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.card.playable:hover {
  transform: translateY(-15px) scale(1.05);
  box-shadow: 0 8px 25px rgba(45, 134, 89, 0.4);
  border-color: #2d8659;
}

.card.disabled {
  opacity: 0.6;
  cursor: not-allowed;
  filter: grayscale(50%);
}

.card.disabled:hover {
  transform: none;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.game-over-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(5px);
}

.game-over-content {
  background: white;
  border-radius: 20px;
  padding: 40px;
  text-align: center;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  max-width: 400px;
  animation: bounceIn 0.5s ease;
}

.game-over-content h2 {
  color: #0f4c3a;
  margin-bottom: 20px;
  font-size: 2.2em;
}

.result {
  margin-bottom: 20px;
  font-size: 1.5em;
}

.result .win {
  color: #27ae60;
  font-weight: bold;
}

.result .lose {
  color: #e74c3c;
  font-weight: bold;
}

.result .draw {
  color: #f39c12;
  font-weight: bold;
}

.final-scores {
  background: #f8f9fa;
  border-radius: 10px;
  padding: 15px;
  margin-bottom: 25px;
  color: #2c3e50;
  font-weight: 600;
}

.final-scores div {
  margin: 5px 0;
}

.new-game-btn {
  background: linear-gradient(135deg, #2d8659, #1a6b4f);
  border: none;
  color: white;
  padding: 15px 30px;
  border-radius: 25px;
  font-weight: bold;
  font-size: 1.1em;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(45, 134, 89, 0.3);
}

.new-game-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(45, 134, 89, 0.4);
}

.btn {
  background: linear-gradient(135deg, #2d8659, #1a6b4f);
  border: none;
  color: white;
  padding: 12px 25px;
  border-radius: 25px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(45, 134, 89, 0.3);
  text-decoration: none;
  display: inline-block;
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(45, 134, 89, 0.4);
}

.btn.btn-primary {
  background: linear-gradient(135deg, #3498db, #2980b9);
  box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
}

.btn.btn-primary:hover {
  box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
}

.btn.btn-secondary {
  background: linear-gradient(135deg, #95a5a6, #7f8c8d);
  box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
}

.btn.btn-secondary:hover {
  box-shadow: 0 6px 20px rgba(149, 165, 166, 0.4);
}

/* Trick Result Display */
.trick-result {
  text-align: center;
  animation: slideInResult 0.5s ease-out;
}

.trick-result h3 {
  color: #0f4c3a;
  margin-bottom: 20px;
  font-size: 1.5em;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
}

.trick-cards-result {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 30px;
  margin-bottom: 15px;
  min-width: 400px !important;
  width: 100%;
  flex-wrap: nowrap;
}

.result-card {
  position: static !important;
  transition: all 0.3s ease;
  transform: none !important;
  min-width: 90px;
  min-height: 135px;
  text-align: center;
}

.result-card.winner-card {
  transform: scale(1.1) !important;
  box-shadow: 0 0 20px rgba(45, 134, 89, 0.6);
  border: 3px solid #2d8659;
  border-radius: 10px;
}

.result-card.winner-card::after {
  content: "🏆";
  position: absolute;
  top: -10px;
  right: -10px;
  background: #2d8659;
  color: white;
  border-radius: 50%;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

.result-card img {
  width: 90px !important;
  height: 135px !important;
  border-radius: 10px;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
  border: 3px solid #fff;
  object-fit: cover;
}

@keyframes slideInResult {
  0% {
    opacity: 0;
    transform: translateY(-30px) scale(0.9);
  }
  100% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@keyframes slideIn {
  from {
    transform: translateY(-50px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

@keyframes bounceIn {
  0% {
    transform: scale(0.3);
    opacity: 0;
  }
  50% {
    transform: scale(1.05);
  }
  70% {
    transform: scale(0.9);
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

/* Responsive Design */
@media (max-width: 768px) {
  .game-board {
    grid-template-areas:
      "bot-hand"
      "info"
      "trick"
      "trump"
      "right-panel"
      "hand";
    grid-template-columns: 1fr;
    grid-template-rows: auto auto auto auto auto auto;
    gap: 15px;
    min-height: auto;
  }

  .right-panel {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }

  .bot-cards {
    gap: 8px;
  }

  .bot-card-back {
    width: 50px;
    height: 75px;
  }

  .hand-cards {
    gap: 8px;
  }

  .card {
    width: 60px;
    height: 90px;
  }

  .trick-cards {
    flex-direction: column;
    gap: 15px;
  }

  .score-display {
    flex-direction: column;
    gap: 15px;
  }
}

@keyframes cardPlayAnimation {
  0% {
    transform: translateY(0) scale(1);
  }
  50% {
    transform: translateY(-40px) scale(1.15);
    z-index: 100;
  }
  100% {
    transform: translateY(-80px) scale(0.9);
    opacity: 0.3;
  }
}

@keyframes cardSlideToCenter {
  0% {
    opacity: 0;
    transform: translateY(120px) scale(0.7) rotate(0deg);
  }
  50% {
    opacity: 0.8;
    transform: translateY(60px) scale(0.9) rotate(2deg);
  }
  100% {
    opacity: 1;
    transform: translateY(0) scale(1) rotate(0deg);
  }
}

@keyframes botCardDrop {
  0% {
    transform: translateY(-150px) scale(0.7) rotate(20deg);
    opacity: 0;
  }
  30% {
    transform: translateY(-50px) scale(0.85) rotate(10deg);
    opacity: 0.6;
  }
  70% {
    transform: translateY(-10px) scale(0.95) rotate(3deg);
    opacity: 0.9;
  }
  100% {
    transform: translateY(0) scale(1) rotate(0deg);
    opacity: 1;
  }
}

@keyframes cardGlow {
  0%, 100% {
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
  }
  50% {
    box-shadow: 0 8px 30px rgba(45, 134, 89, 0.6);
  }
}

@keyframes thinking {
  0%, 50%, 100% {
    opacity: 1;
  }
  25%, 75% {
    opacity: 0.5;
  }
}

/* Animation Classes */
.card.animating {
  pointer-events: none;
}

.card.play-animation {
  animation: cardPlayAnimation 0.6s ease-out;
  z-index: 50;
}

.bot-thinking {
  animation: thinking 1.5s infinite;
  color: #2d8659;
  font-weight: bold;
  font-size: 1.1em;
}

.played-card {
  animation: cardSlideToCenter 0.8s ease-out;
}

.played-card.bot-animation {
  animation: botCardDrop 0.8s ease-out;
}

.played-card.player-animation {
  animation: cardSlideToCenter 0.8s ease-out;
}

.card.playable:hover {
  animation: cardGlow 1s infinite;
}

/* Enhanced hover effects */
.card.playable:hover {
  transform: translateY(-15px) scale(1.05);
  box-shadow: 0 8px 25px rgba(45, 134, 89, 0.4);
  border-color: #2d8659;
  transition: all 0.3s ease;
}

.trick-cards .played-card {
  transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.trick-cards .played-card:hover {
  transform: scale(1.1) !important;
  z-index: 10;
}

@media (max-width: 480px) {
  .card {
    width: 50px;
    height: 75px;
  }

  .trump-card {
    width: 60px;
    height: 90px;
  }

  .played-card img {
    width: 75px;
    height: 110px;
  }
}

/* Coin Flip Animation */
.coin-flip-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
  padding: 20px;
}

.coin-flip-text {
  font-size: 1.2rem;
  font-weight: bold;
  color: #333;
}

.coin {
  width: 80px;
  height: 80px;
  position: relative;
  transform-style: preserve-3d;
  transition: transform 0.1s ease-in-out;
}

.coin.flipping {
  animation: coinFlip 2s linear;
}

.coin.final-heads {
  transform: rotateY(0deg);
}

.coin.final-tails {
  transform: rotateY(180deg);
}

.coin-side {
  position: absolute;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  border: 3px solid #ffd700;
  background: linear-gradient(45deg, #ffd700, #ffed4e);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
  backface-visibility: hidden;
}

.coin-side.heads {
  transform: rotateY(0deg);
}

.coin-side.tails {
  transform: rotateY(180deg);
}

.coin-result {
  font-size: 1.1rem;
  font-weight: bold;
  color: #2563eb;
  animation: resultFade 0.5s ease-in-out;
}

@keyframes coinFlip {
  0% { transform: rotateY(0deg); }
  100% { transform: rotateY(1800deg); } /* 5 full rotations */
}

@keyframes resultFade {
  0% { opacity: 0; transform: translateY(10px); }
  100% { opacity: 1; transform: translateY(0); }
}
</style>
