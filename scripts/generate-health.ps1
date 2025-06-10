# 🏥 Geração Completa de Sistema de Saúde
# Este script gera todas as estruturas necessárias para um sistema de saúde

Write-Host "🚀 Iniciando geração do sistema de Saúde..." -ForegroundColor Green

Write-Host "👤 Gerando Pacientes..." -ForegroundColor Yellow
php artisan generate:crud --config=examples/health-patient.json --force

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Pacientes criados com sucesso!" -ForegroundColor Green
} else {
    Write-Host "❌ Erro ao criar pacientes!" -ForegroundColor Red
    exit 1
}

Write-Host "📅 Gerando Consultas..." -ForegroundColor Yellow
php artisan generate:crud --config=examples/health-appointment.json --force

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Consultas criadas com sucesso!" -ForegroundColor Green
} else {
    Write-Host "❌ Erro ao criar consultas!" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "🎉 Sistema de Saúde gerado com sucesso!" -ForegroundColor Green
Write-Host "📁 Estruturas criadas:" -ForegroundColor Cyan
Write-Host "   - Domínio Health (Patients, Appointments)" -ForegroundColor White
Write-Host ""
Write-Host "🔄 Para desfazer todas as alterações, execute:" -ForegroundColor Yellow
Write-Host "   php artisan generate:crud --rollback" -ForegroundColor White
