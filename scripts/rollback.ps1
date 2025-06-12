# 🔄 Script de Rollback Avançado
# Este script oferece opções avançadas de rollback para o Laravel CRUD Generator

param(
    [string]$Domain = "",
    [switch]$Interactive = $false,
    [switch]$FrontendOnly = $false,
    [switch]$BackendOnly = $false,
    [switch]$DryRun = $false,
    [switch]$Force = $false,
    [switch]$Status = $false,
    [switch]$WebInterface = $false,
    [int]$Port = 8080,
    [switch]$Help = $false,
    [switch]$Demo = $false,
    [switch]$Integrity = $false
)

function Show-Help {
    Write-Host "🔄 Script de Rollback Avançado - Laravel CRUD Generator" -ForegroundColor Green
    Write-Host "=========================================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "USAGE:" -ForegroundColor Yellow
    Write-Host "  .\rollback.ps1 [OPTIONS]"
    Write-Host ""
    Write-Host "OPTIONS:" -ForegroundColor Yellow
    Write-Host "  -Domain <nome>     Fazer rollback apenas do domínio especificado"
    Write-Host "  -Interactive       Modo interativo com menu de opções"
    Write-Host "  -FrontendOnly      Fazer rollback apenas do frontend"
    Write-Host "  -BackendOnly       Fazer rollback apenas do backend"
    Write-Host "  -DryRun           Simular rollback (não executar)"
    Write-Host "  -Force            Executar sem confirmações"
    Write-Host "  -Status           Mostrar status do sistema de rollback"
    Write-Host "  -WebInterface     Iniciar interface web de rollback"
    Write-Host "  -Port <número>    Porta para interface web (padrão: 8080)"
    Write-Host "  -Demo             Executar demonstração completa"
    Write-Host "  -Integrity        Verificar integridade do projeto"
    Write-Host "  -Help             Mostrar esta ajuda"
    Write-Host ""
    Write-Host "EXAMPLES:" -ForegroundColor Yellow
    Write-Host "  .\rollback.ps1                           # Rollback completo com confirmação"
    Write-Host "  .\rollback.ps1 -Interactive              # Menu interativo"
    Write-Host "  .\rollback.ps1 -Domain Products          # Rollback apenas do domínio Products"
    Write-Host "  .\rollback.ps1 -FrontendOnly             # Rollback apenas do frontend"
    Write-Host "  .\rollback.ps1 -DryRun                  # Simular rollback"
    Write-Host "  .\rollback.ps1 -Status                  # Ver status do rollback"
    Write-Host "  .\rollback.ps1 -WebInterface -Port 3000 # Interface web na porta 3000"
    Write-Host "  .\rollback.ps1 -Demo                    # Executar demonstração"
    Write-Host "  .\rollback.ps1 -Integrity               # Verificar integridade"
    Write-Host ""
    Write-Host "SISTEMA DE ROLLBACK AVANÇADO:" -ForegroundColor Cyan
    Write-Host "✅ Rollback granular por domínio"
    Write-Host "✅ Separação frontend/backend"
    Write-Host "✅ Interface web moderna"
    Write-Host "✅ Verificação de integridade"
    Write-Host "✅ Logs detalhados e sessões"
    Write-Host "✅ Suporte a dry-run"
    Write-Host ""
}

function Show-Status {
    Write-Host "📊 Verificando status do sistema de rollback..." -ForegroundColor Green
    php artisan rollback:status --detailed
}

function Start-WebInterface {
    Write-Host "🌐 Iniciando interface web na porta $Port..." -ForegroundColor Green
    Write-Host "Acesse: http://localhost:$Port/rollback" -ForegroundColor Cyan
    php artisan rollback:web-interface --port=$Port
}

function Test-Integrity {
    Write-Host "🔍 Verificando integridade do projeto..." -ForegroundColor Green
    php artisan rollback:integrity --detailed
}

