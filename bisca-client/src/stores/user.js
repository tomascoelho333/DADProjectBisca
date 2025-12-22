import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import axios from 'axios'

export const useUserStore = defineStore('user', () => {
  
    //Status
    const user = ref(null)
    // Gets the token from localStorage
    const token = ref(localStorage.getItem('token')) 
    // Getters
    const isAuthenticated = computed(() => !!token.value)

    const PORT = 8000

    async function login(credentials) {
        try {

        // POST to API
        const response = await axios.post(`http://127.0.0.1:${PORT}/api/auth/login`, credentials)
        
        token.value = response.data.token
        user.value = response.data.user
        
        //Saves the token and uses it
        localStorage.setItem('token', token.value)
        axios.defaults.headers.common['Authorization'] = 'Bearer ' + token.value
        
        return true

        } catch (error) {
            console.error(error)
        throw error
        }
    }

    async function logout() {
    try {
      await axios.post('/api/auth/logout') 
    } catch (error) {
      console.error('Error logging out:', error)
    } finally {
      //Removes token either working or not
      token.value = null
      user.value = null
      localStorage.removeItem('token')
      delete axios.defaults.headers.common['Authorization']
    }
  }
    return { user, token, isAuthenticated, login, logout }
})