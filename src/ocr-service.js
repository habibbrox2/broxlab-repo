import express from 'express';
import multer from 'multer';
import path from 'path';
import fs from 'fs';
import { createWorker } from 'tesseract.js';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.OCR_SERVICE_PORT || 7020;

// Configure multer for file uploads
// Use /storage/ocr-temp for persistent storage across deployments
const upload = multer({
    dest: '/storage/ocr-temp',
    limits: { fileSize: 50 * 1024 * 1024 } // 50MB limit
});

// Ensure upload directory exists
const uploadDir = '/storage/ocr-temp';
if (!fs.existsSync(uploadDir)) {
    fs.mkdirSync(uploadDir, { recursive: true });
}

// Middleware
app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ limit: '50mb', extended: true }));

// Store worker instances per language
const workers = {};

/**
 * Get or create a Tesseract worker for a specific language
 */
async function getWorker(lang = 'eng') {
    if (!workers[lang]) {
        console.log(`Creating Tesseract worker for language: ${lang}`);
        const worker = await createWorker(lang);
        workers[lang] = worker;
    }
    return workers[lang];
}

/**
 * Health check endpoint
 */
app.get('/ocr/health', (req, res) => {
    res.json({
        status: 'ok',
        service: 'Tesseract.js OCR Service',
        port: PORT,
        engines: ['tesseract.js'],
        version: '1.0.0',
        timestamp: new Date().toISOString()
    });
});

/**
 * Extract text from image using Tesseract.js
 * POST /ocr/tesseract/image
 * Accepts: base64 image in JSON or file upload
 */
app.post('/ocr/tesseract/image', upload.single('image'), async (req, res) => {
    try {
        const lang = req.body.lang || req.query.lang || 'eng';
        let imagePath = null;
        let base64Data = null;

        // Handle file upload
        if (req.file) {
            imagePath = req.file.path;
        }
        // Handle base64 in JSON body
        else if (req.body.image) {
            base64Data = req.body.image;
        }
        // Handle base64 in query param
        else if (req.query.image) {
            base64Data = req.query.image;
        }
        else {
            return res.status(400).json({
                success: false,
                error: 'No image provided. Send as file upload or base64 string in "image" field'
            });
        }

        console.log(`Processing Tesseract OCR request for language: ${lang}`);
        const worker = await getWorker(lang);

        let result;
        if (imagePath) {
            result = await worker.recognize(imagePath);
        } else {
            // Convert base64 to Buffer and then to image
            const buffer = Buffer.from(base64Data, 'base64');
            result = await worker.recognize(buffer);
        }

        // Clean up temp file if uploaded
        if (imagePath && fs.existsSync(imagePath)) {
            fs.unlinkSync(imagePath);
        }

        res.json({
            success: true,
            engine: 'tesseract.js',
            text: result.data.text,
            confidence: result.data.confidence,
            language: lang,
            boxes: result.data.lines,
            words: result.data.words,
            timestamp: new Date().toISOString()
        });
    } catch (error) {
        console.error('Tesseract OCR error:', error.message);

        // Clean up temp file on error
        if (req.file && fs.existsSync(req.file.path)) {
            fs.unlinkSync(req.file.path);
        }

        res.status(500).json({
            success: false,
            error: error.message || 'OCR processing failed',
            engine: 'tesseract.js'
        });
    }
});

/**
 * Batch process multiple images
 * POST /ocr/tesseract/batch
 * Accepts: array of base64 images or file uploads
 */
app.post('/ocr/tesseract/batch', upload.array('images'), async (req, res) => {
    try {
        const lang = req.body.lang || 'eng';
        const images = req.body.images || []; // Array of base64 strings
        const files = req.files || []; // Uploaded files

        if (images.length === 0 && files.length === 0) {
            return res.status(400).json({
                success: false,
                error: 'No images provided for batch processing'
            });
        }

        console.log(`Batch processing ${images.length + files.length} images with language: ${lang}`);
        const worker = await getWorker(lang);
        const results = [];

        // Process base64 images
        for (let i = 0; i < images.length; i++) {
            try {
                const buffer = Buffer.from(images[i], 'base64');
                const result = await worker.recognize(buffer);
                results.push({
                    index: i,
                    success: true,
                    text: result.data.text,
                    confidence: result.data.confidence
                });
            } catch (error) {
                results.push({
                    index: i,
                    success: false,
                    error: error.message
                });
            }
        }

        // Process uploaded files
        for (let i = 0; i < files.length; i++) {
            try {
                const result = await worker.recognize(files[i].path);
                results.push({
                    filename: files[i].originalname,
                    success: true,
                    text: result.data.text,
                    confidence: result.data.confidence
                });

                // Clean up temp file
                if (fs.existsSync(files[i].path)) {
                    fs.unlinkSync(files[i].path);
                }
            } catch (error) {
                results.push({
                    filename: files[i].originalname,
                    success: false,
                    error: error.message
                });

                if (fs.existsSync(files[i].path)) {
                    fs.unlinkSync(files[i].path);
                }
            }
        }

        res.json({
            success: true,
            engine: 'tesseract.js',
            totalProcessed: results.length,
            results: results,
            language: lang,
            timestamp: new Date().toISOString()
        });
    } catch (error) {
        console.error('Batch OCR error:', error.message);

        // Clean up all temp files on error
        if (req.files) {
            req.files.forEach(file => {
                if (fs.existsSync(file.path)) {
                    fs.unlinkSync(file.path);
                }
            });
        }

        res.status(500).json({
            success: false,
            error: error.message || 'Batch OCR processing failed',
            engine: 'tesseract.js'
        });
    }
});

