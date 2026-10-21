<?php

namespace App\Jobs;

use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use App\Services\RagEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IndexKnowledgeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly KnowledgeDocument $document
    ) {}

    public function handle(RagEngineService $ragService): void
    {
        $ragService->indexDocument($this->tenant, $this->document);
    }
}
