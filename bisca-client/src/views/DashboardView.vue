<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { Button } from '@/components/ui/button'
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/ui/card'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
<<<<<<< HEAD
import { 
  LogOut, LogIn, UserPlus, Coins, User as UserIcon, 
  Trophy, BarChart3, HistoryIcon, Settings 
} from 'lucide-vue-next'
=======
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { LogOut, Trash2, Coins, User as UserIcon, Trophy, GamepadIcon, Users } from 'lucide-vue-next'
>>>>>>> origin/G3-commits

const router = useRouter()
const userStore = useUserStore()

// URL do Avatar
const userAvatarUrl = computed(() => {
  if (userStore.user?.photo_avatar_filename) {
    return `http://127.0.0.1:8000/storage/photos_avatars/${userStore.user.photo_avatar_filename}`
  }
  return `http://127.0.0.1:8000/storage/photos_avatars/anonymous.png`
})

<<<<<<< HEAD
const handleLogout = async () => {
  await userStore.logout()
  router.push('/dashboard')
=======
// Compute initials for Avatar fallback (e.g., "John Doe" -> "JD")
const userInitials = computed(() => {
  const name = userStore.user?.name || 'User'
  const names = name.trim().split(' ')

  //At least 2 names
  if (names.length >= 2) {
    const firstInitial = names[0][0]
    const lastInitial = names[names.length - 1][0]
    return (firstInitial + lastInitial).toUpperCase()
  }

  //Only 1 name
  return name.substring(0, 2).toUpperCase()
})

// Actions
const handleLogout = async () => {
  await userStore.logout()
  router.push('/login')
}

// Game Navigation
const startSinglePlayerGame = () => {
  router.push('/game/single')
}

const startMultiplayerGame = () => {
  router.push('/game/multiplayer')
}

const handleDeleteAccount = async () => {
  deleteError.value = ''
  isDeleting.value = true

  try {
    // Requirement G1: Send password to confirm deletion
    await axios.delete('/api/users/me', {
      data: { password: deletePassword.value } // Axios DELETE sends body inside 'data'
    })

    // If successful, logout and redirect to login
    await userStore.logout()
    router.push('/login')

  } catch (e) {
    // Get error message from API or fallback
    deleteError.value = e.response?.data?.message || 'Error deleting account.'
  } finally {
    isDeleting.value = false
  }
>>>>>>> origin/G3-commits
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
<<<<<<< HEAD
            
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
=======
             <Button
              variant="secondary"
              class="w-full justify-start"
              @click="router.push('/profile')"
            >
              <UserIcon class="mr-2 h-4 w-4" /> Edit Profile
>>>>>>> origin/G3-commits
            </Button>
            <Button variant="ghost" class="w-full justify-start border" @click="router.push('/leaderboard')">
              <Trophy class="mr-2 h-4 w-4 text-yellow-500" /> Leaderboard
            </Button>
            
          </div>
        </CardContent>
      </Card>

<<<<<<< HEAD
      <Card v-if="userStore.user">
        <CardContent class="pt-6">
          <div class="flex items-center justify-between p-4 border rounded-lg bg-yellow-50/50 border-yellow-200">
            <div class="flex items-center gap-4">
              <div class="p-3 bg-yellow-100 rounded-full shadow-sm">
                <Coins class="w-8 h-8 text-yellow-600" />
=======
      <!-- Game Modes Card -->
      <Card>
        <CardHeader>
          <CardTitle>Play Bisca</CardTitle>
          <CardDescription>Choose your game mode</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-3">
          <Button
            @click="startSinglePlayerGame"
            class="w-full justify-start h-12"
            variant="default"
          >
            <GamepadIcon class="mr-3 h-5 w-5" />
            <div class="text-left">
              <div class="font-semibold">Single Player</div>
              <div class="text-xs text-muted-foreground">Play against bot</div>
            </div>
          </Button>

          <Button
            @click="startMultiplayerGame"
            class="w-full justify-start h-12"
            variant="outline"
          >
            <Users class="mr-3 h-5 w-5" />
            <div class="text-left">
              <div class="font-semibold">Multiplayer</div>
              <div class="text-xs text-muted-foreground">Play against other players</div>
            </div>
          </Button>
        </CardContent>
      </Card>

      <!-- Statistics Card -->

      <Card>
        <CardHeader>
          <CardTitle>Statistics</CardTitle>
          <CardDescription>Your game performance</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-4">

          <div class="flex items-center justify-between p-4 border rounded-lg bg-slate-100">
            <div class="flex items-center gap-2">
              <div class="p-2 bg-yellow-100 rounded-full">
                <Coins class="w-6 h-6 text-yellow-600" />
>>>>>>> origin/G3-commits
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
