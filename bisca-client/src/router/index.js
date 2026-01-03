<<<<<<< HEAD
import { createRouter, createWebHistory } from "vue-router";
import HomeView from "../views/HomeView.vue";
import LoginView from "../views/LoginView.vue";
import RegisterView from "@/views/RegisterView.vue";
import { useUserStore } from "@/stores/user";
=======
import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import LoginView from '../views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'
import SimpleTestView from '@/views/SimpleTestView.vue'
import { useUserStore } from '@/stores/user'
>>>>>>> origin/G3-commits

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: "/",
      name: "home",
      // Default value is commented:
      //component: HomeView,
<<<<<<< HEAD
      redirect: '/dashboard',
=======
      redirect: '/test',
    },
    {
      path: '/test',
      name: 'test',
      component: SimpleTestView
>>>>>>> origin/G3-commits
    },
    {
      path: "/about",
      name: "about",
      // route level code-splitting
      // this generates a separate chunk (About.[hash].js) for this route
      // which is lazy-loaded when the route is visited.
      component: () => import("../views/AboutView.vue"),
    },
    //Dashboard
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../views/DashboardView.vue'),
      meta: { requiresAuth: false }
    },
    //Login
    {
      path: "/login",
      name: "login",
      component: LoginView,
    },
    //Register
    {
      path: "/register",
      name: "register",
      component: RegisterView,
    },
    //User profile
    {
      path: '/profile',
      name: 'profile',
      component: () => import('../views/ProfileView.vue'),
      meta: { requiresAuth: true }
<<<<<<< HEAD
    }, 
    // Statistics
    {
      path: '/stats',
      name: 'statistics',
      component: () => import('../views/StatisticsView.vue'),
      meta: { requiresAuth: false }
    },
    // Leaderboard
    {
      path: '/leaderboard',
      name: 'leaderboard',
      component: () => import('../views/LeaderboardView.vue'),
      meta: { requiresAuth: false }
    },
    {
      path: '/history',
      name: 'history',
      component: () => import('../views/HistoryView.vue'),
      meta: { requiresAuth: true }
    },
    
    {
      path: "/funds/add",
      name: "add-funds",
      component: () => import("../views/FundsView.vue"),
      meta: { requiresAuth: true },
    },
    // Admin
    {
      path: "/admin",
      name: "admin",
      component: () => import("../views/AdminView.vue"),
      meta: { requiresAuth: true, requiresAdmin: true },
    },
    },
    //Single Player Game (no auth required)
    {
      path: '/game/single',
      name: 'singlePlayerGame',
      component: () => import('../views/GameView.vue'),
      props: { gameMode: 'single' }
    },
    //Multiplayer Game (auth required)
    {
      path: '/game/multiplayer',
      name: 'multiplayerGame',
      component: () => import('../views/GameView.vue'),
      meta: { requiresAuth: true },
      props: { gameMode: 'multiplayer' }
    },
    //Legacy Game route (redirect to dashboard for game selection)
    {
      path: '/game',
      name: 'game',
      redirect: '/dashboard'
    }
  ],
});

router.beforeEach(async (to, from, next) => {
<<<<<<< HEAD
  const userStore = useUserStore();
=======
  const userStore = useUserStore()
>>>>>>> origin/G3-commits

  if (to.meta.requiresAuth) {
    // If the user in on memory
    if (userStore.user) {
      // Check if admin access is required
      if (to.meta.requiresAdmin && userStore.user.type !== 'A') {
        return next('/dashboard');
      }
      return next();
    }

    // No user but has token
    if (localStorage.getItem("token")) {
      const success = await userStore.restoreToken();

      const success = await userStore.restoreToken()

      if (success) {
        // Check if admin access is required
        if (to.meta.requiresAdmin && userStore.user.type !== 'A') {
          return next('/dashboard');
        }
        return next();
      } else {
        return next("/login"); // Token Expired
      }
    }
    return next("/login");
  }

  if ((to.name === "login" || to.name === "register") && userStore.isAuthenticated) {
    return next("/dashboard");
  }
<<<<<<< HEAD
=======

  next()
})
>>>>>>> origin/G3-commits

  next();
});

export default router;
