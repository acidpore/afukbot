import { createApp, h, defineAsyncComponent } from 'vue';

const appElement = document.getElementById('app');
const pageData = JSON.parse(appElement?.dataset.page || '{}');
const componentName = pageData.component || 'Landing';

// Auto-import components
const pages: Record<string, any> = {
    Landing: defineAsyncComponent(() => import('./Pages/Landing.vue')),
    Login: defineAsyncComponent(() => import('./Pages/Login.vue')),
    Dashboard: defineAsyncComponent(() => import('./Pages/Dashboard.vue')),
};

const app = createApp({
    render: () => h(pages[componentName])
});

app.mount('#app');
