import re


class DocumentChunker:
    """
    Splits FAQ, return policy, and catalog documents into coherent semantic chunks
    with overlap while preserving markdown headers and Q&A boundaries.
    """

    @staticmethod
    def split_text(text: str, chunk_size: int = 500, chunk_overlap: int = 80) -> list[str]:
        cleaned = text.strip()
        if not cleaned:
            return []

        if len(cleaned) <= chunk_size:
            return [cleaned]

        # Split first by double newlines or Markdown headers to keep Q&A blocks intact
        blocks = re.split(r"\n\s*\n", cleaned)
        chunks: list[str] = []
        current_chunk = ""

        for block in blocks:
            block = block.strip()
            if not block:
                continue

            if len(current_chunk) + len(block) + 2 <= chunk_size:
                current_chunk = f"{current_chunk}\n\n{block}".strip()
            else:
                if current_chunk:
                    chunks.append(current_chunk)
                    overlap_tail = current_chunk[-chunk_overlap:] if chunk_overlap > 0 else ""
                else:
                    overlap_tail = ""

                if len(block) <= chunk_size:
                    current_chunk = f"{overlap_tail}\n{block}".strip() if overlap_tail else block
                else:
                    # Sentence-aware split for oversized single paragraphs
                    sentences = re.split(r"(?<=[.!?])\s+", block)
                    sub_chunk = overlap_tail
                    for sentence in sentences:
                        if len(sub_chunk) + len(sentence) + 1 <= chunk_size:
                            sub_chunk = f"{sub_chunk} {sentence}".strip()
                        else:
                            if sub_chunk:
                                chunks.append(sub_chunk)
                            # Hard slice if a single sentence exceeds chunk_size
                            while len(sentence) > chunk_size:
                                chunks.append(sentence[:chunk_size])
                                sentence = sentence[chunk_size - chunk_overlap :]
                            sub_chunk = sentence
                    current_chunk = sub_chunk

        if current_chunk:
            chunks.append(current_chunk)

        return chunks
