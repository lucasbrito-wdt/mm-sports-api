# Caminho do arquivo SQLite
$sqlitePath = "E:\wdt\laravel-backend\crudgerador\database\database.sqlite"

# Função utilitária para verificar se o arquivo está em uso
function Test-FileLock {
    param([string]$Path)
    try {
        $stream = [System.IO.File]::Open($Path, 'Open', 'ReadWrite', 'None')
        $stream.Close()
        return $false
    } catch {
        return $true
    }
}

# Fecha qualquer processo Laravel dev server (php artisan serve) ou SQLite
$provaveis = Get-Process | Where-Object { $_.Name -match "php|sqlite|artisan" }
foreach ($proc in $provaveis) {
    try {
        Write-Host "Tentando encerrar processo suspeito: $($proc.Name) (PID: $($proc.Id))"
        Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
    } catch {
        Write-Host "Falha ao encerrar $($proc.Name): $_"
    }
}

# Aguarda liberação de lock por segurança
Start-Sleep -Seconds 2

# Segunda verificação: arquivo ainda está bloqueado?
if (Test-FileLock -Path $sqlitePath) {
    Write-Host "O arquivo ainda está em uso. Não foi possível liberar o lock automaticamente."
    exit 1
}

# Tenta remover
try {
    Remove-Item -Path $sqlitePath -Force -ErrorAction Stop
    Write-Host "✅ Arquivo removido com sucesso: $sqlitePath"
} catch {
    Write-Host "❌ Erro ao remover: $_"
    exit 1
}

# Recria arquivo SQLite vazio
try {
    New-Item -Path $sqlitePath -ItemType File -Force | Out-Null
    Write-Host "✅ Arquivo recriado com sucesso."
} catch {
    Write-Host "❌ Falha ao recriar: $_"
}
