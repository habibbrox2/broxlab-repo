/**
 * ImageContextManager - Multi-Image Upload & OCR Context Tracking
 * Path: /public_html/ai/js/modules/handlers/ImageContextManager.js
 *
 * Handles:
 *  - Multi-image upload and validation
 *  - Image metadata tracking
 *  - Base64 encoding for API transmission
 *  - OCR text extraction and caching
 *  - Image context management
 */

export class ImageContextManager {
    constructor(config = {}) {
        this.config = {
            maxImages: 5,
            maxImageSize: 5 * 1024 * 1024, // 5MB
            allowedTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            autoOCR: true,
            ...config,
        };

        this.images = [];
        this.ocrCache = new Map();
        this.eventHandlers = new Map();
    }

    /**
     * Add image from File or URL
     */
    async addImage(source, metadata = {}) {
        try {
            // Check max images limit
            if (this.images.length >= this.config.maxImages) {
                throw new Error(`Maximum ${this.config.maxImages} images allowed`);
            }

            let imageData;

            // Handle File object
            if (source instanceof File) {
                imageData = await this._processFile(source);
            }
            // Handle URL
            else if (typeof source === 'string') {
                imageData = await this._processUrl(source);
            }
            // Handle canvas element
            else if (source instanceof HTMLCanvasElement) {
                imageData = await this._processCanvas(source);
            } else {
                throw new Error('Invalid image source');
            }

            // Add metadata
            imageData = {
                ...imageData,
                id: this._generateId(),
                timestamp: new Date().toISOString(),
                ...metadata,
            };

            this.images.push(imageData);
            console.log('[ImageContextManager] Image added:', imageData.id);

            // Trigger event
            this._emit('image:added', imageData);

            // Auto OCR if enabled
            if (this.config.autoOCR) {
                await this.extractOCR(imageData.id);
            }

            return imageData;
        } catch (e) {
            console.error('[ImageContextManager] Failed to add image:', e.message);
            this._emit('image:error', e);
            throw e;
        }
    }

    /**
     * Process File object
     */
    async _processFile(file) {
        // Validate file type
        if (!this.config.allowedTypes.includes(file.type)) {
            throw new Error(`Invalid file type: ${file.type}`);
        }

        // Validate file size
        if (file.size > this.config.maxImageSize) {
            throw new Error(`File too large: ${(file.size / 1024 / 1024).toFixed(2)}MB`);
        }

        // Convert to base64
        const base64 = await this._fileToBase64(file);

        // Get image dimensions
        const dimensions = await this._getImageDimensions(base64, file.type);

        return {
            filename: file.name,
            type: file.type,
            size: file.size,
            base64,
            ...dimensions,
            source: 'file',
        };
    }

    /**
     * Process URL
     */
    async _processUrl(url) {
        try {
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const blob = await response.blob();
            const file = new File([blob], url.split('/').pop(), { type: blob.type });

            return await this._processFile(file);
        } catch (e) {
            throw new Error(`Failed to fetch image from URL: ${e.message}`);
        }
    }

    /**
     * Process canvas element
     */
    async _processCanvas(canvas) {
        const base64 = canvas.toDataURL('image/png');
        const dimensions = await this._getImageDimensions(base64, 'image/png');

        return {
            filename: 'canvas-' + Date.now() + '.png',
            type: 'image/png',
            size: base64.length,
            base64,
            ...dimensions,
            source: 'canvas',
        };
    }

