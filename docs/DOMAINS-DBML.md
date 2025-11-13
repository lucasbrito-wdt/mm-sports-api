# Guia: Geração de Domínios a partir de DBML

## Comando

php artisan generate:domains-from-dbml {arquivo.dbml} [opções]## Opções

- `--domain-prefix`: Adiciona um prefixo aos nomes dos domínios (ex: `--domain-prefix=Admin`)
- `--force`: Força a criação mesmo se o domínio já existir
- `--skip-frontend`: Não gera arquivos frontend
- `--skip-backend`: Não gera arquivos backend
- `--dry-run`: Apenas mostra a ordem sem executar

## Exemplos

### 1. Análise sem execução (dry-run)
php artisan generate:domains-from-dbml iva.dbml --dry-run### 2. Geração completa
php artisan generate:domains-from-dbml iva.dbml --force### 3. Apenas backendh
php artisan generate:domains-from-dbml iva.dbml --skip-frontend --force### 4. Com prefixo
php artisan generate:domains-from-dbml iva.dbml --domain-prefix=Admin --force## Como funciona

1. **Análise**: O comando lê o arquivo DBML e identifica todas as tabelas
2. **Dependências**: Identifica chaves estrangeiras e mapeia dependências
3. **Ordenação**: Ordena as tabelas usando topological sort:
   - Primeiro: Tabelas sem dependências (FASE 1)
   - Depois: Tabelas com dependências (FASE 2)
4. **Geração**: Cria os domínios na ordem correta

## Formato DBML Suportado

O comando suporta o formato padrão DBML:

Table users {
  id integer [primary key]
  name varchar
  company_id integer [ref: > companies.id]
}

Table companies {
  id integer [primary key]
  name varchar
}## Ordem de Criação

O comando sempre cria na seguinte ordem:

1. **FASE 1**: Tabelas sem chaves estrangeiras
2. **FASE 2**: Tabelas com chaves estrangeiras (respeitando dependências)

## Validações

- ✅ Detecta ciclos de dependência
- ✅ Valida existência de tabelas referenciadas
- ✅ Verifica se arquivo DBML existe
- ✅ Confirma antes de executar (exceto com --force)