# Sistema de Rollback Avançado - Laravel CRUD Generator

## Visão Geral

O Sistema de Rollback Avançado foi implementado para fornecer uma maneira robusta e granular de desfazer operações de geração de CRUD, permitindo rollback seletivo por domínio, separação entre frontend e backend, e interface web para gerenciamento.

## Componentes Principais

### 1. RollbackManager
**Comando:** `php artisan rollback:manager`

Gerenciador principal com funcionalidades avançadas:
- **Rollback Completo:** Desfaz todas as alterações
- **Rollback Seletivo por Domínio:** Desfaz apenas arquivos de um domínio específico
- **Rollback Frontend/Backend Separado:** Permite escolher entre frontend, backend ou ambos
- **Modo Dry-Run:** Simula operação sem executar
- **Modo Interativo:** Interface amigável para seleção de opções

#### Opções Disponíveis:
```bash
# Rollback de domínio específico
php artisan rollback:manager --domain=BlogPost --frontend-only

# Rollback interativo
php artisan rollback:manager --interactive

# Rollback apenas do backend
php artisan rollback:manager --backend-only --force

# Simulação (dry-run)
php artisan rollback:manager --dry-run
```

### 2. RollbackStatus
**Comando:** `php artisan rollback:status`

Mostra informações detalhadas sobre o estado atual do sistema:
- Estatísticas gerais de sessões
- Detalhes por domínio
- Informações específicas de sessão
- Breakdown de arquivos por tipo

#### Opções Disponíveis:
```bash
# Status básico
php artisan rollback:status

# Status detalhado
php artisan rollback:status --detailed

# Status de domínio específico
php artisan rollback:status --domain=BlogPost

# Detalhes de sessão específica
php artisan rollback:status --session=session_id_123
```

### 3. RollbackWebInterface
**Comando:** `php artisan rollback:web-interface`

Interface web moderna para gerenciar rollbacks:
- Dashboard com estatísticas
- Tabela de sessões com filtros
- Execução de rollback via navegador
- Modal com detalhes de sessão
- Design responsivo com Tailwind CSS

#### Uso:
```bash
# Iniciar interface web na porta padrão (8080)
php artisan rollback:web-interface

# Iniciar em porta específica
php artisan rollback:web-interface --port=3000

# Acessar via navegador
http://localhost:8080/rollback
```

### 4. FrontendRollbackHandler
Componente especializado para rollback de arquivos de frontend:

#### Funcionalidades:
- **Detecção Inteligente:** Identifica automaticamente arquivos relacionados a domínios
- **Rollback Específico por Domínio:** Remove apenas arquivos do domínio especificado
- **Limpeza Automática:** Remove referências em arquivos de índice, rotas e navegação
- **Suporte Multi-Framework:** Vue.js, TypeScript, Pinia, etc.
- **Verificação de Integridade:** Valida consistência após rollback

#### Tipos de Arquivo Suportados:
- **Componentes Vue:** `.vue`
- **Stores Pinia:** `stores/*.ts`
- **Tipos TypeScript:** `types/*.ts`
- **Serviços API:** `services/*.ts`
- **Páginas/Views:** `pages/*.vue`, `views/*.vue`
- **Rotas:** `router/*.ts`

### 5. RollbackLogger (Melhorado)
Sistema de logging avançado com:
- **Sessões Estruturadas:** Organiza operações em sessões
- **Metadados Detalhados:** Timestamp, usuário, ambiente, etc.
- **Versionamento:** Suporte a múltiplas versões de log
- **Compatibilidade:** Funciona com logs antigos
- **Performance:** Otimizado para grandes volumes de dados

## Estrutura de Dados