/**
 * Auto-detect language and extract text
 * POST /ocr/auto
 * Prioritizes Tesseract.js, falls back to OCR.space API
 */
app.post('/ocr/auto', upload.single('image'), async (req, res) => {
    try {
        const lang = req.body.lang || 'eng';
        let imagePath = null;
        let base64Data = null;

        // Handle file upload
        if (req.file) {
            imagePath = req.file.path;
        }
        // Handle base64 in JSON body
        else if (req.body.image) {
            base64Data = req.body.image;
        }
        // Handle base64 in query param
        else if (req.query.image) {
            base64Data = req.query.image;
        }
        else {
            return res.status(400).json({
                success: false,
                error: 'No image provided'
            });
        }

        console.log(`Auto OCR processing with language: ${lang}`);

        // Try Tesseract.js first
        try {
            const worker = await getWorker(lang);
            let result;

            if (imagePath) {
                result = await worker.recognize(imagePath);
            } else {
                const buffer = Buffer.from(base64Data, 'base64');
                result = await worker.recognize(buffer);
            }

            // Clean up temp file if uploaded
            if (imagePath && fs.existsSync(imagePath)) {
                fs.unlinkSync(imagePath);
            }

            return res.json({
                success: true,
                engine: 'tesseract.js',
                text: result.data.text,
                confidence: result.data.confidence,
                language: lang,
                timestamp: new Date().toISOString()
            });
        } catch (tesseractError) {
            console.warn('Tesseract.js processing failed, would fallback to OCR.space:', tesseractError.message);

            // Clean up temp file if uploaded
            if (imagePath && fs.existsSync(imagePath)) {
                fs.unlinkSync(imagePath);
            }

            // Fallback would normally be to OCR.space API
            return res.status(500).json({
                success: false,
                error: tesseractError.message,
                engine: 'tesseract.js',
                note: 'Tesseract.js failed. PHP backend should fallback to OCR.space API.'
            });
        }
    } catch (error) {
        console.error('Auto OCR error:', error.message);

        // Clean up temp file on error
        if (req.file && fs.existsSync(req.file.path)) {
            fs.unlinkSync(req.file.path);
        }

        res.status(500).json({
            success: false,
            error: error.message || 'OCR processing failed',
            engine: 'auto'
        });
    }
});

/**
 * Graceful shutdown with worker cleanup
 */
async function shutdown() {
    console.log('\nShutting down OCR service gracefully...');
    for (const [lang, worker] of Object.entries(workers)) {
        try {
            console.log(`Terminating worker for language: ${lang}`);
            await worker.terminate();
        } catch (error) {
            console.error(`Error terminating worker for ${lang}:`, error.message);
        }
    }
    process.exit(0);
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);

// Start server
const server = app.listen(PORT, () => {
    console.log(`✓ Tesseract.js OCR Service listening on port ${PORT}`);
    console.log(`✓ Health check: GET http://localhost:${PORT}/ocr/health`);
    console.log(`✓ Single image OCR: POST http://localhost:${PORT}/ocr/tesseract/image`);
    console.log(`✓ Batch OCR: POST http://localhost:${PORT}/ocr/tesseract/batch`);
    console.log(`✓ Auto OCR: POST http://localhost:${PORT}/ocr/auto`);
});

// Handle server errors
server.on('error', (error) => {
    if (error.code === 'EADDRINUSE') {
        console.error(`✗ Port ${PORT} is already in use. Try a different port.`);
        console.error(`  Set OCR_SERVICE_PORT environment variable to use a different port.`);
    } else {
        console.error('Server error:', error.message);
    }
    process.exit(1);
});

export default app;
