# 🔄 Script de Rollback
# Este script desfaz todas as alterações feitas pelo gerador

Write-Host "⚠️  ATENÇÃO: Este script irá desfazer TODAS as alterações!" -ForegroundColor Red
Write-Host "📁 Arquivos que foram criados serão REMOVIDOS" -ForegroundColor Yellow
Write-Host "📝 Arquivos que foram modificados serão RESTAURADOS" -ForegroundColor Yellow
Write-Host ""

$confirmation = Read-Host "Tem certeza que deseja continuar? (digite 'SIM' para confirmar)"

if ($confirmation -ne "SIM") {
    Write-Host "❌ Operação cancelada pelo usuário." -ForegroundColor Yellow
    exit 0
}

Write-Host ""
Write-Host "🔄 Executando rollback..." -ForegroundColor Green

php artisan generate:crud --rollback

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "✅ Rollback executado com sucesso!" -ForegroundColor Green
    Write-Host "🔍 Verifique os logs para detalhes das alterações desfeitas." -ForegroundColor Cyan
} else {
    Write-Host ""
    Write-Host "❌ Erro durante o rollback!" -ForegroundColor Red
    Write-Host "🔍 Verifique os logs para mais informações." -ForegroundColor Yellow
}