### Sessão de Rollback
```json
{
  "id": "session_20250611_123456_abc123",
  "action": "generate_crud",
  "domain": "BlogPost",
  "timestamp": "2025-06-11T12:34:56Z",
  "metadata": {
    "user": "developer",
    "environment": "local",
    "php_version": "8.2.0",
    "laravel_version": "12.0"
  },
  "files": {
    "created": [
      "/path/to/BlogPostController.php",
      "/path/to/BlogPost.php"
    ],
    "modified": {
      "/path/to/routes/api.php": "/backup/path"
    },
    "directories": [
      "/path/to/frontend/components/BlogPost"
    ]
  },
  "summary": {
    "total_files": 15,
    "frontend_files": 8,
    "backend_files": 7
  }
}
```

## Fluxo de Trabalho

### 1. Geração de CRUD
```bash
# Gerar CRUD que criará dados de rollback
php artisan generate:crud --domain

# O sistema automaticamente:
# - Cria sessão de rollback
# - Registra todos os arquivos criados/modificados
# - Gera backups quando necessário
# - Salva metadados da operação
```

### 2. Verificação de Status
```bash
# Ver o que foi gerado
php artisan rollback:status --detailed

# Exemplo de saída:
# 📊 Status do Sistema de Rollback
# ✅ Total de sessões: 3
# 📂 Domínios processados: BlogPost, Ecommerce, UserProfile
# 📝 Total de arquivos: 45 (25 frontend, 20 backend)
```

### 3. Rollback Seletivo
```bash
# Rollback interativo
php artisan rollback:manager --interactive

# Menu será apresentado:
# 🔄 Rollback Completo
# 🎯 Rollback Seletivo por Domínio  ← Escolher esta opção
# 🎨 Apenas Frontend
# 🔧 Apenas Backend
# 📁 Seleção Manual de Arquivos
```

### 4. Rollback de Domínio Frontend
```bash
# Rollback apenas do frontend de um domínio
php artisan rollback:manager --domain=BlogPost --frontend-only

# O sistema irá:
# - Remover componentes Vue do domínio
# - Limpar stores Pinia relacionadas
# - Atualizar arquivos de rota
# - Remover tipos TypeScript
# - Limpar navegação
# - Verificar integridade
```

## Recursos Avançados

### 1. Verificação de Integridade
Após cada rollback, o sistema verifica:
- **Frontend:** Arquivos críticos, sintaxe JSON, estrutura de diretórios
- **Backend:** Controllers, Models, Services, Routes
- **Integração:** Consistência entre APIs e tipos TypeScript

### 2. Relatórios Detalhados
```bash
# Relatório de rollback de domínio específico
🎯 RELATÓRIO DE ROLLBACK DE DOMÍNIO - FRONTEND
======================================================================
📦 Domínio: BlogPost
----------------------------------------------------------------------
📊 ESTATÍSTICAS:
  📁 Total de arquivos encontrados: 12
  ✅ Arquivos removidos com sucesso: 11
  ❌ Falhas ao remover: 1

📋 BREAKDOWN POR TIPO:
  🧩 Componentes: 4 arquivos
  📄 Páginas: 2 arquivos
  🏪 Stores: 1 arquivos
  📝 Tipos: 2 arquivos
  🔧 Serviços: 3 arquivos

🧹 LIMPEZA REALIZADA:
  🗂️  Imports/exports removidos dos arquivos índice
  🛣️  Rotas relacionadas ao domínio removidas
  🧭 Navegação relacionada ao domínio limpa
  🏪 Stores relacionadas ao domínio limpas
```

### 3. Interface Web
- **Dashboard Visual:** Gráficos e estatísticas
- **Operações em Lote:** Seleção múltipla de sessões
- **Filtros Avançados:** Por domínio, data, tipo
- **Logs em Tempo Real:** Acompanhamento de progresso
- **API REST:** Endpoints para automação

## Casos de Uso

### 1. Desenvolvimento Iterativo
```bash
# Gerar versão inicial
php artisan generate:crud --domain

# Testar e encontrar problemas
# ...

# Fazer rollback do frontend para ajustar
php artisan rollback:manager --domain=BlogPost --frontend-only

# Gerar novamente com ajustes
php artisan generate:crud --domain --force
```

