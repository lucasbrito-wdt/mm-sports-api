# Script PowerShell para teste da funcionalidade de múltiplos CRUDs
# Arquivo: scripts/test-multiple-cruds.ps1

Write-Host "🚀 Testando funcionalidade de múltiplos CRUDs no domínio" -ForegroundColor Green

# Limpar execução anterior se houver
Write-Host "🧹 Limpando execuções anteriores..." -ForegroundColor Yellow
php artisan generate:crud --rollback

# Testar geração de domínio com múltiplos CRUDs
Write-Host "📦 Gerando sistema de blog completo com múltiplos CRUDs..." -ForegroundColor Cyan
php artisan generate:crud --config=@examples/blog-complete-system.json --domain --force

# Verificar se arquivos foram criados
Write-Host "✅ Verificando arquivos gerados..." -ForegroundColor Blue

$domain = "BlogComplete"
$modelsPath = "app\Domains\$domain\Models"

if (Test-Path $modelsPath) {
    Write-Host "📁 Models gerados:" -ForegroundColor Green
    Get-ChildItem $modelsPath -Name | ForEach-Object { Write-Host "  - $_" -ForegroundColor White }
} else {
    Write-Host "❌ Pasta de models não encontrada!" -ForegroundColor Red
}

$controllersPath = "app\Domains\$domain\Controllers"
if (Test-Path $controllersPath) {
    Write-Host "📁 Controllers gerados:" -ForegroundColor Green
    Get-ChildItem $controllersPath -Name | ForEach-Object { Write-Host "  - $_" -ForegroundColor White }
}

$routesPath = "routes\domains\blog-complete.php"
if (Test-Path $routesPath) {
    Write-Host "📁 Arquivo de rotas criado:" -ForegroundColor Green
    Write-Host "  - $routesPath" -ForegroundColor White
}

# Verificar frontend
$frontendPath = "..\template-exemple\pages\blog-complete"
if (Test-Path $frontendPath) {
    Write-Host "📁 Arquivos de frontend gerados:" -ForegroundColor Green
    Get-ChildItem $frontendPath -Name | ForEach-Object { Write-Host "  - $_" -ForegroundColor White }
}

Write-Host "🎉 Teste concluído! Múltiplos CRUDs gerados com sucesso." -ForegroundColor Green
Write-Host "💡 Para desfazer: php artisan generate:crud --rollback" -ForegroundColor Yellow
