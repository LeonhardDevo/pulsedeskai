import { config } from "../config.js";
import { RAGQueryPayload, RAGQueryResult } from "../types.js";

export class RagApiClient {
  private readonly baseUrl: string;

  constructor(baseUrl: string = config.ragApiUrl) {
    this.baseUrl = baseUrl.replace(/\/$/, "");
  }

  async queryKnowledgeBase(payload: RAGQueryPayload): Promise<RAGQueryResult> {
    const response = await fetch(`${this.baseUrl}/api/v1/rag/query`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    if (!response.ok) {
      throw new Error(`RAG API error: ${response.status} ${response.statusText}`);
    }

    return (await response.json()) as RAGQueryResult;
  }
}
