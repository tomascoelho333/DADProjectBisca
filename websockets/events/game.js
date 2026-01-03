import { getUser } from "../state/connection.js"
import { createGame, getGames, joinGame, flipCard, clearFlippedCard} from "../state/game.js"
import { server } from "../server.js"

export const handleGameEvents = (io, socket) => {
    socket.on("create-game", (difficulty) => {
        const user = getUser(socket.id)
        
        // Prevent admins from creating games
        if (user.type === 'A') {
            socket.emit("error", { message: "Administrators cannot participate in games." })
            console.log(`[Game] Admin ${user.name} attempted to create a game (denied)`)
            return
        }
        
        const game = createGame(difficulty, user)
        socket.join(`game-${game.id}`)
        console.log(`[Game] ${user.name} created a new game - ID: ${game.id}`)
        io.emit("games", getGames())
    })
    
    socket.on("get-games", () => {
        socket.emit("games", getGames())
    })
    
    socket.on("join-game", (gameID, userID) => {
        const user = getUser(socket.id)
        
        // Prevent admins from joining games
        if (user.type === 'A') {
            socket.emit("error", { message: "Administrators cannot participate in games." })
            console.log(`[Game] Admin ${user.name} attempted to join game ${gameID} (denied)`)
            return
        }
        
        joinGame(gameID, userID)
        socket.join(`game-${gameID}`)
        console.log(`[Game] User ${userID} joined game ${gameID}`)
        io.emit("games", getGames())
    })
    
    socket.on("flip-card", (gameID, card) => {
        console.log('flip-card event received:', gameID, card)
        const game = flipCard(gameID, card)
        console.log('new game:', game)
        io.to(`game-${gameID}`).emit("game-change", game)
    })    
}

export const triggerFlipDelay = (game) => {
	game = clearFlippedCard(game)
	server.io.to(`game-${game.id}`).emit("game-change", game)
}
