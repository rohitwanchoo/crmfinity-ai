import './bootstrap';

import Alpine from 'alpinejs';

// Initialize sidebar store before Alpine starts
Alpine.store('sidebar', {
    open: false,
    toggle() {
        this.open = !this.open;
    }
});

window.Alpine = Alpine;

Alpine.start();
