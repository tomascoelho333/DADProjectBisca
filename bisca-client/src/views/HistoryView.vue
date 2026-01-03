<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useRouter} from 'vue-router'
import { Input } from '@/components/ui/input'
import { useUserStore } from '@/stores/user'

import { ArrowLeft, Loader2, Trophy, Calendar, Clock, Eye } from 'lucide-vue-next'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

const history = ref([])
const currentPage = ref(1)
const lastPage = ref(1)
const searchQuery = ref('')
const userStore = useUserStore() // Initialize store

// Variables for the details modal
const isDetailsOpen = ref(false)
const selectedGame = ref(null)
const isLoadingDetails = ref(false)

const loadHistory = async (page = 1) => {
  try {
    const url = `/api/history?page=${page}` + (searchQuery.value ? `&player=${searchQuery.value}` : '')
    const res = await axios.get(url)
    history.value = res.data.data
    currentPage.value = res.data.current_page
    lastPage.value = res.data.last_page
  } catch (e) {
    console.error("Error loading history", e)
  }
}

// Function to fetch and open game details
const openGameDetails = async (gameId) => {
  isDetailsOpen.value = true
  isLoadingDetails.value = true
  selectedGame.value = null
  
  try {
    const res = await axios.get(`/api/history/${gameId}`)
    selectedGame.value = res.data
  } catch (e) {
    console.error("Error fetching details", e)
    isDetailsOpen.value = false
  } finally {
    isLoadingDetails.value = false
  }
}

// duration formatter for the modal
const formatDuration = (seconds) => {
  if (!seconds) return '-'
  const min = Math.floor(seconds / 60)
  const sec = seconds % 60
  return `${min}m ${sec}s`
}

const router = useRouter()

onMounted(() => loadHistory())

