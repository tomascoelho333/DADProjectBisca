<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { Loader2 } from 'lucide-vue-next'

// Components Shadcn UI
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'

const router = useRouter()
const userStore = useUserStore()

const email = ref('')
const password = ref('')
const isLoading = ref(false)
const error = ref('')

const handleLogin = async () => {
  error.value = ''
  isLoading.value = true

  try {
    await userStore.login({
      email: email.value,
      password: password.value
    })
    // Success
    router.push('/dashboard')
  } catch (e) {
    // Error
    if (e.response && e.response.status === 401) {
      error.value = e.response.data.message;
    } else {
      error.value = 'An error occured. Try again.'
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="flex items-center justify-center min-h-screen bg-slate-50">
    <Card class="w-full max-w-md shadow-lg">
      <CardHeader class="space-y-1">
        <CardTitle class="text-2xl font-bold text-center">Bisca Game</CardTitle>
        <CardDescription class="text-center">
          Enter Email and Password
        </CardDescription>
      </CardHeader>
      
      <CardContent>
        <form @submit.prevent="handleLogin" class="space-y-4">
          <Alert v-if="error" variant="destructive">
            <AlertTitle>Error</AlertTitle>
            <AlertDescription>{{ error }}</AlertDescription>
          </Alert>

          <div class="space-y-2">
            <Label for="email">Email</Label>
            <Input 
              id="email" 
              type="email" 
              placeholder="example@mail.pt" 
              v-model="email"
              required 
            />
          </div>

          <div class="space-y-2">
            <Label for="password">Password</Label>
            <Input 
              id="password" 
              type="password" 
              v-model="password"
              required 
            />
          </div>

          <Button type="submit" class="w-full" :disabled="isLoading">
            <Loader2 v-if="isLoading" class="w-4 h-4 mr-2 animate-spin" />
            <span v-if="isLoading">Logging in...</span>
            <span v-else>Login</span>
          </Button>
        </form>
      </CardContent>
      
      <CardFooter class="flex flex-col space-y-2 text-center">
        <div class="text-sm text-muted-foreground">
          No Account?
          <router-link to="/register" class="text-primary hover:underline">
            Register
          </router-link>
        </div>
      </CardFooter>
    </Card>
  </div>
</template>