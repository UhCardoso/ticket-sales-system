import { createApp } from 'vue'
import App from './App.vue'
import router from '@/config/Router'
import '@/assets/css/main.css'

createApp(App).use(router).mount('#app')
