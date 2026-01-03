<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { Loader2, Upload } from 'lucide-vue-next'

// Shadcn UI Components
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

// Form Fields
const name = ref('')
const nickname = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const photoFile = ref(null)

// UI State
const isLoading = ref(false)
const error = ref('')

// Function to handle file selection
const handleFileChange = (event) => {
  // Get the first file selected by the user
  photoFile.value = event.target.files[0]
}

const handleRegister = async () => {
  error.value = ''
  isLoading.value = true

  try {
    const formData = new FormData()
    formData.append('name', name.value)
    formData.append('nickname', nickname.value)
    formData.append('email', email.value)
    formData.append('password', password.value)
    formData.append('password_confirmation', passwordConfirmation.value)
    
    // Only append the file if the user selected one
    if (photoFile.value) {
      formData.append('photo_avatar_filename', photoFile.value)
    }

    // Call the register action in the store
    await userStore.register(formData)

    // Success: Redirect to dashboard
    router.push('/dashboard')

  } catch (e) {
    // Error Handling: specific API message or generic fallback
    if (e.response && e.response.data && e.response.data.message) {
      error.value = e.response.data.message
    } else {
      error.value = 'An error occurred during registration. Please check your inputs.'
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="flex items-center justify-center min-h-screen bg-slate-50 py-10">
    <Card class="w-full max-w-md shadow-lg">
      <CardHeader class="space-y-1">
        <CardTitle class="text-2xl font-bold text-center">Create Account</CardTitle>
        <CardDescription class="text-center">
          Enter your details to join the game
        </CardDescription>
      </CardHeader>
      
      <CardContent>
        <form @submit.prevent="handleRegister" class="space-y-4">
          
          <Alert v-if="error" variant="destructive">
            <AlertTitle>Error</AlertTitle>
            <AlertDescription>{{ error }}</AlertDescription>
          </Alert>

          <div class="space-y-2">
            <Label for="name">Full Name</Label>
            <Input id="name" v-model="name" placeholder="José João" required />
          </div>

          <div class="space-y-2">
            <Label for="nickname">Nickname</Label>
            <Input id="nickname" v-model="nickname" placeholder="ProGamer123" required />
          </div>

          <div class="space-y-2">
            <Label for="email">Email</Label>
            <Input id="email" type="email" v-model="email" placeholder="example@mail.com" required />
          </div>

          <div class="space-y-2">
            <Label for="password">Password</Label>
            <Input id="password" type="password" v-model="password" required />
          </div>

          <div class="space-y-2">
            <Label for="confirm_password">Confirm Password</Label>
            <Input id="confirm_password" type="password" v-model="passwordConfirmation" required />
          </div>

          <div class="space-y-2">
            <Label for="photo">Avatar (Optional)</Label>
            <Input 
              id="photo" 
              type="file" 
              accept="image/*" 
              @change="handleFileChange" 
              class="cursor-pointer file:text-primary" 
            />
          </div>

          <Button type="submit" class="w-full" :disabled="isLoading">
            <Loader2 v-if="isLoading" class="w-4 h-4 mr-2 animate-spin" />
            <span v-if="isLoading">Creating account...</span>
            <span v-else>Register</span>
          </Button>
        </form>
      </CardContent>
      
      <CardFooter class="flex flex-col space-y-2 text-center">
        <div class="text-sm text-muted-foreground">
          Already have an account?
          <router-link to="/login" class="text-primary hover:underline">
            Login
          </router-link>
        </div>
      </CardFooter>
    </Card>
  </div>
</template>