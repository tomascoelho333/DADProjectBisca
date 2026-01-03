<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import axios from 'axios'

// UI Components (Shadcn)
import { Button } from '@/components/ui/button'
import { Card, CardHeader, CardTitle, CardContent, CardDescription, CardFooter } from '@/components/ui/card'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { LogOut, Trash2, Coins, User as UserIcon, Trophy, GamepadIcon, Users } from 'lucide-vue-next'

const router = useRouter()
const userStore = useUserStore()

// State for Delete Account Modal
const isDeleteDialogOpen = ref(false)
const deletePassword = ref('')
const deleteError = ref('')
const isDeleting = ref(false)

// If the user has a photo filename, we construct the full URL
const userAvatarUrl = computed(() => {
  if (userStore.user?.photo_avatar_filename) {
    return `http://127.0.0.1:8000/storage/photos/${userStore.user.photo_avatar_filename}`
  }
  return null
})

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
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-4 md:p-8">

    <header class="max-w-5xl mx-auto flex justify-between items-center mb-8">
      <div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Dashboard</h1>
        <p class="text-slate-500">Welcome back, {{ userStore.user?.nickname }}!</p>
      </div>
      <Button variant="outline" @click="handleLogout">
        <LogOut class="w-4 h-4 mr-2" />
        Sign out
      </Button>
    </header>

    <main class="max-w-5xl mx-auto grid gap-6 md:grid-cols-2 lg:grid-cols-3">

      <!-- User Profile Card -->
      <Card>
        <CardHeader class="flex flex-row items-center gap-4">
          <Avatar class="h-16 w-16">
            <AvatarImage :src="userAvatarUrl" alt="Avatar" />
            <AvatarFallback>{{ userInitials }}</AvatarFallback>
          </Avatar>
          <div>
            <CardTitle>{{ userStore.user?.name }}</CardTitle>
            <CardDescription>{{ userStore.user?.email }}</CardDescription>
            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 border-transparent bg-primary text-primary-foreground hover:bg-primary/80 mt-2">
              {{ userStore.user?.type === 'A' ? 'Administrator' : 'Player' }}
            </span>
          </div>
        </CardHeader>
        <CardContent>
          <div class="grid gap-2">
             <Button
              variant="secondary"
              class="w-full justify-start"
              @click="router.push('/profile')"
            >
              <UserIcon class="mr-2 h-4 w-4" /> Edit Profile
            </Button>
          </div>
        </CardContent>
      </Card>

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
              </div>
              <div>
                <p class="text-sm font-medium text-slate-500">Current Balance</p>
                <p class="text-2xl font-bold">{{ userStore.user?.coins_balance ?? 0 }} Coins</p>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div class="p-4 border rounded-lg text-center">
              <p class="text-sm text-slate-500">Total Games</p>
              <p class="text-xl font-bold">0</p>
            </div>
            <div class="p-4 border rounded-lg text-center">
              <p class="text-sm text-slate-500">Wins</p>
              <p class="text-xl font-bold text-green-600">0</p>
            </div>
            <div class="p-4 border rounded-lg text-center">
              <p class="text-sm text-slate-500">Losses</p>
              <p class="text-xl font-bold text-red-600">0</p>
            </div>
          </div>

        </CardContent>
      </Card>

    </main>
  </div>
</template>
