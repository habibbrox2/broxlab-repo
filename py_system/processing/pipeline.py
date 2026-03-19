"""Multimodal RAG Pipeline - Text Processing"""
from typing import List, Dict, Any, Optional
from dataclasses import dataclass, field
from langchain_core.documents import Document
from langchain_text_splitters import RecursiveCharacterTextSplitter
from langchain_community.embeddings import HuggingFaceEmbeddings
from langchain_community.vectorstores import Chroma, FAISS
from langchain_community.retrievers import BM25Retriever
import logging
import os

logger = logging.getLogger(__name__)

@dataclass
class ProcessedChunk:
    content: str
    source: str
    chunk_index: int
    metadata: Dict[str, Any] = field(default_factory=dict)

class TextProcessor:
    def __init__(self, chunk_size=1000, chunk_overlap=200):
        self.splitter = RecursiveCharacterTextSplitter(
            chunk_size=chunk_size,
            chunk_overlap=chunk_overlap,
            separators=["\n\n", "\n", ". ", "? ", "! ", " "],
            length_function=len,
        )
    
    def process_text(self, text: str, source: str) -> List[ProcessedChunk]:
        chunks = self.splitter.split_text(text)
        return [
            ProcessedChunk(
                content=chunk,
                source=source,
                chunk_index=i,
                metadata={"source": source, "chunk_index": i, "type": "text"}
            )
            for i, chunk in enumerate(chunks)
        ]
    
    def process_documents(self, documents: List[Document]) -> List[Document]:
        return self.splitter.split_documents(documents)

class EmbeddingManager:
    def __init__(self, model_name="sentence-transformers/all-MiniLM-L6-v2"):
        self.model_name = model_name
        self.embeddings = None
    
    def initialize(self):
        logger.info(f"Loading embedding model: {self.model_name}")
        self.embeddings = HuggingFaceEmbeddings(
            model_name=self.model_name,
            model_kwargs={"device": "cpu"},
            encode_kwargs={"normalize_embeddings": True}
        )
        logger.info("Embedding model loaded successfully")
        return self.embeddings
    
    def get_embeddings(self):
        if self.embeddings is None:
            self.initialize()
        return self.embeddings
    
    def embed_query(self, query: str):
        return self.get_embeddings().embed_query(query)
    
    def embed_documents(self, documents: List[str]):
        return self.get_embeddings().embed_documents(documents)

# Vector Store Manager
from pathlib import Path

class VectorStoreManager:
    def __init__(self, embeddings, persist_directory="data/vector_store", provider="chromadb"):
        self.embeddings = embeddings
        self.persist_directory = persist_directory
        self.provider = provider
        self.vector_store = None
        self._load_or_create_store()
    
    def _load_or_create_store(self):
        persist_dir = self.persist_directory
        if Path(persist_dir).exists() and any(Path(persist_dir).iterdir()):
            logger.info(f"Loading existing vector store from {persist_dir}")
            self._load_store()
        else:
            logger.info("Creating new vector store")
            self._create_store()
    
    def _create_store(self):
        if self.provider == "chromadb":
            self.vector_store = Chroma(
                collection_name="multimodal_rag",
                embedding_function=self.embeddings,
                persist_directory=self.persist_directory
            )
        elif self.provider == "faiss":
            self.vector_store = FAISS.from_texts(["init"], self.embeddings)
    
    def _load_store(self):
        if self.provider == "chromadb":
            self.vector_store = Chroma(
                collection_name="multimodal_rag",
                embedding_function=self.embeddings,
                persist_directory=self.persist_directory
            )
        elif self.provider == "faiss":
            self.vector_store = FAISS.load_local(
                self.persist_directory, 
                self.embeddings,
                allow_dangerous_deserialization=True
            )
    
    def add_documents(self, documents):
        self.vector_store.add_documents(documents)
        if self.provider == "chromadb":
            self.vector_store.persist()
        elif self.provider == "faiss":
            self.vector_store.save_local(self.persist_directory)
        logger.info(f"Added {len(documents)} documents")
    
    def as_retriever(self, search_type="similarity", k=5):
        return self.vector_store.as_retriever(
            search_type=search_type,
            search_kwargs={"k": k}
        )
    
    def similarity_search(self, query, k=5):
        return self.vector_store.similarity_search(query, k=k)
    
    def similarity_search_with_score(self, query, k=5):
        return self.vector_store.similarity_search_with_score(query, k=k)

# Hybrid Retriever
class HybridRetriever:
    def __init__(self, vector_store, documents, semantic_weight=0.7, keyword_weight=0.3):
        self.vector_store = vector_store
        self.documents = documents
        self.semantic_weight = semantic_weight
        self.keyword_weight = keyword_weight
        self._setup_retrievers()
    
    def _setup_retrievers(self):
        self.semantic_retriever = self.vector_store.as_retriever(
            search_type="similarity", search_kwargs={"k": 5}
        )
        self.bm25_retriever = BM25Retriever.from_documents(self.documents, k=5)
    
    def get_relevant_documents(self, query):
        semantic_results = self.semantic_retriever.get_relevant_documents(query)
        keyword_results = self.bm25_retriever.get_relevant_documents(query)
        
        seen = set()
        combined = []
        for doc in semantic_results:
            key = doc.page_content[:50]
            if key not in seen:
                seen.add(key)
                combined.append((doc, self.semantic_weight))
        
        for doc in keyword_results:
            key = doc.page_content[:50]
            if key not in seen:
                seen.add(key)
                combined.append((doc, self.keyword_weight))
        
        combined.sort(key=lambda x: x[1], reverse=True)
        return [doc for doc, _ in combined[:5]]

# Main Pipeline Class
class MultimodalRAGPipeline:
    def __init__(self):
        self.text_processor = TextProcessor()
        self.embedding_manager = EmbeddingManager()
        self.vector_store_manager = None
        self.documents = []
    
    def initialize(self):
        embeddings = self.embedding_manager.initialize()
        self.vector_store_manager = VectorStoreManager(embeddings)
        logger.info("RAG Pipeline initialized")
    
    def ingest_text(self, text: str, source: str = "unknown") -> int:
        chunks = self.text_processor.process_text(text, source)
        documents = [
            Document(page_content=chunk.content, metadata=chunk.metadata)
            for chunk in chunks
        ]
        self.vector_store_manager.add_documents(documents)
        self.documents.extend(documents)
        logger.info(f"Ingested {len(chunks)} chunks from {source}")
        return len(chunks)
    
    def get_retriever(self, hybrid=True):
        if hybrid:
            return HybridRetriever(
                self.vector_store_manager.vector_store,
                self.documents
            )
        return self.vector_store_manager.as_retriever()
    
    def similarity_search(self, query, k=5):
        return self.vector_store_manager.similarity_search(query, k=k)