"""Image Processing Module for Multimodal RAG
Supports OCR for images (receipts, documents, photos) with multiple engines.
"""

import logging
from typing import List, Optional, Union, Dict, Any
from pathlib import Path
from PIL import Image
import numpy as np

logger = logging.getLogger(__name__)

# Try to import OCR libraries
try:
    import pytesseract
    HAS_PYTESSERACT = True
except ImportError:
    HAS_PYTESSERACT = False

try:
    import easyocr
    HAS_EASYOCR = True
except ImportError:
    HAS_EASYOCR = False

try:
    import cv2
    HAS_OPENCV = True
except ImportError:
    HAS_OPENCV = False


class ImageProcessor:
    """Extract text from images using multiple OCR engines."""
    
    def __init__(self, engine="pytesseract", easyocr_languages=["en"], easyocr_gpu=False):
        self.engine = engine
        self.easyocr_languages = easyocr_languages
        self.easyocr_gpu = easyocr_gpu
        self._ocr_reader = None
    
    def _init_easyocr(self):
        """Initialize EasyOCR reader."""
        if self._ocr_reader is None:
            try:
                self._ocr_reader = easyocr.Reader(
                    self.easyocr_languages,
                    gpu=self.easyocr_gpu,
                    verbose=False
                )
                logger.info("EasyOCR initialized")
            except Exception as e:
                logger.error(f"Failed to initialize EasyOCR: {e}")
                raise
    
    def process_image(self, image_path: Union[str, Path]) -> str:
        """Extract text from image file."""
        image_path = Path(image_path)
        
        if not image_path.exists():
            raise FileNotFoundError(f"Image not found: {image_path}")
        
        # Load and preprocess image
        image = self._load_and_preprocess(image_path)
        
        if self.engine == "pytesseract":
            return self._process_with_pytesseract(image)
        elif self.engine == "easyocr":
            return self._process_with_easyocr(image_path)
        elif self.engine == "opencv":
            return self._process_with_opencv(image)
        else:
            raise ValueError(f"Unknown OCR engine: {self.engine}")
    
    def _load_and_preprocess(self, image_path: Path) -> Image.Image:
        """Load and preprocess image for better OCR results."""
        image = Image.open(image_path)
        
        # Convert to RGB if needed
        if image.mode != "RGB":
            image = image.convert("RGB")
        
        # Convert to numpy array for OpenCV processing
        if HAS_OPENCV:
            img_array = np.array(image)
            img_array = cv2.cvtColor(img_array, cv2.COLOR_RGB2BGR)
            
            # Preprocessing for better OCR
            gray = cv2.cvtColor(img_array, cv2.COLOR_BGR2GRAY)
            
            # Apply threshold to get binary image
            _, binary = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
            
            # Convert back to PIL Image
            processed_array = cv2.cvtColor(binary, cv2.COLOR_GRAY2RGB)
            image = Image.fromarray(processed_array)
        
        return image
    
    def _process_with_pytesseract(self, image: Image.Image) -> str:
        """Extract text using pytesseract."""
        if not HAS_PYTESSERACT:
            raise RuntimeError("pytesseract not installed. Install with: pip install pytesseract")
        
        try:
            # Configure pytesseract
            config = '--oem 3 --psm 6'
            text = pytesseract.image_to_string(image, config=config)
            logger.info("Text extracted using pytesseract")
            return text.strip()
        except Exception as e:
            logger.error(f"pytesseract error: {e}")
            return ""
    
    def _process_with_easyocr(self, image_path: Path) -> str:
        """Extract text using EasyOCR."""
        if not HAS_EASYOCR:
            raise RuntimeError("EasyOCR not installed. Install with: pip install easyocr")
        
        self._init_easyocr()
        try:
            results = self._ocr_reader.readtext(str(image_path))
            text_parts = []
            for bbox, text, confidence in results:
                if confidence > 0.3:
                    text_parts.append(text)
            text = " ".join(text_parts)
            logger.info("Text extracted using EasyOCR")
            return text.strip()
        except Exception as e:
            logger.error(f"EasyOCR error: {e}")
            return ""
    
    def _process_with_opencv(self, image: Image.Image) -> str:
        """Extract text using OpenCV preprocessing + pytesseract."""
        if not HAS_OPENCV:
            raise RuntimeError("OpenCV not installed. Install with: pip install opencv-python")
        
        if not HAS_PYTESSERACT:
            raise RuntimeError("pytesseract not installed. Install with: pip install pytesseract")
        
        try:
            # Convert PIL to OpenCV
            img_array = np.array(image)
            img_array = cv2.cvtColor(img_array, cv2.COLOR_RGB2BGR)
            
            # Convert to grayscale
            gray = cv2.cvtColor(img_array, cv2.COLOR_BGR2GRAY)
            
            # Apply various preprocessing techniques
            # 1. Noise removal
            denoised = cv2.medianBlur(gray, 3)
            
            # 2. Thresholding
            _, binary = cv2.threshold(denoised, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
            
            # 3. Morphological operations to clean up
            kernel = np.ones((1, 1), np.uint8)
            opening = cv2.morphologyEx(binary, cv2.MORPH_OPEN, kernel)
            closing = cv2.morphologyEx(opening, cv2.MORPH_CLOSE, kernel)
            
            # Convert back to PIL for pytesseract
            processed_array = cv2.cvtColor(closing, cv2.COLOR_GRAY2RGB)
            processed_image = Image.fromarray(processed_array)
            
            # Extract text
            config = '--oem 3 --psm 6'
            text = pytesseract.image_to_string(processed_image, config=config)
            logger.info("Text extracted using OpenCV + pytesseract")
            return text.strip()
        except Exception as e:
            logger.error(f"OpenCV processing error: {e}")
            return ""
    
    def process_images_batch(self, image_paths: List[Union[str, Path]]) -> List[str]:
        """Process multiple images and return extracted text."""
        results = []
        for path in image_paths:
            try:
                text = self.process_image(path)
                results.append(text)
            except Exception as e:
                logger.error(f"Failed to process {path}: {e}")
                results.append("")
        return results
    
    def extract_image_metadata(self, image_path: Union[str, Path]) -> dict:
        """Extract metadata from image file."""
        image_path = Path(image_path)
        image = Image.open(image_path)
        
        return {
            "filename": image_path.name,
            "format": image.format,
            "mode": image.mode,
            "width": image.width,
            "height": image.height,
            "size_bytes": image_path.stat().st_size,
            "dpi": image.info.get('dpi', (72, 72))
        }
    
    def detect_document_type(self, image_path: Union[str, Path]) -> str:
        """Detect the type of document in the image."""
        try:
            # Basic heuristics for document type detection
            metadata = self.extract_image_metadata(image_path)
            width, height = metadata['width'], metadata['height']
            
            # Aspect ratio analysis
            aspect_ratio = width / height
            
            if aspect_ratio > 0.6 and aspect_ratio < 0.8:
                return "document"
            elif aspect_ratio > 0.9 and aspect_ratio < 1.1:
                return "receipt"
            elif aspect_ratio > 1.2:
                return "photo"
            else:
                return "unknown"
        except Exception as e:
            logger.error(f"Document type detection failed: {e}")
            return "unknown"


class ImageIngester:
    """Ingest images for the multimodal RAG system."""
    
    def __init__(self, image_processor: Optional[ImageProcessor] = None):
        self.image_processor = image_processor or ImageProcessor()
    
    def ingest_image(self, image_path: Union[str, Path], source_name: Optional[str] = None) -> dict:
        """Ingest a single image and return extracted text with metadata."""
        source_name = source_name or Path(image_path).name
        
        try:
            extracted_text = self.image_processor.process_image(image_path)
            metadata = self.image_processor.extract_image_metadata(image_path)
            document_type = self.image_processor.detect_document_type(image_path)
            
            metadata.update({
                "source": source_name,
                "type": "image",
                "original_filename": Path(image_path).name,
                "document_type": document_type,
                "text_length": len(extracted_text),
                "has_text": bool(extracted_text.strip())
            })
            
            return {
                "text": extracted_text,
                "metadata": metadata
            }
        except Exception as e:
            logger.error(f"Failed to ingest image {image_path}: {e}")
            return {
                "text": "",
                "metadata": {
                    "source": source_name,
                    "type": "image",
                    "error": str(e)
                }
            }
    
    def ingest_images_from_directory(self, directory: Union[str, Path], extensions=None) -> List[dict]:
        """Ingest all images from a directory."""
        directory = Path(directory)
        extensions = extensions or [".jpg", ".jpeg", ".png", ".gif", ".bmp", ".webp", ".tiff", ".tif"]
        
        results = []
        for ext in extensions:
            for image_path in directory.glob(f"*{ext}"):
                try:
                    result = self.ingest_image(image_path)
                    results.append(result)
                except Exception as e:
                    logger.error(f"Failed to ingest {image_path}: {e}")
        
        logger.info(f"Ingested {len(results)} images from {directory}")
        return results
    
    def batch_ingest(self, image_paths: List[Union[str, Path]], source_prefix: str = "batch") -> List[dict]:
        """Ingest multiple images with batch processing."""
        results = []
        for i, image_path in enumerate(image_paths):
            source_name = f"{source_prefix}_{i}_{Path(image_path).name}"
            result = self.ingest_image(image_path, source_name)
            results.append(result)
        
        return results


def main():
    """Command line interface for image processing."""
    import sys
    import argparse
    
    parser = argparse.ArgumentParser(description="Process images for OCR")
    parser.add_argument("image_path", help="Path to image file")
    parser.add_argument("--engine", choices=["pytesseract", "easyocr", "opencv"], 
                       default="pytesseract", help="OCR engine to use")
    parser.add_argument("--languages", nargs="+", default=["en"], 
                       help="Languages for EasyOCR")
    parser.add_argument("--gpu", action="store_true", help="Use GPU for EasyOCR")
    
    args = parser.parse_args()
    
    try:
        processor = ImageProcessor(
            engine=args.engine,
            easyocr_languages=args.languages,
            easyocr_gpu=args.gpu
        )
        
        text = processor.process_image(args.image_path)
        print(text)
        
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()