function Run-Demo {
    Write-Host "🎬 Executando demonstração do sistema de rollback..." -ForegroundColor Green
    Write-Host ""

    # Passo 1: Mostrar status inicial
    Write-Host "📋 Passo 1: Status inicial" -ForegroundColor Yellow
    php artisan rollback:status
    Write-Host ""

    # Passo 2: Verificar se há exemplos
    Write-Host "📋 Passo 2: Verificando arquivos de exemplo..." -ForegroundColor Yellow
    if (Test-Path "examples/test-rollback.json") {
        Write-Host "✅ Arquivo de exemplo encontrado" -ForegroundColor Green
    } else {
        Write-Host "⚠️  Criando arquivo de exemplo..." -ForegroundColor Yellow
        $exampleContent = @'
{
    "domain": "DemoRollback",
    "model": "TestProduct",
    "schema": "name=string,100,req;price=decimal,8,2,req;description=text",
    "generateCompleteStructure": true,
    "force": true
}
'@
        $exampleContent | Out-File -FilePath "examples/demo-rollback.json" -Encoding UTF8
        Write-Host "✅ Arquivo criado: examples/demo-rollback.json" -ForegroundColor Green
    }
    Write-Host ""

    # Passo 3: Mostrar comandos disponíveis
    Write-Host "📋 Passo 3: Comandos disponíveis" -ForegroundColor Yellow
    php artisan list | Select-String "rollback"
    Write-Host ""

    # Passo 4: Mostrar estrutura de rollback
    Write-Host "📋 Passo 4: Estrutura de rollback" -ForegroundColor Yellow
    $rollbackDir = "storage/framework/rollback"
    if (Test-Path $rollbackDir) {
        Write-Host "✅ Diretório de rollback existe: $rollbackDir" -ForegroundColor Green
        Get-ChildItem $rollbackDir -Recurse | ForEach-Object { Write-Host "  - $($_.Name)" }
    } else {
        Write-Host "⚠️  Diretório de rollback não existe (será criado na primeira geração)" -ForegroundColor Yellow
    }
    Write-Host ""

    Write-Host "🎉 Demonstração concluída!" -ForegroundColor Green
    Write-Host "📖 Para gerar um CRUD de teste: php artisan generate:crud --config=examples/demo-rollback.json" -ForegroundColor Cyan
    Write-Host "🔄 Para testar rollback: .\rollback.ps1 -Interactive" -ForegroundColor Cyan
}

function Confirm-Action {
    param([string]$Message)

    if ($Force) {
        return $true
    }

    $response = Read-Host "$Message (S/N)"
    return $response -eq "S" -or $response -eq "s" -or $response -eq "Y" -or $response -eq "y"
}

function Execute-Rollback {
    param(
        [string]$Type = "full",
        [string]$Domain = "",
        [bool]$DryRun = $false
    )

    $command = "php artisan rollback:manager"

    if ($Domain) {
        $command += " --domain=$Domain"
    }

    if ($Type -eq "frontend") {
        $command += " --frontend-only"
    } elseif ($Type -eq "backend") {
        $command += " --backend-only"
    }

    if ($DryRun) {
        $command += " --dry-run"
        Write-Host "🔍 Simulando rollback..." -ForegroundColor Yellow
    } else {
        Write-Host "🔄 Executando rollback..." -ForegroundColor Green
    }

    if ($Force) {
        $command += " --force"
    }

    Invoke-Expression $command

    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Rollback executado com sucesso!" -ForegroundColor Green
        if (-not $DryRun) {
            Write-Host "🔍 Verifique os logs para detalhes das alterações desfeitas." -ForegroundColor Cyan
        }
    } else {
        Write-Host "❌ Erro durante o rollback!" -ForegroundColor Red
        Write-Host "🔍 Verifique os logs para mais informações." -ForegroundColor Yellow
    }
}

