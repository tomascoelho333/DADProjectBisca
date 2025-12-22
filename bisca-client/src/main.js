//import './assets/main.css'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import axios from 'axios'

const PORT = 8000

axios.defaults.baseURL = `http://127.0.0.1:${PORT}` 
axios.defaults.headers.common['Accept'] = 'application/json'

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')