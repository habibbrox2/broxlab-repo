"""Image Processing Module for Multimodal RAG"""
import logging
from typing import List, Optional, Union
from pathlib import Path
from PIL import Image

logger = logging.getLogger(__name__)

class ImageProcessor:
    def __init__(self, engine="pytesseract", easyocr_languages=["en"], easyocr_gpu=False):
        self.engine = engine
        self.easyocr_languages = easyocr_languages
        self.easyocr_gpu = easyocr_gpu
        self._ocr_reader = None
    
    def _init_easyocr(self):
        if self._ocr_reader is None:
            try:
                import easyocr
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
        image_path = Path(image_path)
        
        if not image_path.exists():
            raise FileNotFoundError(f"Image not found: {image_path}")
        
        image = Image.open(image_path)
        
        if image.mode != "RGB":
            image = image.convert("RGB")
        
        if self.engine == "pytesseract":
            return self._process_with_pytesseract(image)
        elif self.engine == "easyocr":
            return self._process_with_easyocr(image_path)
        else:
            raise ValueError(f"Unknown OCR engine: {self.engine}")
    
    def _process_with_pytesseract(self, image: Image.Image) -> str:
        try:
            import pytesseract
            text = pytesseract.image_to_string(image)
            logger.info("Text extracted using pytesseract")
            return text.strip()
        except Exception as e:
            logger.error(f"pytesseract error: {e}")
            return ""
    
    def _process_with_easyocr(self, image_path: Path) -> str:
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
    
    def process_images_batch(self, image_paths: List[Union[str, Path]]) -> List[str]:
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
        image_path = Path(image_path)
        image = Image.open(image_path)
        
        return {
            "filename": image_path.name,
            "format": image.format,
            "mode": image.mode,
            "width": image.width,
            "height": image.height,
            "size_bytes": image_path.stat().st_size
        }

class ImageIngester:
    def __init__(self, image_processor: Optional[ImageProcessor] = None):
        self.image_processor = image_processor or ImageProcessor()
    
    def ingest_image(self, image_path: Union[str, Path], source_name: Optional[str] = None) -> dict:
        source_name = source_name or Path(image_path).name
        
        extracted_text = self.image_processor.process_image(image_path)
        metadata = self.image_processor.extract_image_metadata(image_path)
        metadata["source"] = source_name
        metadata["type"] = "image"
        metadata["original_filename"] = Path(image_path).name
        
        return {
            "text": extracted_text,
            "metadata": metadata
        }
    
    def ingest_images_from_directory(self, directory: Union[str, Path], extensions=None) -> List[dict]:
        directory = Path(directory)
        extensions = extensions or [".jpg", ".jpeg", ".png", ".gif", ".bmp", ".webp"]
        
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