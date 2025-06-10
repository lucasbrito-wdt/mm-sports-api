# 📝 Geração Completa de Sistema de Blog
# Este script gera todas as estruturas necessárias para um sistema de blog

Write-Host "🚀 Iniciando geração do sistema de Blog..." -ForegroundColor Green

Write-Host "📂 Gerando Categorias de Posts..." -ForegroundColor Yellow
php artisan generate:crud --config=examples/blog-category.json --force

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Categorias criadas com sucesso!" -ForegroundColor Green
} else {
    Write-Host "❌ Erro ao criar categorias!" -ForegroundColor Red
    exit 1
}

Write-Host "📄 Gerando Posts..." -ForegroundColor Yellow
php artisan generate:crud --config=examples/blog-post.json --force

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Posts criados com sucesso!" -ForegroundColor Green
} else {
    Write-Host "❌ Erro ao criar posts!" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "🎉 Sistema de Blog gerado com sucesso!" -ForegroundColor Green
Write-Host "📁 Estruturas criadas:" -ForegroundColor Cyan
Write-Host "   - Domínio Blog (Categories, Posts)" -ForegroundColor White
Write-Host ""
Write-Host "🔄 Para desfazer todas as alterações, execute:" -ForegroundColor Yellow
Write-Host "   php artisan generate:crud --rollback" -ForegroundColor White
