<script setup>
import { onMounted } from 'vue'
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()

onMounted(() => {
  userStore.restoreToken()
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-sans antialiased">
    <RouterView />
  </div>
</template>