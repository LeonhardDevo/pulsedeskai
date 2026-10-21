import { createApp } from 'vue';
import App from './App.vue';

const mountEl = document.getElementById('pulsedesk-vue-root');
if (mountEl) {
    createApp(App).mount(mountEl);
}
