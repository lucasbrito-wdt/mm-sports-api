# Laravel CRUD Generator

Gerador CRUD completo para Laravel com suporte a arquitetura de domínios.

## Instalação

```bash
composer require seu-usuario/laravel-crud-generator
```

### Publicar Configurações (Opcional)

```bash
php artisan vendor:publish --tag=crud-generator-config
php artisan vendor:publish --tag=crud-generator-stubs
```

## Uso

### Gerar um CRUD Completo

```bash
php artisan make:crud
```

### Gerar um Domínio

```bash
php artisan make:crud --domain
```

### Rollback

```bash
php artisan make:crud --rollback
```

## Funcionalidades

- ✅ Geração de Models com relacionamentos
- ✅ Migrations com foreign keys
- ✅ Controllers com CRUD completo
- ✅ Services para lógica de negócio
- ✅ Seeders automáticos
- ✅ Permissões automáticas
- ✅ Suporte a relacionamentos bidirecionais
- ✅ Rollback completo
- ✅ Arquitetura de domínios

## Licença

MIT License