    /**
     * Convert File to base64
     */
    _fileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = () => reject(new Error('Failed to read file'));
            reader.readAsDataURL(file);
        });
    }

    /**
     * Get image dimensions
     */
    _getImageDimensions(base64, type) {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                resolve({
                    width: img.naturalWidth,
                    height: img.naturalHeight,
                    aspectRatio: img.naturalWidth / img.naturalHeight,
                });
            };
            img.onerror = () => {
                resolve({ width: 0, height: 0, aspectRatio: 1 });
            };
            img.src = base64;
        });
    }

    /**
     * Extract text via OCR (client-side or server-side)
     */
    async extractOCR(imageId) {
        try {
            const image = this.getImage(imageId);
            if (!image) {
                throw new Error('Image not found');
            }

            // Check cache first
            if (this.ocrCache.has(imageId)) {
                console.log('[ImageContextManager] OCR text from cache:', imageId);
                return this.ocrCache.get(imageId);
            }

            // Client-side OCR simulation (placeholder for Tesseract.js integration)
            // In production, this could use Tesseract.js or send to server
            const ocrText = await this._performOCR(image.base64);

            // Cache result
            this.ocrCache.set(imageId, ocrText);
            image.ocrText = ocrText;

            console.log('[ImageContextManager] OCR extracted:', imageId, `(${ocrText.length} chars)`);
            this._emit('ocr:completed', { imageId, text: ocrText });

            return ocrText;
        } catch (e) {
            console.error('[ImageContextManager] OCR failed:', e.message);
            this._emit('ocr:error', e);
            throw e;
        }
    }

    /**
     * Perform OCR (placeholder - can integrate Tesseract.js or server-side)
     */
    async _performOCR(base64) {
        // Placeholder for actual OCR implementation
        // In production, could:
        // 1. Use Tesseract.js (client-side)
        // 2. Send to backend for OCR
        // 3. Use Google Vision API
        console.log('[ImageContextManager] OCR processing:', base64.substring(0, 50) + '...');

        // For now, return placeholder
        return '[OCR Text - Configure actual OCR provider]';
    }

    /**
     * Get image by ID
     */
    getImage(imageId) {
        return this.images.find((img) => img.id === imageId);
    }

    /**
     * Get all images
     */
    getImages() {
        return [...this.images];
    }

    /**
     * Get images as payload for API
     */
    getImagesPayload() {
        return this.images.map((img) => ({
            id: img.id,
            filename: img.filename,
            type: img.type,
            base64: img.base64,
            width: img.width,
            height: img.height,
            ocrText: img.ocrText || null,
        }));
    }

    /**
     * Remove image by ID
     */
    removeImage(imageId) {
        const index = this.images.findIndex((img) => img.id === imageId);
        if (index === -1) {
            throw new Error('Image not found');
        }

        const removed = this.images.splice(index, 1)[0];
        this.ocrCache.delete(imageId);

        console.log('[ImageContextManager] Image removed:', imageId);
        this._emit('image:removed', removed);

        return removed;
    }

    /**
     * Clear all images
     */
    clearImages() {
        const count = this.images.length;
        this.images = [];
        this.ocrCache.clear();

        console.log('[ImageContextManager] Cleared', count, 'images');
        this._emit('images:cleared', count);
    }

    /**
     * Get image count
     */
    getImageCount() {
        return this.images.length;
    }

    /**
     * Check if at max capacity
     */
    isAtCapacity() {
        return this.images.length >= this.config.maxImages;
    }

    /**
     * Get remaining slots
     */
    getRemainingSlots() {
        return this.config.maxImages - this.images.length;
    }

    /**
     * Validate image (without adding)
     */
    validateImage(file) {
        if (!this.config.allowedTypes.includes(file.type)) {
            return {
                valid: false,
                error: `Invalid file type: ${file.type}`,
            };
        }

        if (file.size > this.config.maxImageSize) {
            return {
                valid: false,
                error: `File too large: ${(file.size / 1024 / 1024).toFixed(2)}MB`,
            };
        }

        if (this.images.length >= this.config.maxImages) {
            return {
                valid: false,
                error: `Maximum ${this.config.maxImages} images allowed`,
            };
        }

        return { valid: true };
    }

    /**
     * Get total size of all images
     */
    getTotalSize() {
        const totalBytes = this.images.reduce((sum, img) => sum + img.size, 0);
        return (totalBytes / 1024 / 1024).toFixed(2) + ' MB';
    }

    /**
     * Export images as JSON
     */
    exportImages() {
        return {
            timestamp: new Date().toISOString(),
            count: this.images.length,
            images: this.images.map((img) => ({
                id: img.id,
                filename: img.filename,
                type: img.type,
                dimensions: {
                    width: img.width,
                    height: img.height,
                    aspectRatio: img.aspectRatio,
                },
                size: img.size,
                source: img.source,
                ocrText: this.ocrCache.get(img.id) || null,
            })),
        };
    }

    /**
     * Event handler registration
     */
    on(eventName, callback) {
        if (!this.eventHandlers.has(eventName)) {
            this.eventHandlers.set(eventName, []);
        }
        this.eventHandlers.get(eventName).push(callback);

        return () => {
            const handlers = this.eventHandlers.get(eventName);
            const index = handlers.indexOf(callback);
            if (index > -1) {
                handlers.splice(index, 1);
            }
        };
    }

    /**
     * Emit event
     */
    _emit(eventName, data) {
        const handlers = this.eventHandlers.get(eventName);
        if (handlers) {
            handlers.forEach((callback) => {
                try {
                    callback(data);
                } catch (e) {
                    console.error('[ImageContextManager] Event handler error:', e);
                }
            });
        }
    }

    /**
     * Generate unique ID
     */
    _generateId() {
        return 'img-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Reset manager
     */
    reset() {
        this.clearImages();
        this.eventHandlers.clear();
        console.log('[ImageContextManager] Manager reset');
    }
}

export default ImageContextManager;
