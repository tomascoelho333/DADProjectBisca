<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { Button } from '@/components/ui/button'
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/ui/card'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import { 
  LogOut, LogIn, UserPlus, Coins, User as UserIcon, 
  Trophy, BarChart3, HistoryIcon, Settings, Gamepad2, Users
} from 'lucide-vue-next'

const router = useRouter()
const userStore = useUserStore()

// URL do Avatar
const userAvatarUrl = computed(() => {
  if (userStore.user?.photo_avatar_filename) {
    return `http://127.0.0.1:8000/storage/photos_avatars/${userStore.user.photo_avatar_filename}`
  }
  return `http://127.0.0.1:8000/storage/photos_avatars/anonymous.png`
})

const handleLogout = async () => {
  await userStore.logout()
  router.push('/dashboard')
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-4 md:p-8">

    <header class="max-w-5xl mx-auto flex justify-between items-center mb-8">
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Bisca Dashboard</h1>
        <p v-if="userStore.user" class="text-slate-500">Welcome back, {{ userStore.user.nickname }}!</p>
        <p v-else class="text-slate-500">Join the community to play Bisca online!</p>
      </div>

      <div v-if="userStore.user">
         <Button variant="outline" @click="handleLogout">
           <LogOut class="w-4 h-4 mr-2" /> Sign out
         </Button>
      </div>
      <div v-else class="flex gap-2">
         <Button variant="outline" @click="router.push('/login')">
           <LogIn class="w-4 h-4 mr-2" /> Sign In
         </Button>
      </div>
    </header>

    <main class="max-w-5xl mx-auto grid gap-6 md:grid-cols-2 lg:grid-cols-3">

      <!-- User Profile Card -->
      <Card>
        <CardHeader class="flex flex-row items-center gap-4">
          <Avatar class="h-16 w-16 border-2 border-slate-100">
            <AvatarImage :src="userAvatarUrl" alt="Avatar" />
            <AvatarFallback class="bg-slate-200">?</AvatarFallback>
          </Avatar>
          
          <div v-if="userStore.user">
            <CardTitle>{{ userStore.user.name }}</CardTitle>
            <CardDescription>{{ userStore.user.email }}</CardDescription>
            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold bg-primary text-primary-foreground mt-2">
              {{ userStore.user.type === 'A' ? 'Administrator' : 'Player' }}
            </span>
          </div>
          <div v-else>
            <CardTitle>Welcome Guest</CardTitle>
            <CardDescription>Create an account to track stats and earn coins.</CardDescription>
          </div>
        </CardHeader>

        <CardContent>
          <div class="grid gap-2">
            
            <div v-if="userStore.user" class="grid gap-2 pb-2 mb-2 border-b border-slate-100">
                <Button variant="secondary" class="w-full justify-start" @click="router.push('/profile')">
                  <UserIcon class="mr-2 h-4 w-4" /> Edit Profile
                </Button>
                <Button variant="secondary" class="w-full justify-start" @click="router.push('/history')">
                  <HistoryIcon class="mr-2 h-4 w-4" /> My Game History
                </Button>
                <Button v-if="userStore.user.type === 'A'" variant="secondary" class="w-full justify-start bg-red-50 hover:bg-red-100 text-red-900" @click="router.push('/admin')">
                  <Settings class="mr-2 h-4 w-4" /> Administration
                </Button>
            </div>

            <Button variant="ghost" class="w-full justify-start border" @click="router.push('/stats')">
              <BarChart3 class="mr-2 h-4 w-4 text-blue-500" /> Platform Statistics
            </Button>
            <Button variant="ghost" class="w-full justify-start border" @click="router.push('/leaderboard')">
              <Trophy class="mr-2 h-4 w-4 text-yellow-500" /> Leaderboard
            </Button>
            
          </div>
        </CardContent>
      </Card>

      <!-- Play Games Card (Not visible to Administrators) -->
      <Card v-if="userStore.user && userStore.user.type !== 'A'" class="md:col-span-2 lg:col-span-2 bg-gradient-to-br from-blue-50 to-indigo-50 border-indigo-200">
        <CardHeader>
          <CardTitle class="flex items-center gap-2 text-2xl">
            <Gamepad2 class="w-6 h-6 text-indigo-600" /> Play Bisca
          </CardTitle>
          <CardDescription>Choose your game mode and variant</CardDescription>
        </CardHeader>

        <CardContent>
          <div class="grid gap-4">
            <!-- Single Player Section -->
            <div class="bg-white rounded-lg p-4 border border-indigo-100">
              <h3 class="font-semibold text-slate-700 mb-3">Single Player (Play vs Bot)</h3>
              <div class="grid grid-cols-2 gap-2">
                <Button 
                  variant="secondary" 
                  class="w-full justify-start" 
                  @click="router.push({ name: 'singlePlayerGame', query: { variant: 'bisca9' } })"
                >
                  <Gamepad2 class="mr-2 h-4 w-4" /> Bisca de 9
                </Button>
                <Button 
                  variant="secondary" 
                  class="w-full justify-start" 
                  @click="router.push({ name: 'singlePlayerGame', query: { variant: 'bisca3' } })"
                >
                  <Gamepad2 class="mr-2 h-4 w-4" /> Bisca de 3
                </Button>
              </div>
            </div>

            <!-- Multiplayer Section (only for registered users) -->
            <div v-if="userStore.user && userStore.user.type !== 'A'" class="bg-white rounded-lg p-4 border border-green-100">
              <h3 class="font-semibold text-slate-700 mb-3">Multiplayer (Play vs Players)</h3>
              <div class="grid grid-cols-2 gap-2">
                <Button 
                  variant="secondary" 
                  class="w-full justify-start bg-green-50 hover:bg-green-100 text-green-900 border-green-200" 
                  @click="router.push({ name: 'multiplayerGame', query: { variant: 'bisca9' } })"
                >
                  <Users class="mr-2 h-4 w-4" /> Bisca de 9
                </Button>
                <Button 
                  variant="secondary" 
                  class="w-full justify-start bg-green-50 hover:bg-green-100 text-green-900 border-green-200" 
                  @click="router.push({ name: 'multiplayerGame', query: { variant: 'bisca3' } })"
                >
                  <Users class="mr-2 h-4 w-4" /> Bisca de 3
                </Button>
              </div>
              <p class="text-xs text-slate-500 mt-2 text-center">
                Single game: 2 coins | Match (best of 4): 3+ coins
              </p>
            </div>

            <!-- Guest notice -->
            <div v-else class="bg-blue-50 rounded-lg p-3 border border-blue-200">
              <p class="text-sm text-blue-900">
                Sign in to play multiplayer games and earn coins!
              </p>
              <Button 
                class="w-full mt-2 bg-blue-600 hover:bg-blue-700 text-white" 
                @click="router.push('/login')"
              >
                Sign In to Play Multiplayer
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Administrator Notice Card -->
      <Card v-if="userStore.user && userStore.user.type === 'A'" class="md:col-span-2 lg:col-span-2 bg-gradient-to-br from-red-50 to-orange-50 border-red-200">
        <CardHeader>
          <CardTitle class="flex items-center gap-2 text-2xl text-red-900">
            <Settings class="w-6 h-6 text-red-600" /> Administrator Account
          </CardTitle>
          <CardDescription class="text-red-800">You have administrative privileges</CardDescription>
        </CardHeader>

        <CardContent>
          <p class="text-sm text-red-900 mb-4">
            As an administrator, you have full access to the platform management tools and can view all users, transactions, and games. However, administrator accounts cannot play games or hold coins.
          </p>
          <Button 
            class="w-full bg-red-600 hover:bg-red-700 text-white" 
            @click="router.push('/admin')"
          >
            Go to Administration Panel
          </Button>
        </CardContent>
      </Card>

      <!-- Coins Balance Card (only for non-admin players) -->
      <Card v-if="userStore.user && userStore.user.type !== 'A'" class="bg-yellow-50/50 border-yellow-200">
        <CardContent class="pt-6">
          <div class="flex items-center justify-between p-4 border rounded-lg bg-yellow-50/50 border-yellow-200">
            <div class="flex items-center gap-4">
              <div class="p-3 bg-yellow-100 rounded-full shadow-sm">
                <Coins class="w-8 h-8 text-yellow-600" />
              </div>
              <div>
                <p class="text-sm font-medium text-slate-500">Current Balance</p>
                <p class="text-3xl font-bold text-slate-800">{{ userStore.user.coins_balance ?? 0 }}</p>
              </div>
            </div>
            <div class="items-right ml-auto">
                <Button variant="outline" @click="router.push('/funds/add')">
                  Add Balance
                </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Guest CTA Card -->
      <Card v-else-if="!userStore.user" class="bg-slate-900 text-white border-slate-800">
        <CardHeader>
          <CardTitle class="text-xl">Ready to join the table?</CardTitle>
          <CardDescription class="text-slate-400">
            Register now to receive your starting coins and compete for the top spot on the global leaderboard.
          </CardDescription>
        </CardHeader>
        <CardContent>
           <Button class="w-full bg-white text-slate-900 hover:bg-slate-200 font-bold" @click="router.push('/register')">
             Create Free Account
           </Button>
        </CardContent>
      </Card>

    </main>
  </div>
</template>
