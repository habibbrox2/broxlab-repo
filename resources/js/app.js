import Alpine from 'alpinejs';
import { initAlpineComponents } from './alpine-components.js';

window.Alpine = Alpine;

// Register data components BEFORE starting Alpine
initAlpineComponents();
Alpine.start();
