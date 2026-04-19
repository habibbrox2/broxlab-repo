/**
 * EventEmitter - Pub/Sub Pattern Implementation
 * Path: /public_html/ai/js/modules/utils/EventEmitter.js
 *
 * Simple event emitter for decoupled communication between modules
 */

export class EventEmitter {
    constructor() {
        this.events = new Map();
    }

    /**
     * Register event listener
     */
    on(eventName, handler) {
        if (!this.events.has(eventName)) {
            this.events.set(eventName, []);
        }
        this.events.get(eventName).push(handler);

        // Return unsubscribe function
        return () => this.off(eventName, handler);
    }

    /**
     * Register one-time event listener
     */
    once(eventName, handler) {
        const wrapper = (...args) => {
            handler(...args);
            this.off(eventName, wrapper);
        };
        return this.on(eventName, wrapper);
    }

    /**
     * Remove event listener
     */
    off(eventName, handler) {
        if (!this.events.has(eventName)) return;

        const handlers = this.events.get(eventName);
        const index = handlers.indexOf(handler);
        if (index > -1) {
            handlers.splice(index, 1);
        }

        if (handlers.length === 0) {
            this.events.delete(eventName);
        }
    }

    /**
     * Remove all listeners for event (or all events if no event specified)
     */
    removeAllListeners(eventName) {
        if (eventName) {
            this.events.delete(eventName);
        } else {
            this.events.clear();
        }
    }

    /**
     * Emit event
     */
    emit(eventName, ...args) {
        if (!this.events.has(eventName)) return false;

        const handlers = this.events.get(eventName);
        handlers.forEach((handler) => {
            try {
                handler(...args);
            } catch (e) {
                console.error(`[EventEmitter] Error in handler for "${eventName}":`, e);
            }
        });

        return true;
    }

    /**
     * Get listener count for event
     */
    listenerCount(eventName) {
        return this.events.has(eventName) ? this.events.get(eventName).length : 0;
    }

    /**
     * Get all event names
     */
    eventNames() {
        return Array.from(this.events.keys());
    }

    /**
     * Get listeners for event
     */
    listeners(eventName) {
        return this.events.has(eventName) ? [...this.events.get(eventName)] : [];
    }
}

export default EventEmitter;