function Show-InteractiveMenu {
    do {
        Clear-Host
        Write-Host "🔄 Gerenciador de Rollback Avançado - Laravel CRUD Generator" -ForegroundColor Green
        Write-Host "=" * 70 -ForegroundColor Gray
        Write-Host ""
        Write-Host "Escolha uma opção:" -ForegroundColor Yellow
        Write-Host "1. 📊 Ver Status do Sistema"
        Write-Host "2. 🔄 Rollback Completo"
        Write-Host "3. 🎯 Rollback por Domínio"
        Write-Host "4. 🎨 Rollback apenas Frontend"
        Write-Host "5. 🔧 Rollback apenas Backend"
        Write-Host "6. 🔍 Simular Rollback (Dry Run)"
        Write-Host "7. 🌐 Interface Web"
        Write-Host "8. 🔍 Verificar Integridade"
        Write-Host "9. 🎬 Executar Demonstração"
        Write-Host "10. ❓ Ajuda"
        Write-Host "0. 🚪 Sair"
        Write-Host ""
        Write-Host "💡 Sistema de Rollback Avançado com:" -ForegroundColor Cyan
        Write-Host "   ✅ Rollback granular por domínio" -ForegroundColor Green
        Write-Host "   ✅ Separação frontend/backend" -ForegroundColor Green
        Write-Host "   ✅ Interface web moderna" -ForegroundColor Green
        Write-Host "   ✅ Verificação de integridade" -ForegroundColor Green
        Write-Host ""

        $choice = Read-Host "Digite sua escolha (0-10)"

        switch ($choice) {
            "1" {
                Write-Host ""
                Write-Host "📊 Carregando status do sistema..." -ForegroundColor Blue
                Show-Status
                Write-Host ""
                Read-Host "Pressione Enter para continuar"
            }
            "2" {
                Write-Host ""
                Write-Host "⚠️ ROLLBACK COMPLETO" -ForegroundColor Red
                Write-Host "Esta opção irá desfazer TODAS as alterações feitas pelo gerador!" -ForegroundColor Yellow
                if (Confirm-Action "Tem certeza que deseja continuar?") {
                    Execute-Rollback -Type "full"
                } else {
                    Write-Host "❌ Operação cancelada." -ForegroundColor Yellow
                }
                Write-Host ""
                Read-Host "Pressione Enter para continuar"
            }
            "3" {
                Write-Host ""
                Write-Host "🎯 ROLLBACK POR DOMÍNIO" -ForegroundColor Blue
                Write-Host "Esta opção permite fazer rollback apenas de um domínio específico." -ForegroundColor Gray
                $domainName = Read-Host "Digite o nome do domínio"
                if ($domainName) {
                    Write-Host ""
                    Write-Host "Escolha o escopo:" -ForegroundColor Yellow
                    Write-Host "1. Frontend + Backend"
                    Write-Host "2. Apenas Frontend"
                    Write-Host "3. Apenas Backend"
                    $scopeChoice = Read-Host "Digite sua escolha (1-3)"

                    $rollbackType = "domain"
                    $additionalParams = ""

                    switch ($scopeChoice) {
                        "2" { $additionalParams = " --frontend-only" }
                        "3" { $additionalParams = " --backend-only" }
                    }

                    if (Confirm-Action "Fazer rollback do domínio '$domainName'?") {
                        $command = "php artisan rollback:manager --domain=$domainName$additionalParams"
                        Write-Host "Executando: $command" -ForegroundColor Gray
                        Invoke-Expression $command
                    } else {
                        Write-Host "❌ Operação cancelada." -ForegroundColor Yellow
                    }
                }
                Write-Host ""
                Read-Host "Pressione Enter para continuar"
            }
            "4" {
                Write-Host ""
                Write-Host "🎨 ROLLBACK FRONTEND" -ForegroundColor Blue
                Write-Host "Esta opção fará rollback apenas dos arquivos de frontend." -ForegroundColor Gray
                if (Confirm-Action "Fazer rollback apenas do frontend?") {
                    Execute-Rollback -Type "frontend"
                } else {
                    Write-Host "❌ Operação cancelada." -ForegroundColor Yellow
                }
                Write-Host ""
                Read-Host "Pressione Enter para continuar"
            }
            "5" {
                Write-Host ""
                Write-Host "🔧 ROLLBACK BACKEND" -ForegroundColor Blue
                Write-Host "Esta opção fará rollback apenas dos arquivos de backend." -ForegroundColor Gray
                if (Confirm-Action "Fazer rollback apenas do backend?") {
                    Execute-Rollback -Type "backend"
                } else {
                    Write-Host "❌ Operação cancelada." -ForegroundColor Yellow
                }
                Write-Host ""
                Read-Host "Pressione Enter para continuar"
            }
            "6" {
                Write-Host ""
                Write-Host "🔍 SIMULAÇÃO DE ROLLBACK" -ForegroundColor Blue
                Write-Host "Esta opção mostra o que seria feito sem executar as alterações." -ForegroundColor Gray
                Execute-Rollback -Type "full" -DryRun $true
                Write-Host ""
                Read-Host "Pressione Enter para continuar"
            }
            "7" {
                Write-Host ""
                Write-Host "🌐 INTERFACE WEB" -ForegroundColor Blue
                $webPort = Read-Host "Digite a porta (Enter para usar 8080)"
                if (-not $webPort) { $webPort = "8080" }
                Write-Host "Iniciando interface web na porta $webPort..." -ForegroundColor Green
                Write-Host "Acesse: http://localhost:$webPort/rollback" -ForegroundColor Cyan
                Write-Host "Pressione Ctrl+C para parar o servidor" -ForegroundColor Yellow
                php artisan rollback:web-interface --port=$webPort
            }
            "8" {
                Write-Host ""
                Write-Host "🔍 VERIFICAÇÃO DE INTEGRIDADE" -ForegroundColor Blue
                Test-Integrity
                Write-Host ""
                Read-Host "Pressione Enter para continuar"
            }
            "9" {
                Write-Host ""
                Write-Host "🎬 DEMONSTRAÇÃO DO SISTEMA" -ForegroundColor Blue
                Run-Demo
                Write-Host ""
                Read-Host "Pressione Enter para continuar"
            }
            "10" {
                Show-Help
                Write-Host ""
                Read-Host "Pressione Enter para continuar"
            }
            "0" {
                Write-Host ""
                Write-Host "👋 Até logo!" -ForegroundColor Green
                return
                        }
            default {
                Write-Host ""
                Write-Host "❌ Opção inválida. Tente novamente." -ForegroundColor Red
                Start-Sleep -Seconds 1
            }
        }
    } while ($true)
}

