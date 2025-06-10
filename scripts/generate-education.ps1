# 🎓 Geração Completa de Sistema Educacional
# Este script gera todas as estruturas necessárias para um sistema educacional

Write-Host "🚀 Iniciando geração do sistema Educacional..." -ForegroundColor Green

Write-Host "📚 Gerando Cursos..." -ForegroundColor Yellow
php artisan generate:crud --config=examples/education-course.json --force

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Cursos criados com sucesso!" -ForegroundColor Green
} else {
    Write-Host "❌ Erro ao criar cursos!" -ForegroundColor Red
    exit 1
}

Write-Host "👨‍🎓 Gerando Estudantes..." -ForegroundColor Yellow
php artisan generate:crud --config=examples/education-student.json --force

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Estudantes criados com sucesso!" -ForegroundColor Green
} else {
    Write-Host "❌ Erro ao criar estudantes!" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "🎉 Sistema Educacional gerado com sucesso!" -ForegroundColor Green
Write-Host "📁 Estruturas criadas:" -ForegroundColor Cyan
Write-Host "   - Domínio Education (Courses, Students)" -ForegroundColor White
Write-Host ""
Write-Host "🔄 Para desfazer todas as alterações, execute:" -ForegroundColor Yellow
Write-Host "   php artisan generate:crud --rollback" -ForegroundColor White
