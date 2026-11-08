import './bootstrap';
// import './notifications'; // REMOVIDO - SweetAlert2 via CDN nos layouts (app.blade.php + guest.blade.php)

import Alpine from 'alpinejs';
import assistenteFacilita from './assistente-facilita';

window.Alpine = Alpine;

Alpine.data('assistenteFacilita', () => assistenteFacilita(window.facilitaAssistente || {}));

Alpine.start();