# Main execution - Lógica principal
Write-Host "🔄 Script de Rollback Avançado - Laravel CRUD Generator" -ForegroundColor Green

# Verificar argumentos e executar ação apropriada
if ($Help) {
    Show-Help
    exit 0
}

if ($Demo) {
    Run-Demo
    exit 0
}

if ($Status) {
    Show-Status
    exit 0
}

if ($Integrity) {
    Test-Integrity
    exit 0
}

if ($WebInterface) {
    Start-WebInterface
    exit 0
}

if ($Interactive) {
    Show-InteractiveMenu
    exit 0
}

# Execução direta baseada em parâmetros
if ($Domain -or $FrontendOnly -or $BackendOnly -or $DryRun) {
    Write-Host "📋 Parâmetros detectados, executando rollback..." -ForegroundColor Blue

    # Construir comando
    $command = "php artisan rollback:manager"

    if ($Domain) {
        $command += " --domain=$Domain"
    }

    if ($FrontendOnly) {
        $command += " --frontend-only"
    }

    if ($BackendOnly) {
        $command += " --backend-only"
    }

    if ($DryRun) {
        $command += " --dry-run"
    }

    if ($Force) {
        $command += " --force"
    }

    Write-Host "Executando: $command" -ForegroundColor Gray
    Invoke-Expression $command
    exit 0
}

# Se nenhum parâmetro foi fornecido, mostrar opções
Write-Host ""
Write-Host "Nenhum parâmetro específico fornecido." -ForegroundColor Yellow
Write-Host ""
Write-Host "Opções rápidas:" -ForegroundColor Cyan
Write-Host "  .\rollback.ps1 -Interactive    # Menu interativo"
Write-Host "  .\rollback.ps1 -Status         # Ver status"
Write-Host "  .\rollback.ps1 -Help           # Ver ajuda"
Write-Host "  .\rollback.ps1 -Demo           # Executar demonstração"
Write-Host ""

$quickChoice = Read-Host "Deseja abrir o menu interativo? (S/N)"
if ($quickChoice -eq "S" -or $quickChoice -eq "s" -or $quickChoice -eq "Y" -or $quickChoice -eq "y") {
    Show-InteractiveMenu
} else {
    Write-Host "👋 Use .\rollback.ps1 -Help para ver todas as opções disponíveis." -ForegroundColor Green
}

$statusResult = php artisan rollback:status 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Erro ao verificar status do rollback!" -ForegroundColor Red
    Write-Host $statusResult
    exit 1
}

# Execução direta baseada nos parâmetros
if ($Domain) {
    Write-Host "🎯 Rollback do domínio: $Domain" -ForegroundColor Yellow
    if (Confirm-Action "Confirma o rollback do domínio '$Domain'?") {
        Execute-Rollback -Type "domain" -Domain $Domain -DryRun $DryRun
    } else {
        Write-Host "❌ Operação cancelada." -ForegroundColor Yellow
    }
} elseif ($FrontendOnly) {
    Write-Host "🎨 Rollback apenas do frontend" -ForegroundColor Yellow
    if (Confirm-Action "Confirma o rollback apenas do frontend?") {
        Execute-Rollback -Type "frontend" -DryRun $DryRun
    } else {
        Write-Host "❌ Operação cancelada." -ForegroundColor Yellow
    }
} elseif ($BackendOnly) {
    Write-Host "🔧 Rollback apenas do backend" -ForegroundColor Yellow
    if (Confirm-Action "Confirma o rollback apenas do backend?") {
        Execute-Rollback -Type "backend" -DryRun $DryRun
    } else {
        Write-Host "❌ Operação cancelada." -ForegroundColor Yellow
    }
} else {
    # Rollback completo
    Write-Host "⚠️ ATENÇÃO: Este script irá desfazer TODAS as alterações!" -ForegroundColor Red
    Write-Host "📁 Arquivos que foram criados serão REMOVIDOS" -ForegroundColor Yellow
    Write-Host "📝 Arquivos que foram modificados serão RESTAURADOS" -ForegroundColor Yellow
    Write-Host ""

    if (Confirm-Action "Tem certeza que deseja continuar? (S/N)") {
        Execute-Rollback -Type "full" -DryRun $DryRun
    } else {
        Write-Host "❌ Operação cancelada pelo usuário." -ForegroundColor Yellow
        exit 0
    }
}
