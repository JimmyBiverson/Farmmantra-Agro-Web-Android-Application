//
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import '@fortawesome/fontawesome-free/css/all.min.css';
import { initializeWorkspace } from './workspace';
window.Alpine = Alpine;
window.Chart = Chart;
document.addEventListener('DOMContentLoaded', () => {
    Alpine.start();
    initializeWorkspace();
    const meta = name => document.querySelector(`meta[name="${name}"]`)?.content;
    if (meta('fm-user') && meta('fm-reverb-key')) {
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: meta('fm-reverb-key'),
            wsHost: window.location.hostname,
            wsPort: Number(meta('fm-reverb-port') || 8080),
            wssPort: Number(meta('fm-reverb-port') || 443),
            forceTLS: meta('fm-reverb-scheme') === 'https',
            enabledTransports: ['ws', 'wss'],
            auth: { headers: { 'X-CSRF-TOKEN': meta('csrf-token') } },
        });
        window.Echo.private(`App.Models.User.${meta('fm-user')}`)
            .listen('.notification.received', () => {
                window.dispatchEvent(new Event('farmmantra-notification'));
            });
    }
});
