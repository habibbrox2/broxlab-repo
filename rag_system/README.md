# Multimodal RAG System

## Quick Start

```bash
# Install dependencies
pip install -r requirements.txt

# Process a PDF
python pdf_processor.py document.pdf

# Process an image with OCR
python image_processor.py image.png
```

## Components

| Script | Purpose |
|--------|---------|
| `processing/pipeline.py` | Main RAG pipeline with text chunking, embeddings, vector store |
| `pdf_processor.py` | Extract text from PDF documents |
| `image_processor.py` | OCR for images (receipts, documents, photos) |

## Usage

### Python API

```python
from processing.pipeline import MultimodalRAGPipeline

pipeline = MultimodalRAGPipeline()
pipeline.initialize()

# Ingest text
pipeline.ingest_text("Your content here", "source")

# Search
results = pipeline.similarity_search("your query")
```

### Command Line

```bash
# Extract text from PDF
python pdf_processor.py input.pdf

# Extract text from image
python image_processor.py input.png
```

---

# Multimodal RAG System  
  
Complete Multimodal RAG system with LangChain, supporting text, image, and PDF inputs. 
