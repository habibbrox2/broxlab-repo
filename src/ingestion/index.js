/**
 * Ingestion Module Index
 * Exports all ingestion components
 */

import { PDFProcessor, PDFIngester } from './pdf-processor.js';
import { ImageProcessor, ImageIngester } from './image-processor.js';

export { PDFProcessor, PDFIngester, ImageProcessor, ImageIngester };
export default {
    PDFProcessor,
    PDFIngester,
    ImageProcessor,
    ImageIngester
};