const formatDate = (dateString) => {
  if (!dateString) return '-'
  
  const date = new Date(dateString)
  
  // Formatting date according to the decent human being way (non american)
  return date.toLocaleString('pt-PT', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit', // 2025 to 25
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
  })
}
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-3xl font-bold mb-6">Game History</h1>

    <div class="mb-4">
    <Button variant="outline" size="sm" @click="router.push({ name: 'dashboard' })">
        <ArrowLeft class="w-4 h-4 mr-2" />
        Dashboard
    </Button>
    </div>

    <div class="mb-4 flex gap-2">
        <Input 
          v-model="searchQuery" 
          placeholder="Filter by nickname..." 
          class="max-w-sm"
          @keyup.enter="loadHistory(1)" 
        />
        <Button variant="outline" @click="loadHistory(1)">Search</Button>
    </div>

    <Card>
      <CardContent class="p-0">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 border-b">
            <tr>
              <th class="p-4">Date</th>
              <th class="p-4">Type</th>
              <th class="p-4">Players (points)</th>
              <th class="p-4">Winner</th>
              <th class="p-4">Status</th>
              <th class="p-4 text-center" v-if="userStore.user?.type === 'A' || history.length > 0">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="game in history" :key="game.id" class="border-b hover:bg-slate-50">
              <td class="p-4 text-slate-500">{{ formatDate(game.began_at) }}</td>
              <td class="p-4 font-bold">Bisca de {{ game.type }}</td>
              <td class="p-4">
                <div class="flex flex-col">
                  <span>{{ game.player1?.nickname || "Anonymous" }} ({{ game.player1_points || 0 }})</span>
                  <span class="text-slate-400 text-xs">VS</span>
                  <span>{{ game.player2?.nickname || 'Bot' }} ({{ game.player2_points || 0 }})</span>
                </div>
              </td>

              <td class="p-4">
                <Badge 
                  v-if="game.winner" 
                  variant="default" 
                  class="bg-green-600 hover:bg-green-700"
                >
                  {{ game.winner.nickname }}
                </Badge>
                <!-- game.is_draw isnt working for some reason... -->
                <Badge
                  v-else-if="(!game.winner && game.status === 'Ended')" 
                  variant="secondary" 
                  class="bg-yellow-500 hover:bg-yellow-600 text-white"
                >
                  Draw
                </Badge>

                <span v-else class="text-slate-400 text-xs">
                  -
                  </span>
              </td>

              <td class="p-4">
                <Badge variant="outline">{{ game.status }}</Badge>
              </td>
              <td class="p-4 text-center" v-if="userStore.user?.type === 'A' || history.length > 0">
                <Button variant="ghost" size="sm" @click="openGameDetails(game.id)">
                  <Eye class="w-4 h-4 text-slate-500" />
                </Button>
              </td>
            </tr>
             <tr v-if="history.length === 0">
                <td colspan="6" class="p-8 text-center text-gray-400">No games played yet.</td>
            </tr>
          </tbody>
        </table>

        <div class="p-4 flex justify-between items-center border-t" v-if="lastPage > 1">
          <button 
            @click="loadHistory(currentPage - 1)" 
            :disabled="currentPage === 1"
            class="px-4 py-2 text-sm border rounded disabled:opacity-50"
          >
            Previous
          </button>
          <span class="text-sm text-slate-500">Page {{ currentPage }} of {{ lastPage }}</span>
          <button 
            @click="loadHistory(currentPage + 1)" 
            :disabled="currentPage === lastPage"
            class="px-4 py-2 text-sm border rounded disabled:opacity-50"
          >
            Next
          </button>
        </div>
      </CardContent>
    </Card>

    <!-- Detailed records -->
    <Dialog :open="isDetailsOpen" @update:open="isDetailsOpen = $event">
      <DialogContent class="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>Game Details</DialogTitle>
          <DialogDescription>Full record for Game #{{ selectedGame?.id }}</DialogDescription>
        </DialogHeader>

        <div v-if="isLoadingDetails" class="flex justify-center py-8">
          <Loader2 class="w-8 h-8 animate-spin text-primary" />
        </div>

        <div v-else-if="selectedGame" class="grid gap-4 py-2">
          
          <div class="flex items-center justify-between bg-slate-100 p-3 rounded-lg">
            <span class="font-semibold text-slate-600">Winner</span>
            <div class="flex items-center gap-2">
              <Trophy class="w-4 h-4 text-yellow-500" />
              <span class="font-bold">{{ selectedGame.winner?.nickname || 'Draw' }}</span>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-1">
              <p class="text-xs text-muted-foreground">Start Time</p>
              <div class="flex items-center gap-2 text-sm">
                <Calendar class="w-3 h-3" /> {{ formatDate(selectedGame.began_at) }}
              </div>
            </div>
            <div class="space-y-1">
              <p class="text-xs text-muted-foreground">End Time</p>
              <div class="flex items-center gap-2 text-sm">
                <Calendar class="w-3 h-3" /> {{ formatDate(selectedGame.ended_at) }}
              </div>
            </div>
            <div class="space-y-1 col-span-2 border-t pt-2 mt-2">
              <p class="text-xs text-muted-foreground">Total Duration</p>
              <div class="flex items-center gap-2 font-mono text-sm">
                <Clock class="w-3 h-3" /> {{ formatDuration(selectedGame.total_time) }}
              </div>
            </div>
          </div>

          <div class="border rounded-md p-4 bg-slate-50 mt-2">
            <h4 class="mb-3 text-xs font-semibold uppercase text-slate-500">Score Breakdown</h4>
            <div class="flex justify-between items-center">
              <div class="text-center">
                <p class="text-sm font-medium">{{ selectedGame.player1?.nickname }}</p>
                <p class="text-2xl font-bold text-slate-800">{{ selectedGame.player1_points }}</p>
              </div>
              <div class="text-xs text-slate-400">VS</div>
              <div class="text-center">
                <p class="text-sm font-medium">{{ selectedGame.player2?.nickname || 'Bot' }}</p>
                <p class="text-2xl font-bold text-slate-800">{{ selectedGame.player2_points }}</p>
              </div>
            </div>
          </div>

        </div>
      </DialogContent>
    </Dialog>


  </div>
</template>