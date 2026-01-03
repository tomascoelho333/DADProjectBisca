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
import { LogOut, Trash2, Coins, User as UserIcon, Trophy, ArrowLeft } from 'lucide-vue-next'

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

const amountEuros = ref(1) 

const coinsToReceive = computed(() => {
  const euros = parseInt(amountEuros.value) || 0
  return euros * 10
})

const paymentType = ref('MBWAY')
const paymentReference = ref('')


const referencePlaceholder = computed(() => {
  switch (paymentType.value) {
    case 'MBWAY': return '9 digits starting with 9'
    case 'PAYPAL': return 'email@domain.com'
    case 'IBAN': return 'PT50 + 23 digits'
    case 'MB': return '12345-123456789'
    case 'VISA': return '16 digits starting with 4'
    default: return 'Enter reference'
  }
})


const isReferenceValid = computed(() => {
  const ref = paymentReference.value
  if (!ref) return false

  switch (paymentType.value) {
    case 'MBWAY': return /^9\d{8}$/.test(ref) 
    case 'PAYPAL': return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(ref) 
    case 'IBAN': return /^[A-Z]{2}\d{23}$/.test(ref) 
    case 'MB': return /^\d{5}-\d{9}$/.test(ref) 
    case 'VISA': return /^4\d{15}$/.test(ref) 
    default: return false
  }
})

const isPurchasing = ref(false)

const handlePurchase = async () => {
  if (!isReferenceValid.value || amountEuros.value < 1) {
    alert('Please check your payment details.')
    return
  }

  isPurchasing.value = true
  
  try {
    const response = await axios.post('/api/funds/add', {
      type: paymentType.value,
      reference: paymentReference.value,
      value: amountEuros.value
    })

    if (response.data && response.data.new_balance !== undefined) {
      userStore.user.coins_balance = response.data.new_balance
      alert(`Success! You now have ${response.data.new_balance} coins.`)
      
      paymentReference.value = ''
      amountEuros.value = 1
    }
  } catch (error) {
    const errorMsg = 'Payment failed. Please try again.'
    alert(errorMsg)
  } finally {
    isPurchasing.value = false
  }
}

</script>

<template>
  <div class="min-h-screen bg-slate-50 p-4 md:p-8">
    
    <header class="max-w-5xl mx-auto flex justify-between items-center mb-8">
        <div class="flex items-center gap-4">
          <Button variant="outline" size="icon" @click="router.back()">
            <ArrowLeft class="w-4 h-4" />
          </Button>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Add Balance</h1>
      </div>
      <Button variant="outline" @click="handleLogout">
        <LogOut class="w-4 h-4 mr-2" />
        Sign out
      </Button>
    </header>

    <main class="max-w-5xl mx-auto grid gap-6 md:grid-cols-2">
      
    <Card class="md:col-span-2">
    <CardHeader>
        <CardTitle>Add Balance</CardTitle>
    </CardHeader>
    <CardContent class="grid gap-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      
      <div>
    <Label class="block mb-2 font-medium text-slate-700">Payment Method</Label>
    <select v-model="paymentType" @change="paymentReference = ''" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
      <option value="MBWAY">MBWAY</option>
      <option value="PAYPAL">PayPal</option>
      <option value="IBAN">IBAN</option>
      <option value="MB">Multibanco (MB)</option>
      <option value="VISA">VISA</option>
    </select>
  </div>

      <div class="md:col-span-1">
    <Label class="block mb-2 font-medium text-slate-700">Reference</Label>
    <Input 
      v-model="paymentReference" 
      type="text" 
      :placeholder="referencePlaceholder"
      :class="{'border-red-500': paymentReference && !isReferenceValid}"
    />
    <p v-if="paymentReference && !isReferenceValid" class="text-xs text-red-500 mt-1">
      Invalid format for {{ paymentType }}.
    </p>
  </div>

      <div>
        <Label class="block mb-2 font-medium text-slate-700">Amount (€)</Label>
        <Input 
          v-model="amountEuros" 
          type="number" 
          min="1" 
          step="1"
          placeholder="Euros"
        />
        <p class="text-xs text-slate-500 mt-1">You will receive: {{ coinsToReceive }} Coins</p>
      </div>
    </div>

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
      
        <Button 
            class="bg-yellow-600 hover:bg-yellow-700 text-white" 
            @click="handlePurchase"
            :disabled="isPurchasing || !isReferenceValid || amountEuros < 1"
            >
            <span v-if="isPurchasing">Processing...</span>
            <span v-else>Confirm Purchase</span>
        </Button>
    </div>
  </CardContent>
</Card>

    </main>
  </div>
</template>