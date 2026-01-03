<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useUserStore } from '@/stores/user'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Trophy, Medal, Crown, Star } from 'lucide-vue-next'
import { ArrowLeft, BarChart3 } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useRouter } from 'vue-router'

const userStore = useUserStore()
const globalData = ref([])
const personalData = ref(null)
const router = useRouter()

onMounted(async () => {

  console.log("Estado do User:", userStore.user)
  // global leaderboard
  try {
    const res = await axios.get('/api/leaderboard/global')
    globalData.value = res.data
  } catch (e) { console.error(e) }

  // personal leaderboard only if logged in
  if (userStore.user) {
    try {
      const resP = await axios.get('/api/leaderboard/personal')
      personalData.value = resP.data
    } catch (e) { console.error(e) }
  }
})
</script>

<template>
  <div class="p-6 max-w-4xl mx-auto space-y-8">
    <h1 class="text-3xl font-bold">Leaderboards</h1>

    <!-- Back to dashboard button -->
    <div>
    <Button variant="outline" size="sm" @click="router.push({ name: 'dashboard' })">
        <ArrowLeft class="w-4 h-4 mr-2" />
        Dashboard
    </Button>
    </div>
    
    <Tabs default-value="global" class="w-full">
      <TabsList class="grid w-full grid-cols-2">
        <TabsTrigger value="global">Global Ranking</TabsTrigger>
        <TabsTrigger value="personal" :disabled="!userStore.user">My Stats</TabsTrigger>
      </TabsList>

      <TabsContent value="global">
        <Card>
          <CardHeader>
            <CardTitle>Top Players</CardTitle>
            <CardDescription>Ranked by Game Wins. Tie-breaker being the first to achieve said score.</CardDescription>
          </CardHeader>
          <CardContent>
            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead class="bg-slate-50 border-b">
                  <tr>
                    <th class="p-4 w-16">#</th>
                    <th class="p-4">Nickname</th>
                    <th class="p-4 text-center">Match Wins</th>
                    <th class="p-4 text-right">Game Wins</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(player, idx) in globalData" :key="idx" class="border-b last:border-0 hover:bg-slate-50">
                    <td class="p-4 font-bold text-slate-500">{{ idx + 1 }}</td>
                    
                    <td class="p-4 font-medium flex items-center gap-2">
                      <Crown v-if="idx === 0" class="w-4 h-4 text-yellow-500" />
                      {{ player.nickname }}
                    </td>

                    <td class="p-4 text-center font-medium text-purple-600">
                        {{ player.total_match_wins }}
                    </td>

                    <td class="p-4 text-right font-bold text-primary text-lg">
                        {{ player.total_game_wins }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      </TabsContent>

      <TabsContent value="personal" v-if="personalData">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          
          <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Game Wins</CardTitle>
              <Trophy class="h-4 w-4 text-yellow-500" />
            </CardHeader>
            <CardContent>
              <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold">
                  {{ personalData.total_game_wins }}
                </span>
                
                <span class="text-sm text-muted-foreground">
                  out of
                </span>

                <span class="text-2xl font-bold">
                  {{ personalData.total_games_played }}
                </span>
              </div>
          </CardContent>
          </Card>

          <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Match Wins</CardTitle>
              <Medal class="h-4 w-4 text-purple-500" />
            </CardHeader>
            <CardContent>
              <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold">
                  {{ personalData.total_match_wins }}
                </span>
                
                <span class="text-sm text-muted-foreground">
                  out of
                </span>

                <span class="text-2xl font-bold">
                  {{ personalData.total_matches_played }}
                </span>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Capotes</CardTitle>
              <Star class="h-4 w-4 text-blue-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">{{ personalData.total_capotes }}</div>
              <p class="text-xs text-muted-foreground">Score 91-119</p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle class="text-sm font-medium">Bandeiras</CardTitle>
              <Star class="h-4 w-4 text-red-500" />
            </CardHeader>
            <CardContent>
              <div class="text-2xl font-bold">{{ personalData.total_bandeiras }}</div>
              <p class="text-xs text-muted-foreground">Score 120 (Max)</p>
            </CardContent>
          </Card>

        </div>
        <Card class="mt-6 border-l-4 border-l-primary shadow-sm">
          <CardContent class="p-6">
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-2 text-slate-500 font-medium">
                <BarChart3 class="w-5 h-5" />
                <span>Personal Ranking</span>
              </div>
              
              <div class="text-5xl font-extrabold text-slate-900 tracking-tight mt-1">
                #{{ personalData.rank }}
              </div>
              
              <div class="flex items-center gap-2 mt-1">
                <span class="text-sm text-slate-500">Global Leaderboard Position</span>
                </div>
            </div>
          </CardContent>
        </Card>
      </TabsContent>
    </Tabs>
  </div>
</template>