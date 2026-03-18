"""PDF Processing Module for Multimodal RAG"""
import logging
from typing import List, Optional, Union, Dict, Any
from pathlib import Path
from dataclasses import dataclass

logger = logging.getLogger(__name__)

# Try to import PDF processing libraries
try:
    import fitz  # PyMuPDF
    HAS_PYMUPDF = True
except ImportError:
    HAS_PYMUPDF = False

try:
    import pdfplumber
    HAS_PDFPLUMBER = True
except ImportError:
    HAS_PDFPLUMBER = False


class PDFProcessor:
    """Extract text from PDF documents with multiple fallback options."""

    def __init__(self, extract_images=False):
        self.extract_images = extract_images

    def process_pdf(self, pdf_path: Union[str, Path]) -> str:
        """Extract text from PDF file."""
        pdf_path = Path(pdf_path)
        if not pdf_path.exists():
            raise FileNotFoundError(f"PDF file not found: {pdf_path}")

        # Try PyMuPDF first (fastest and best quality)
        if HAS_PYMUPDF:
            return self._extract_pymupdf(pdf_path)

        # Fallback to pdfplumber
        if HAS_PDFPLUMBER:
            return self._extract_pdfplumber(pdf_path)

        # Last resort: use command line tool
        return self._extract_pdftotext(pdf_path)

    def _extract_pymupdf(self, pdf_path: Path) -> str:
        """Extract using PyMuPDF (fitz)."""
        text_parts = []
        doc = fitz.open(pdf_path)
        for page_num in range(len(doc)):
            page = doc[page_num]
            text = page.get_text("text")
            if text.strip():
                text_parts.append(f"--- Page {page_num + 1} ---\n{text}")
        doc.close()
        return "\n\n".join(text_parts)

    def _extract_pdfplumber(self, pdf_path: Path) -> str:
        """Extract using pdfplumber (good for tables)."""
        text_parts = []
        with pdfplumber.open(pdf_path) as pdf:
            for page_num, page in enumerate(pdf.pages):
                text = page.extract_text()
                if text:
                    text_parts.append(f"--- Page {page_num + 1} ---\n{text}")

                # Extract tables if present
                tables = page.extract_tables()
                for table_idx, table in enumerate(tables):
                    if table:
                        table_text = self._format_table(table)
                        text_parts.append(f"--- Table {table_idx + 1} ---\n{table_text}")

        return "\n\n".join(text_parts)

    def _extract_pdftotext(self, pdf_path: Path) -> str:
        """Fallback: use pdftotext command line tool."""
        import subprocess
        temp_file = str(pdf_path) + ".txt"
        try:
            result = subprocess.run(
                ["pdftotext", "-layout", str(pdf_path), temp_file],
                capture_output=True,
                text=True
            )
            if Path(temp_file).exists():
                with open(temp_file, 'r', encoding='utf-8') as f:
                    text = f.read()
                Path(temp_file).unlink()
                return text
        except FileNotFoundError:
            pass

        raise RuntimeError("No PDF extraction method available. Install pymupdf or pdfplumber.")

    def _format_table(self, table: list) -> str:
        """Format table data as readable text."""
        lines = []
        for row in table:
            if row:
                cleaned = [cell.strip() if cell else "" for cell in row]
                lines.append(" | ".join(cleaned))
        return "\n".join(lines)

    def extract_pages(self, pdf_path: Union[str, Path]) -> List[Dict[str, Any]]:
        """Extract text page by page."""
        pdf_path = Path(pdf_path)
        if not pdf_path.exists():
            raise FileNotFoundError(f"PDF file not found: {pdf_path}")

        pages = []
        if HAS_PYMUPDF:
            doc = fitz.open(pdf_path)
            for page_num in range(len(doc)):
                page = doc[page_num]
                text = page.get_text("text")
                pages.append({
                    'page_num': page_num + 1,
                    'text': text.strip()
                })
            doc.close()
        elif HAS_PDFPLUMBER:
            with pdfplumber.open(pdf_path) as pdf:
                for page_num, page in enumerate(pdf.pages):
                    text = page.extract_text()
                    pages.append({
                        'page_num': page_num + 1,
                        'text': text.strip() if text else ""
                    })
        else:
            # Fallback to single extraction
            pages.append({'page_num': 1, 'text': self.process_pdf(pdf_path)})

        return pages


class PDFIngester:
    def __init__(self, pdf_processor: Optional[PDFProcessor] = None):
        self.pdf_processor = pdf_processor or PDFProcessor()

    def ingest_pdf(self, pdf_path: Union[str, Path], source_name: Optional[str] = None) -> dict:
        """Ingest a PDF file and return extracted text with metadata."""
        pdf_path = Path(pdf_path)
        source_name = source_name or pdf_path.name

        extracted_text = self.pdf_processor.process_pdf(pdf_path)
        pages = self.pdf_processor.extract_pages(pdf_path)

        return {
            "text": extracted_text,
            "page_count": len(pages),
            "metadata": {
                "source": source_name,
                "type": "pdf",
                "filename": pdf_path.name,
                "pages": pages
            }
        }

    def ingest_pdfs_from_directory(self, directory: Union[str, Path], extensions=None) -> List[dict]:
        """Ingest all PDF files from a directory."""
        directory = Path(directory)
        extensions = extensions or [".pdf"]

        results = []
        for ext in extensions:
            for pdf_path in directory.glob(f"*{ext}"):
                try:
                    result = self.ingest_pdf(pdf_path)
                    results.append(result)
                except Exception as e:
                    logger.error(f"Failed to ingest {pdf_path}: {e}")

        logger.info(f"Ingested {len(results)} PDFs from {directory}")
        return results 
