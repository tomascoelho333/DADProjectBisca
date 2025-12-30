<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { Button } from '@/components/ui/button'
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/ui/card'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import { 
  LogOut, LogIn, UserPlus, Coins, User as UserIcon, 
  Trophy, BarChart3, HistoryIcon 
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
         <Button variant="ghost" @click="router.push('/login')">
           <LogIn class="w-4 h-4 mr-2" /> Sign In
         </Button>
      </div>
    </header>

    <main class="max-w-5xl mx-auto grid gap-6 md:grid-cols-2">
      
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

      <Card v-if="userStore.user">
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
          </div>
          </CardContent>
      </Card>

      <Card v-else class="bg-slate-900 text-white border-slate-800">
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