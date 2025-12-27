<script setup>
import { useRouter } from 'vue-router'
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useUserStore } from '@/stores/user'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  Title,
  PointElement,
  LineElement
} from 'chart.js'
import { Pie, Bar, Line} from 'vue-chartjs'
import { Loader2, Users, Gamepad2, Timer, Activity, TrendingUp, CreditCard, Euro, ArrowLeft } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

const router = useRouter()

ChartJS.register(ArcElement, Tooltip, Legend, BarElement, CategoryScale, LinearScale, Title, PointElement, LineElement)

const userStore = useUserStore()
const isLoading = ref(true)

// Public Data
const publicStats = ref({
  total_players: 0,
  total_games: 0,
  total_playing: 0,
  avg_game_duration: 0
})

// Admin Data
const adminStats = ref(null)
const pieChartData = ref({ labels: [], datasets: [] }) // Game Types
const barChartData = ref({ labels: [], datasets: [] }) // Game Status
const paymentChartData = ref({ labels: [], datasets: [] }) // Payment Methods
const lineChartData = ref({ labels: [], datasets: [] })

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom' } }
}

const isAdmin = computed(() => userStore.user?.type === 'A')

onMounted(async () => {
  isLoading.value = true
  try {
    const resPublic = await axios.get('/api/statistics')
    publicStats.value = resPublic.data

    if (isAdmin.value) {
      const resAdmin = await axios.get('/api/statistics/advanced')
      adminStats.value = resAdmin.data
      processCharts(resAdmin.data)
    }
  } catch (error) {
    console.error('Error loading statistics:', error)
  } finally {
    isLoading.value = false
  }
})

