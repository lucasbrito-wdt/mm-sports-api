# 🛒 Geração Completa de E-commerce
# Este script gera todas as estruturas necessárias para um sistema de e-commerce

Write-Host "🚀 Iniciando geração do sistema de E-commerce..." -ForegroundColor Green

Write-Host "📦 Gerando Categorias de Produtos..." -ForegroundColor Yellow
php artisan generate:crud --domain --config=@examples/ecommerce-category.json --force

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Categorias criadas com sucesso!" -ForegroundColor Green
} else {
    Write-Host "❌ Erro ao criar categorias!" -ForegroundColor Red
    exit 1
}

Write-Host "🛍️ Gerando Produtos..." -ForegroundColor Yellow
php artisan generate:crud --domain --config=@examples/ecommerce-product.json --force

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Produtos criados com sucesso!" -ForegroundColor Green
} else {
    Write-Host "❌ Erro ao criar produtos!" -ForegroundColor Red
    exit 1
}

Write-Host "📋 Gerando Pedidos..." -ForegroundColor Yellow
php artisan generate:crud --domain --config=@examples/ecommerce-order.json --force

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Pedidos criados com sucesso!" -ForegroundColor Green
} else {
    Write-Host "❌ Erro ao criar pedidos!" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "🎉 Sistema de E-commerce gerado com sucesso!" -ForegroundColor Green
Write-Host "📁 Estruturas criadas:" -ForegroundColor Cyan
Write-Host "   - Domínio Catalog (Categories, Products)" -ForegroundColor White
Write-Host "   - Domínio Orders (Orders)" -ForegroundColor White
Write-Host ""
Write-Host "🔄 Para desfazer todas as alterações, execute:" -ForegroundColor Yellow
Write-Host "   php artisan generate:crud --rollback" -ForegroundColor White
