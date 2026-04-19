/**
 * StorageManager - Persistence Layer
 * Path: /public_html/ai/js/modules/services/StorageManager.js
 *
 * Handles:
 *  - localStorage (settings, preferences)
 *  - sessionStorage (temporary data)
 *  - IndexedDB (large data, conversations)
 */

export class StorageManager {
    constructor(config = {}) {
        this.config = {
            dbName: 'BroxAI',
            dbVersion: 1,
            storeName: 'conversations',
            ...config,
        };

        this.db = null;
        this.initDb();
    }

    /**
     * Initialize IndexedDB
     */
    initDb() {
        return new Promise((resolve, reject) => {
            if (!window.indexedDB) {
                console.warn('[StorageManager] IndexedDB not available');
                resolve(null);
                return;
            }

            const request = indexedDB.open(this.config.dbName, this.config.dbVersion);

            request.onerror = () => {
                console.error('[StorageManager] Failed to open IndexedDB:', request.error);
                reject(request.error);
            };

            request.onsuccess = () => {
                this.db = request.result;
                console.log('[StorageManager] IndexedDB initialized');
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                if (!db.objectStoreNames.contains(this.config.storeName)) {
                    db.createObjectStore(this.config.storeName, { keyPath: 'id' });
                    console.log('[StorageManager] Object store created');
                }
            };
        });
    }

    /**
     * Save to localStorage
     */
    setLocal(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            console.log('[StorageManager] Saved to localStorage:', key);
        } catch (e) {
            console.error('[StorageManager] localStorage error:', e);
            // Fallback to sessionStorage if quota exceeded
            try {
                sessionStorage.setItem(key, JSON.stringify(value));
            } catch (e2) {
                console.error('[StorageManager] Storage quota exceeded');
            }
        }
    }

    /**
     * Get from localStorage
     */
    getLocal(key) {
        try {
            const value = localStorage.getItem(key);
            return value ? JSON.parse(value) : null;
        } catch (e) {
            console.error('[StorageManager] localStorage read error:', e);
            return null;
        }
    }

    /**
     * Remove from localStorage
     */
    removeLocal(key) {
        try {
            localStorage.removeItem(key);
        } catch (e) {
            console.error('[StorageManager] localStorage remove error:', e);
        }
    }

    /**
     * Clear localStorage
     */
    clearLocal() {
        try {
            localStorage.clear();
            console.log('[StorageManager] localStorage cleared');
        } catch (e) {
            console.error('[StorageManager] localStorage clear error:', e);
        }
    }

    /**
     * Save to IndexedDB
     */
    async setDb(key, value) {
        if (!this.db) return null;

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.config.storeName], 'readwrite');
            const store = transaction.objectStore(this.config.storeName);
            const request = store.put({ id: key, data: value, timestamp: Date.now() });

            request.onerror = () => {
                console.error('[StorageManager] IndexedDB write error:', request.error);
                reject(request.error);
            };

            request.onsuccess = () => {
                console.log('[StorageManager] Saved to IndexedDB:', key);
                resolve(value);
            };
        });
    }

    /**
     * Get from IndexedDB
     */
    async getDb(key) {
        if (!this.db) return null;

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.config.storeName], 'readonly');
            const store = transaction.objectStore(this.config.storeName);
            const request = store.get(key);

            request.onerror = () => {
                console.error('[StorageManager] IndexedDB read error:', request.error);
                reject(request.error);
            };

            request.onsuccess = () => {
                resolve(request.result?.data || null);
            };
        });
    }

    /**
     * Get all from IndexedDB
     */
    async getAllDb() {
        if (!this.db) return [];

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.config.storeName], 'readonly');
            const store = transaction.objectStore(this.config.storeName);
            const request = store.getAll();

            request.onerror = () => {
                console.error('[StorageManager] IndexedDB read error:', request.error);
                reject(request.error);
            };

            request.onsuccess = () => {
                resolve(request.result.map((r) => r.data));
            };
        });
    }

    /**
     * Remove from IndexedDB
     */
    async removeDb(key) {
        if (!this.db) return null;

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.config.storeName], 'readwrite');
            const store = transaction.objectStore(this.config.storeName);
            const request = store.delete(key);

            request.onerror = () => {
                console.error('[StorageManager] IndexedDB delete error:', request.error);
                reject(request.error);
            };

            request.onsuccess = () => {
                console.log('[StorageManager] Deleted from IndexedDB:', key);
                resolve(true);
            };
        });
    }

    /**
     * Clear IndexedDB
     */
    async clearDb() {
        if (!this.db) return null;

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.config.storeName], 'readwrite');
            const store = transaction.objectStore(this.config.storeName);
            const request = store.clear();

            request.onerror = () => {
                console.error('[StorageManager] IndexedDB clear error:', request.error);
                reject(request.error);
            };

            request.onsuccess = () => {
                console.log('[StorageManager] IndexedDB cleared');
                resolve(true);
            };
        });
    }

    /**
     * Save conversation
     */
    async saveConversation(conversation) {
        const key = `conversation:${conversation.id}`;
        return await this.setDb(key, conversation);
    }

    /**
     * Get conversation
     */
    async getConversation(id) {
        const key = `conversation:${id}`;
        return await this.getDb(key);
    }

    /**
     * Get all conversations
     */
    async getAllConversations() {
        if (!this.db) return [];

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.config.storeName], 'readonly');
            const store = transaction.objectStore(this.config.storeName);
            const request = store.getAll();

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                const conversations = request.result
                    .filter((r) => r.id.startsWith('conversation:'))
                    .map((r) => r.data);
                resolve(conversations);
            };
        });
    }

    /**
     * Delete conversation
     */
    async deleteConversation(id) {
        const key = `conversation:${id}`;
        return await this.removeDb(key);
    }

    /**
     * Get storage size
     */
    getStorageSize() {
        try {
            let size = 0;

            // localStorage size
            for (let key in localStorage) {
                if (localStorage.hasOwnProperty(key)) {
                    size += localStorage[key].length + key.length;
                }
            }

            // sessionStorage size
            for (let key in sessionStorage) {
                if (sessionStorage.hasOwnProperty(key)) {
                    size += sessionStorage[key].length + key.length;
                }
            }

            return (size / 1024).toFixed(2) + ' KB';
        } catch (e) {
            return 'Unknown';
        }
    }

    /**
     * Export all data
     */
    async exportData() {
        const data = {
            localStorage: { ...localStorage },
            sessionStorage: { ...sessionStorage },
            indexedDB: await this.getAllDb(),
            timestamp: new Date().toISOString(),
        };
        return data;
    }

    /**
     * Import data
     */
    async importData(data) {
        try {
            if (data.localStorage) {
                Object.entries(data.localStorage).forEach(([key, value]) => {
                    this.setLocal(key, value);
                });
            }

            if (data.indexedDB) {
                for (const item of data.indexedDB) {
                    await this.setDb(item.id, item.data);
                }
            }

            console.log('[StorageManager] Data imported successfully');
            return true;
        } catch (e) {
            console.error('[StorageManager] Data import failed:', e);
            return false;
        }
    }
}

export default StorageManager;