const processCharts = (data) => {
  // Bisca 3 vs 9 (Pie)
  if (data.games_by_type) {
    pieChartData.value = {
      labels: data.games_by_type.map(item => `Bisca of ${item.type}`),
      datasets: [{
        backgroundColor: ['#3b82f6', '#f97316'],
        data: data.games_by_type.map(item => item.total)
      }]
    }
  }

  // Game Status (Bar)
  if (data.games_by_status) {
    barChartData.value = {
      labels: data.games_by_status.map(item => item.status),
      datasets: [{
        label: 'Games',
        backgroundColor: '#10b981',
        data: data.games_by_status.map(item => item.total)
      }]
    }
  }

  // Payment Methods (Pie)
  if (data.purchases_by_type) {
    paymentChartData.value = {
      labels: data.purchases_by_type.map(item => item.payment_type),
      datasets: [{
        backgroundColor: ['#8b5cf6', '#ec4899', '#eab308', '#64748b'],
        data: data.purchases_by_type.map(item => item.total)
      }]
    }
  }

  if (data.purchases_by_month) {
      lineChartData.value = {
        labels: data.purchases_by_month.map(item => item.month),
        datasets: [{
          label: 'Revenue (€)',
          borderColor: '#8b5cf6',
          backgroundColor: '#8b5cf6',
          data: data.purchases_by_month.map(item => item.total),
          tension: 0.3 
        }]
      }
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-6 md:p-10">
    <div class="max-w-7xl mx-auto space-y-8">
      
      <!-- Back to dashboard button -->
      <div>
        <Button variant="outline" size="sm" @click="router.push({ name: 'dashboard' })">
          <ArrowLeft class="w-4 h-4 mr-2" />
          Dashboard
        </Button>
      </div>

      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-3xl font-bold tracking-tight text-slate-900">Statistics</h1>
          <p class="text-slate-500">Overview of the Bisca Platform activity.</p>
        </div>
        <Badge v-if="isAdmin" variant="destructive" class="w-fit">
          Admin Mode
        </Badge>
      </div>

      <div v-if="isLoading" class="flex justify-center py-20">
        <Loader2 class="w-10 h-10 animate-spin text-primary" />
      </div>

      <div v-else class="space-y-8">
        
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Total Players</CardTitle>
              <Users class="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">{{ publicStats.total_players }}</div>
              <p class="text-xs text-muted-foreground">Registered users</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Total Games</CardTitle>
              <Gamepad2 class="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">{{ publicStats.total_games }}</div>
              <p class="text-xs text-muted-foreground">Matches played so far</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Active Now</CardTitle>
              <Activity class="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">{{ publicStats.total_playing }}</div>
              <p class="text-xs text-muted-foreground">Games in progress</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Avg Duration</CardTitle>
              <Timer class="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">{{ publicStats.avg_game_duration }}m</div>
              <p class="text-xs text-muted-foreground">Average game time</p>
            </CardContent>
          </Card>
        </div>

        <div v-if="isAdmin && adminStats" class="space-y-6">
          <div class="border-t pt-6">
            <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
              <TrendingUp class="w-5 h-5" /> Business Intelligence
            </h2>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <Card class="bg-slate-900 text-white border-slate-800">
              <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium text-slate-300">Total Revenue (Real Money)</CardTitle>
                <Euro class="h-4 w-4 text-green-400" />
              </CardHeader>
              <CardContent>
                <div class="text-3xl font-bold">€ {{ adminStats.total_revenue }}</div>
                <p class="text-xs text-slate-400">Total from coin purchases</p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Virtual Economy</CardTitle>
                <div class="h-4 w-4 rounded-full bg-yellow-400" />
              </CardHeader>
              <CardContent>
                <div class="text-3xl font-bold">{{ adminStats.total_coins }}</div>
                <p class="text-xs text-muted-foreground">Total coins in circulation</p>
              </CardContent>
            </Card>
          </div>

          <div class="grid gap-6 md:grid-cols-3">
            <Card>
              <CardHeader>
                <CardTitle>Game Types</CardTitle>
              </CardHeader>
              <CardContent class="h-64">
                 <Pie :data="pieChartData" :options="chartOptions" />
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Game Status</CardTitle>
              </CardHeader>
              <CardContent class="h-64">
                 <Bar :data="barChartData" :options="chartOptions" />
              </CardContent>
            </Card>

             <Card>
              <CardHeader>
                <CardTitle>Payment Methods</CardTitle>
              </CardHeader>
              <CardContent class="h-64">
                 <Pie :data="paymentChartData" :options="chartOptions" />
              </CardContent>
            </Card>
          </div>

          <div class="grid gap-6 lg:grid-cols-2">
            <Card>
              <CardHeader><CardTitle>Top 5 Richest (Coins)</CardTitle></CardHeader>
              <CardContent>
                <table class="w-full text-sm text-left">
                  <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr><th class="px-4 py-2">Nick</th><th class="px-4 py-2 text-right">Coin Amount</th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="p in adminStats.top_rich_players" :key="p.nickname" class="border-b">
                      <td class="px-4 py-2 font-medium">{{ p.nickname }}</td>
                      <td class="px-4 py-2 text-right font-bold text-yellow-600">{{ p.coins_balance }}</td>
                    </tr>
                  </tbody>
                </table>
              </CardContent>
            </Card>

            <Card>
              <CardHeader><CardTitle>Top 5 Win Rate (Min. {{ adminStats.min_games || 3 }} Games)</CardTitle></CardHeader>
              <CardContent>
                <table class="w-full text-sm text-left">
                  <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr><th class="px-4 py-2">Nick</th><th class="px-4 py-2 text-center">Wins/Total</th><th class="px-4 py-2 text-right">%</th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="p in adminStats.top_win_rate" :key="p.nickname" class="border-b">
                      <td class="px-4 py-2 font-medium">{{ p.nickname }}</td>
                      <td class="px-4 py-2 text-center text-gray-500">{{ p.total_wins }}/{{ p.total_played }}</td>
                      <td class="px-4 py-2 text-right font-bold" :class="p.win_rate > 50 ? 'text-green-600' : 'text-orange-600'">
                        {{ p.win_rate }}%
                      </td>
                    </tr>
                  </tbody>
                </table>
              </CardContent>
            </Card>
          </div>

          <div class="grid gap-6 md:grid-cols-1 mb-6">
              <Card>
                  <CardHeader>
                      <CardTitle>Revenue History</CardTitle>
                      <CardDescription>Monthly coin purchases (All time)</CardDescription>
                  </CardHeader>
                  <CardContent class="h-80">
                      <Line :data="lineChartData" :options="chartOptions" />
                  </CardContent>
              </Card>
          </div>

          <div class="grid gap-6 lg:grid-cols-2 mt-6">
              <Card>
                  <CardHeader>
                      <CardTitle>Top Spenders (Real Money)</CardTitle>
                  </CardHeader>
                  <CardContent>
                      <table class="w-full text-sm text-left">
                          <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                              <tr>
                                  <th class="px-4 py-2">Nickname</th>
                                  <th class="px-4 py-2 text-right">Total Spent</th>
                              </tr>
                          </thead>
                          <tbody>
                              <tr v-for="player in adminStats.top_spenders" :key="player.email" class="border-b">
                                  <td class="px-4 py-2 font-medium">{{ player.nickname }}</td>
                                  <td class="px-4 py-2 text-right font-bold text-green-600">
                                      € {{ player.total_spent }}
                                  </td>
                              </tr>
                              <tr v-if="!adminStats.top_spenders?.length">
                                  <td colspan="2" class="px-4 py-6 text-center text-gray-400">No purchases yet.</td>
                              </tr>
                          </tbody>
                      </table>
                  </CardContent>
              </Card>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>