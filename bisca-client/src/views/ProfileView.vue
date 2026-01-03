<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import axios from 'axios'
import { Loader2, ArrowLeft, Trash2 } from 'lucide-vue-next'

// Shadcn UI Components
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Separator } from '@/components/ui/separator'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'

const router = useRouter()
const userStore = useUserStore()

// Form Fields
const name = ref('')
const nickname = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const photoFile = ref(null)
const photoPreview = ref(null)

// UI State (Update)
const isLoading = ref(false)
const error = ref('')
const successMessage = ref('')

// UI State (Delete Account)
const isDeleteDialogOpen = ref(false)
const deletePassword = ref('')
const deleteError = ref('')
const isDeleting = ref(false)

// Load data
onMounted(() => {
  if (userStore.user) {
    name.value = userStore.user.name
    nickname.value = userStore.user.nickname
    email.value = userStore.user.email
    if (userStore.user.photo_avatar_filename) {
      photoPreview.value = `http://127.0.0.1:8000/storage/photos_avatars/${userStore.user.photo_avatar_filename}`
    }else{
      photoPreview.value = `http://127.0.0.1:8000/storage/photos_avatars/anonymous.png`
    }
  }
})

const handleFileChange = (event) => {
  const file = event.target.files[0]
  if (file) {
    photoFile.value = file
    photoPreview.value = URL.createObjectURL(file)
  }
}

// UPDATE PROFILE
const handleUpdate = async () => {
  error.value = ''
  successMessage.value = ''
  isLoading.value = true

  try {
    const formData = new FormData()
    //Makes so the photo can be uploaded
    formData.append('_method', 'PUT')
    formData.append('name', name.value)
    formData.append('nickname', nickname.value)
    formData.append('email', email.value)

    if (password.value) {
      formData.append('password', password.value)
      formData.append('password_confirmation', passwordConfirmation.value)
    }
    if (photoFile.value) {
      formData.append('photo_avatar_filename', photoFile.value)
    }

    await userStore.updateProfile(formData)
    successMessage.value = 'Profile updated successfully!'
    password.value = ''
    passwordConfirmation.value = ''

  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to update profile.'
  } finally {
    isLoading.value = false
  }
}

// DELETE ACCOUNT
const handleDeleteAccount = async () => {
  deleteError.value = ''
  isDeleting.value = true

  try {
    await axios.delete('/api/users/me', {
      data: { password: deletePassword.value }
    })
    await userStore.logout()
    router.push('/login')
  } catch (e) {
    deleteError.value = e.response?.data?.message || 'Error deleting account.'
  } finally {
    isDeleting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 py-10 px-4 flex justify-center items-start">
    
    <Card class="w-full max-w-2xl shadow-lg">
      <CardHeader>
        <div class="flex items-center gap-4">
          <Button variant="outline" size="icon" @click="router.back()">
            <ArrowLeft class="w-4 h-4" />
          </Button>
          <div>
            <CardTitle class="text-2xl">Edit Profile</CardTitle>
            <CardDescription>Update your personal information and security.</CardDescription>
          </div>
        </div>
      </CardHeader>
      
      <CardContent class="space-y-6">
        
        <form @submit.prevent="handleUpdate" class="space-y-6">
          
          <Alert v-if="error" variant="destructive">
            <AlertTitle>Error</AlertTitle>
            <AlertDescription>{{ error }}</AlertDescription>
          </Alert>

          <Alert v-if="successMessage" class="bg-green-50 text-green-700 border-green-200">
            <AlertTitle>Success</AlertTitle>
            <AlertDescription>{{ successMessage }}</AlertDescription>
          </Alert>

          <div class="flex flex-col items-center sm:flex-row gap-6">
            <Avatar class="h-24 w-24 border-2 border-slate-200">
              <AvatarImage :src="photoPreview" class="object-cover" />
            </Avatar>
            <div class="space-y-2 w-full">
              <Label for="photo">Profile Picture</Label>
              <Input id="photo" type="file" accept="image/*" @change="handleFileChange" class="cursor-pointer" />
            </div>
          </div>

          <Separator />

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="name">Full Name</Label>
              <Input id="name" v-model="name" required />
            </div>
            <div class="space-y-2">
              <Label for="nickname">Nickname</Label>
              <Input id="nickname" v-model="nickname" required />
            </div>
            <div class="space-y-2 md:col-span-2">
              <Label for="email">Email Address</Label>
              <Input id="email" type="email" v-model="email" required />
            </div>
          </div>

          <Separator />

          <div class="space-y-2">
            <h3 class="text-lg font-medium">Change Password</h3>
            <p class="text-sm text-muted-foreground">Leave blank to keep current password.</p>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="password">New Password</Label>
              <Input id="password" type="password" v-model="password" />
            </div>
            <div class="space-y-2">
              <Label for="confirm">Confirm New Password</Label>
              <Input id="confirm" type="password" v-model="passwordConfirmation" />
            </div>
          </div>

          <div class="flex justify-end gap-4">
            <Button type="button" variant="outline" @click="router.back()">
               Cancel
             </Button>
             <Button type="submit" :disabled="isLoading">
                <Loader2 v-if="isLoading" class="w-4 h-4 mr-2 animate-spin" />
                <span v-if="isLoading">Saving...</span>
                <span v-else>Save Changes</span>
             </Button>
          </div>
        </form>

        <Separator class="my-6" />

        <div class="rounded-md border border-red-200 p-4 bg-red-50/50">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-medium text-red-600">Danger Zone</h3>
              <p class="text-sm text-slate-600">
                Deleting your account will remove all coins and history.
              </p>
            </div>

            <Dialog v-model:open="isDeleteDialogOpen">
              <DialogTrigger as-child>
                <Button variant="destructive">
                  <Trash2 class="w-4 h-4 mr-2" />
                  Delete Account
                </Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Are you absolutely sure?</DialogTitle>
                  <DialogDescription>
                    This action cannot be undone. To confirm, please enter your current password.
                  </DialogDescription>
                </DialogHeader>
                
                <div class="grid gap-4 py-4">
                  <div class="grid gap-2">
                    <Label for="del_password">Confirm Password</Label>
                    <Input 
                      id="del_password" 
                      type="password" 
                      v-model="deletePassword"
                      placeholder="Your current password" 
                    />
                  </div>
                  <p v-if="deleteError" class="text-sm text-red-600">{{ deleteError }}</p>
                </div>

                <DialogFooter>
                  <Button variant="outline" @click="isDeleteDialogOpen = false">Cancel</Button>
                  <Button 
                    variant="destructive" 
                    @click="handleDeleteAccount"
                    :disabled="!deletePassword || isDeleting"
                  >
                    {{ isDeleting ? 'Deleting...' : 'Confirm Deletion' }}
                  </Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>
          </div>
        </div>

      </CardContent>
    </Card>
  </div>
</template>