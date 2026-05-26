import { createApp, h, defineAsyncComponent } from 'vue';
import axios from 'axios';

// Intercept 401 → redirect ke login
axios.interceptors.response.use(
    res => res,
    err => {
        if (err?.response?.status === 401 && window.location.pathname !== '/login') {
            window.location.href = '/login';
        }
        return Promise.reject(err);
    }
);

const appElement = document.getElementById('app');
const pageData = JSON.parse(appElement?.dataset.page || '{}');
const componentName = pageData.component || 'Landing';

// Auto-import components
const pages: Record<string, any> = {
    Landing:  defineAsyncComponent(() => import('./Pages/Landing.vue')),
    Login:    defineAsyncComponent(() => import('./Pages/Login.vue')),
    Register: defineAsyncComponent(() => import('./Pages/Register.vue')),
    Dashboard: defineAsyncComponent(() => import('./Pages/Dashboard.vue')),
};

const app = createApp({
    render: () => h(pages[componentName])
});

app.mount('#app');
