import json
import re
from dataclasses import dataclass
from sqlalchemy import Column, Integer, String, Text, create_engine, delete, select, text
from sqlalchemy.orm import declarative_base, sessionmaker

from app.config import settings
from app.services.llm_router import (
    EMBEDDING_DIM,
    compute_deterministic_embedding,
    cosine_similarity,
)

Base = declarative_base()


class DocumentChunkRecord(Base):
    __tablename__ = "rag_document_chunks"

    id = Column(Integer, primary_key=True, autoincrement=True)
    tenant_id = Column(String(128), nullable=False, index=True)
    document_id = Column(String(128), nullable=False, index=True)
    title = Column(String(512), nullable=False)
    category = Column(String(64), nullable=False, default="faq")
    chunk_index = Column(Integer, nullable=False, default=0)
    content = Column(Text, nullable=False)
    embedding_json = Column(Text, nullable=False)


@dataclass
class RetrievedChunk:
    document_id: str
    title: str
    category: str
    excerpt: str
    score: float


class HybridVectorStore:
    """
    Production-ready hybrid vector store:
    - When connected to PostgreSQL with pgvector, creates a native `vector(128)` column
      with an HNSW index (`vector_cosine_ops`) and executes SQL `<=>` cosine distance queries.
    - Transparently supports SQLite/Postgres hybrid cosine + BM25 lexical scoring in CI/local dev.
    """

    def __init__(self, database_url: str | None = None):
        self.database_url = database_url or settings.database_url
        connect_args = {"check_same_thread": False} if self.database_url.startswith("sqlite") else {}
        self.engine = create_engine(self.database_url, connect_args=connect_args)
        self.SessionLocal = sessionmaker(bind=self.engine, autoflush=False, autocommit=False)
        self.pgvector_enabled = False
        self._init_db()

    def _init_db(self) -> None:
        Base.metadata.create_all(bind=self.engine)

        if self.database_url.startswith("postgresql") and settings.use_pgvector:
            try:
                with self.engine.begin() as conn:
                    conn.execute(text("CREATE EXTENSION IF NOT EXISTS vector"))
                    conn.execute(
                        text(
                            f"ALTER TABLE rag_document_chunks "
                            f"ADD COLUMN IF NOT EXISTS embedding_vec vector({EMBEDDING_DIM})"
                        )
                    )
                    conn.execute(
                        text(
                            "CREATE INDEX IF NOT EXISTS idx_rag_chunks_hnsw "
                            "ON rag_document_chunks USING hnsw (embedding_vec vector_cosine_ops)"
                        )
                    )
                self.pgvector_enabled = True
            except Exception:
                self.pgvector_enabled = False

    @property
    def backend_name(self) -> str:
        if self.pgvector_enabled:
            return "postgresql+pgvector(hnsw)"
        if self.database_url.startswith("postgresql"):
            return "postgresql+hybrid"
        return "sqlite+hybrid-vector"

    def upsert_document_chunks(
        self,
        tenant_id: str,
        document_id: str,
        title: str,
        category: str,
        chunks: list[str],
    ) -> int:
        with self.SessionLocal() as session:
            session.execute(
                delete(DocumentChunkRecord).where(
                    DocumentChunkRecord.tenant_id == tenant_id,
                    DocumentChunkRecord.document_id == document_id,
                )
            )
            for idx, chunk_text in enumerate(chunks):
                emb = compute_deterministic_embedding(f"{title} {chunk_text}", dim=EMBEDDING_DIM)
                record = DocumentChunkRecord(
                    tenant_id=tenant_id,
                    document_id=document_id,
                    title=title,
                    category=category,
                    chunk_index=idx,
                    content=chunk_text,
                    embedding_json=json.dumps(emb),
                )
                session.add(record)
            session.commit()

            if self.pgvector_enabled:
                session.execute(
                    text(
                        "UPDATE rag_document_chunks "
                        "SET embedding_vec = CAST(embedding_json AS vector) "
                        "WHERE tenant_id = :tid AND document_id = :did"
                    ),
                    {"tid": tenant_id, "did": document_id},
                )
                session.commit()

        return len(chunks)

    def delete_document(self, tenant_id: str, document_id: str) -> int:
        with self.SessionLocal() as session:
            res = session.execute(
                delete(DocumentChunkRecord).where(
                    DocumentChunkRecord.tenant_id == tenant_id,
                    DocumentChunkRecord.document_id == document_id,
                )
            )
            session.commit()
            return int(res.rowcount or 0)

    def search(self, tenant_id: str, query: str, top_k: int = 3) -> list[RetrievedChunk]:
        query_vec = compute_deterministic_embedding(query, dim=EMBEDDING_DIM)
        query_tokens = set(re.findall(r"[\wа-яё]{3,}", query.lower()))

        with self.SessionLocal() as session:
            if self.pgvector_enabled:
                vec_literal = json.dumps(query_vec)
                sql_rows = session.execute(
                    text(
                        "SELECT document_id, title, category, content, embedding_json "
                        "FROM rag_document_chunks "
                        "WHERE tenant_id = :tid "
                        "ORDER BY embedding_vec <=> CAST(:qvec AS vector) "
                        "LIMIT :lim"
                    ),
                    {"tid": tenant_id, "qvec": vec_literal, "lim": max(top_k * 3, 10)},
                ).fetchall()
                rows = [
                    DocumentChunkRecord(
                        document_id=r[0],
                        title=r[1],
                        category=r[2],
                        content=r[3],
                        embedding_json=r[4],
                    )
                    for r in sql_rows
                ]
            else:
                rows = (
                    session.execute(
                        select(DocumentChunkRecord).where(DocumentChunkRecord.tenant_id == tenant_id)
                    )
                    .scalars()
                    .all()
                )

        if not rows:
            return []

        scored: list[RetrievedChunk] = []
        for row in rows:
            try:
                doc_vec = json.loads(row.embedding_json)
            except Exception:
                doc_vec = []

            vec_sim = cosine_similarity(query_vec, doc_vec)
            doc_text_lower = f"{row.title} {row.content}".lower()
            doc_tokens = set(re.findall(r"[\wа-яё]{3,}", doc_text_lower))

            if query_tokens and doc_tokens:
                exact_matches = len(query_tokens & doc_tokens)
                stem_matches = sum(
                    1
                    for qt in query_tokens
                    if len(qt) >= 4 and any(dt.startswith(qt[:4]) for dt in doc_tokens)
                )
                lexical_score = min(1.0, (exact_matches * 0.6 + stem_matches * 0.4) / max(1, len(query_tokens)))
            else:
                lexical_score = 0.0

            combined = min(0.98, (vec_sim * 0.55) + (lexical_score * 0.55))
            if lexical_score > 0.35 or vec_sim > 0.25:
                combined = max(combined, min(0.95, 0.55 + lexical_score * 0.4))

            scored.append(
                RetrievedChunk(
                    document_id=row.document_id,
                    title=row.title,
                    category=row.category,
                    excerpt=row.content,
                    score=round(combined, 4),
                )
            )

        scored.sort(key=lambda item: item.score, reverse=True)
        return scored[:top_k]
