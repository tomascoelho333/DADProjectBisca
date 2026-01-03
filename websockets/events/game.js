import { getUser } from "../state/connection.js"
import { createGame, getGames, joinGame, playCard, getGame } from "../state/game.js"
import { server } from "../server.js"

export const handleGameEvents = (io, socket) => {
    socket.on("create-game", (gameType) => {
        const user = getUser(socket.id)
        if (!user) {
            socket.emit("error", "User not authenticated")
            return
        }
        
        const game = createGame(gameType, user)
        socket.join(`game-${game.id}`)
        console.log(`[Bisca] ${user.name} created a new ${gameType === '3' ? 'Bisca dos 3' : 'Bisca dos 9'} game - ID: ${game.id}`)
        
        // Send game state to creator
        socket.emit("game-created", game)
        io.emit("games-updated", getGames())
    })
    
    socket.on("get-games", () => {
        socket.emit("games", getGames())
    })
    
    socket.on("join-game", (gameID) => {
        const user = getUser(socket.id)
        if (!user) {
            socket.emit("error", "User not authenticated")
            return
        }
        
        const game = joinGame(gameID, user.id)
        if (!game) {
            socket.emit("error", "Cannot join game")
            return
        }
        
        socket.join(`game-${gameID}`)
        console.log(`[Bisca] ${user.name} joined game ${gameID}`)
        
        // Notify all players in the game
        io.to(`game-${gameID}`).emit("game-updated", game)
        io.emit("games-updated", getGames())
    })
    
    socket.on("play-card", (gameID, cardId) => {
        const user = getUser(socket.id)
        if (!user) {
            socket.emit("error", "User not authenticated")
            return
        }
        
        console.log(`[Bisca] ${user.name} plays card ${cardId} in game ${gameID}`)
        const result = playCard(gameID, user.id, cardId)
        
        if (!result.success) {
            socket.emit("error", result.message)
            return
        }
        
        // Notify all players in the game
        io.to(`game-${gameID}`).emit("game-updated", result.game)
        
        // If game is finished, update games list
        if (result.game.status === 'finished') {
            io.emit("games-updated", getGames())
        }
    })
    
    socket.on("get-game-state", (gameID) => {
        const user = getUser(socket.id)
        if (!user) {
            socket.emit("error", "User not authenticated")
            return
        }
        
        const game = getGame(gameID)
        if (!game) {
            socket.emit("error", "Game not found")
            return
        }
        
        // Check if user is part of this game
        if (game.player1 !== user.id && game.player2 !== user.id) {
            socket.emit("error", "Not authorized to view this game")
            return
        }
        
        socket.emit("game-state", game)
    })
    
    socket.on("resign-game", (gameID) => {
        const user = getUser(socket.id)
        if (!user) {
            socket.emit("error", "User not authenticated")
            return
        }
        
        const game = getGame(gameID)
        if (!game) {
            socket.emit("error", "Game not found")
            return
        }
        
        // Check if user is part of this game
        if (game.player1 !== user.id && game.player2 !== user.id) {
            socket.emit("error", "Not authorized to resign from this game")
            return
        }
        
        // Set opponent as winner
        game.winner = game.player1 === user.id ? game.player2 : game.player1
        game.status = 'finished'
        game.endedAt = new Date()
        
        console.log(`[Bisca] ${user.name} resigned from game ${gameID}`)
        
        // Notify all players in the game
        io.to(`game-${gameID}`).emit("game-updated", game)
        io.emit("games-updated", getGames())
    })

    // Handle user disconnection from games
    socket.on("disconnect", () => {
        const user = getUser(socket.id)
        if (user) {
            console.log(`[Bisca] User ${user.name} disconnected`)
            // Here you could handle game abandonment logic if needed
        }
    })
}

export const triggerGameUpdate = (game) => {
	server.io.to(`game-${game.id}`).emit("game-updated", game)
}
