# ==============================================================================
# PulseDesk AI — Local Windows PowerShell Launcher (No Docker Required)
# Starts all 3 services on conflict-free ports:
#   1. FastAPI RAG Engine     -> http://127.0.0.1:8011/docs
#   2. Laravel + Vue 3 SaaS   -> http://127.0.0.1:8010
#   3. Node.js Telegram Bot   -> http://127.0.0.1:3001/health
# ==============================================================================

$Root = $PSScriptRoot

Write-Host "Starting PulseDesk AI Microservices..." -ForegroundColor Cyan

# 1. Start FastAPI RAG Engine on port 8011
Start-Process powershell -ArgumentList "-NoExit", "-Command", "Set-Location '$Root\rag-api'; python -m uvicorn app.main:app --host 127.0.0.1 --port 8011 --reload"

# 2. Start Laravel + Vue 3 SaaS Dashboard on port 8010
Start-Process powershell -ArgumentList "-NoExit", "-Command", "Set-Location '$Root\web-saas'; php artisan serve --host=127.0.0.1 --port=8010"

# 3. Start Node.js Telegram Bot & Webhook Bridge on port 3001
Start-Process powershell -ArgumentList "-NoExit", "-Command", "Set-Location '$Root\tg-bot'; npm run dev"

Write-Host ""
Write-Host "All 3 services are launching in separate PowerShell windows:" -ForegroundColor Green
Write-Host "  -> SaaS Dashboard (Laravel + Vue 3): http://127.0.0.1:8010" -ForegroundColor Yellow
Write-Host "  -> FastAPI RAG Swagger UI:           http://127.0.0.1:8011/docs" -ForegroundColor Yellow
Write-Host "  -> Node.js Telegram Bot Health:      http://127.0.0.1:3001/health" -ForegroundColor Yellow
