import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import LoginView from '../views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'
import { useUserStore } from '@/stores/user'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      // Default value is commented:
      //component: HomeView,
      redirect: '/login',
    },
    {
      path: '/about',
      name: 'about',
      // route level code-splitting
      // this generates a separate chunk (About.[hash].js) for this route
      // which is lazy-loaded when the route is visited.
      component: () => import('../views/AboutView.vue'),
    },
    //Dashboard
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../views/DashboardView.vue'),
      meta: { requiresAuth: true }
    },
    //Login
    {
      path: '/login',
      name: 'login',
      component: LoginView
    },
    //Register
    {
      path: '/register',
      name: 'register',
      component: RegisterView
    }
  ],
})

router.beforeEach(async (to, from, next) => {
  const userStore = useUserStore()
  
  if (to.meta.requiresAuth) {   
    // If the user in on memory
    if (userStore.user) {
      return next()
    }

    // No user but has token
    if (localStorage.getItem('token')) {

      const success = await userStore.restoreToken()
      
      if (success) {
        return next()
      } else {
        return next('/login') // Token Expired
      }
    }
    return next('/login')
  }

  if ((to.name === 'login' || to.name === 'register') && userStore.isAuthenticated) {
      return next('/dashboard')
  }
  
  next()
})

export default router
