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

    // Action to register a new user
    async function register(formData) {
        try {
            //POST TO API
            const response = await axios.post('/api/auth/register', formData)
            
            // Update state with new token and user data
            token.value = response.data.token
            user.value = response.data.user
            
            // Persist token and set headers
            localStorage.setItem('token', token.value)
            axios.defaults.headers.common['Authorization'] = 'Bearer ' + token.value
            
            return true
        } catch (error) 
        {
            throw error
        }
    }

    async function restoreToken() {
        //If theres no token, do nuthing
        if (!token.value) return 

        axios.defaults.headers.common['Authorization'] = 'Bearer ' + token.value

        // GETS the data from the API again
        try {
        const response = await axios.get('/api/users/me')
        user.value = response.data
        return true
        } catch (error) {
        
        // If the token expired, returns error
        console.error('Invalid token: ', error)
        clearUser() 
        return false
        }

    function clearUser() {
      token.value = null
      user.value = null
      localStorage.removeItem('token')
      delete axios.defaults.headers.common['Authorization']
    }
  }

  async function updateProfile(formData) {
      try {
        const response = await axios.post('/api/users/me', formData)
        
        // Update the local user state with the fresh data from the server
        user.value = response.data.user
        return true
      } catch (error) {
        throw error
      }
    }

    return { 
        user, 
        token, 
        isAuthenticated, 
        login, 
        logout, 
        register,
        restoreToken,
        updateProfile
    }
})