### 2. Rollback de Emergência
```bash
# Problema detectado em produção
php artisan rollback:manager --interactive

# Selecionar "Rollback Completo"
# Confirmar operação
# Sistema reverte tudo automaticamente
```

### 3. Limpeza Seletiva
```bash
# Remover apenas componentes de um domínio específico
php artisan rollback:manager --domain=BlogPost --frontend-only

# Manter backend intacto
# Frontend limpo para regeneração
```

## Scripts PowerShell Melhorados

O script `scripts/rollback.ps1` foi aprimorado com:

### Menu Interativo
```powershell
./scripts/rollback.ps1

# Menu:
# [1] Status do Rollback
# [2] Rollback Completo
# [3] Rollback por Domínio
# [4] Rollback Frontend
# [5] Rollback Backend
# [6] Interface Web
# [7] Dry Run
# [0] Sair
```

### Parâmetros Avançados
```powershell
# Rollback de domínio específico
./scripts/rollback.ps1 -Domain "BlogPost" -FrontendOnly -Force

# Dry run
./scripts/rollback.ps1 -DryRun

# Interface web
./scripts/rollback.ps1 -WebInterface -Port 3000

# Help detalhado
./scripts/rollback.ps1 -Help
```

## Melhores Práticas

### 1. Antes de Gerar
```bash
# Sempre verificar status antes de gerar novo CRUD
php artisan rollback:status

# Se houver sessões pendentes, considere rollback
```

### 2. Durante Desenvolvimento
```bash
# Use dry-run para verificar o que será desfeito
php artisan rollback:manager --dry-run

# Use rollback seletivo em vez de completo quando possível
php artisan rollback:manager --domain=Specific --frontend-only
```

### 3. Após Rollback
```bash
# Sempre verificar integridade
php artisan rollback:status --detailed

# Testar aplicação manualmente
# Executar testes automatizados
```

## Troubleshooting

### 1. Problemas de Integridade
```bash
# Verificar problemas específicos
php artisan rollback:integrity --frontend-only

# Tentar correção automática
php artisan rollback:integrity --fix
```

### 2. Sessões Corrompidas
```bash
# Limpar sessões inválidas
php artisan rollback:status --cleanup

# Recriar logs se necessário
php artisan rollback:status --rebuild
```

### 3. Problemas de Frontend
```bash
# Verificar dependências
npm install

# Verificar build
npm run build

# Verificar tipos TypeScript
npm run type-check
```

## Extensibilidade

O sistema foi projetado para ser extensível:

### 1. Novos Tipos de Arquivo
Adicione suporte em `FrontendRollbackHandler`:
```php
private function isFrontendFile(string $file): bool
{
    // Adicionar novas extensões
    $frontendExtensions = ['.vue', '.ts', '.js', '.tsx', '.jsx', '.svelte'];
    // ...
}
```

### 2. Novos Frameworks
Crie handlers específicos:
```php
class ReactRollbackHandler extends FrontendRollbackHandler
{
    // Implementação específica para React
}
```

### 3. Novos Comandos
Estenda a base:
```php
class CustomRollbackCommand extends Command
{
    use RollbackCommandTrait;
    // Implementação customizada
}
```

## Conclusão

O Sistema de Rollback Avançado oferece:
- ✅ **Controle Granular:** Rollback por domínio, tipo, arquivo
- ✅ **Interface Amigável:** CLI interativo e interface web
- ✅ **Segurança:** Verificações e confirmações
- ✅ **Flexibilidade:** Suporte a diferentes cenários
- ✅ **Monitoramento:** Logs detalhados e relatórios
- ✅ **Extensibilidade:** Arquitetura modular

Este sistema transforma o rollback de uma operação binária simples em uma ferramenta poderosa para desenvolvimento ágil e gerenciamento seguro de código gerado.
