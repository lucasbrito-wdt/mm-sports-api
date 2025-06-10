# ⚡ Guia de Início Rápido - Laravel CRUD Generator

## 🎯 Em 30 segundos

**1. CRUD Básico Interativo:**
```bash
php artisan generate:crud
```

**2. Sistema completo via JSON:**
```bash
php artisan generate:crud --config=examples/ecommerce-category.json --force
```

**3. Desfazer tudo:**
```bash
php artisan generate:crud --rollback
```

---

## 🚀 Cenários Principais

### 💡 Primeiro Uso - Teste Simples

```bash
# Crie um produto básico para testar
php artisan generate:crud --config='{"domain":"Test","model":"Product","schema":"nome=string,100,req;preco=decimal,8,2,req","foreignKeys":[],"force":true}' --force
```

### 🛒 E-commerce Completo (1 comando)

```powershell
# Windows PowerShell
.\scripts\generate-ecommerce.ps1
```

```bash
# Linux/Mac
./scripts/generate-ecommerce.sh
```

### 📝 Blog Simples

```bash
# Categorias primeiro
php artisan generate:crud --config=examples/blog-category.json --force

# Posts depois (dependem de categorias)
php artisan generate:crud --config=examples/blog-post.json --force
```

---

## 🏗️ Criar Domínio do Zero

### Modo Interativo
```bash
php artisan generate:crud --domain
```

### Via JSON
```json
{
    "domain": "MeuDominio",
    "model": "MinhaModel", 
    "schema": "nome=string,100,req",
    "generateCompleteStructure": true,
    "force": true
}
```

### 🔥 NOVO: Múltiplos CRUDs em um Domínio
```json
{
    "domain": "BlogSystem",
    "model": "Post",
    "schema": "title=string,200,req;content=text,req",
    "generateCompleteStructure": true,
    "force": true,
    "crud": [
        {
            "model": "Comment",
            "schema": "post_id=integer,req;author_name=string,100,req;comment_text=text,req"
        },
        {
            "model": "Tag", 
            "schema": "name=string,50,req;slug=string,50,unique"
        }
    ]
}
```

**Comando:**
```bash
php artisan generate:crud --config=@examples/blog-complete-system.json --domain --force
```

**Resultado:** 4 CRUDs completos (Post, Comment, Tag, Category) com frontend e backend!

---

## 📋 Sintaxe Rápida de Schema

| Tipo | Exemplo | Resultado |
|------|---------|-----------|
| Texto | `nome=string,100,req` | VARCHAR(100) NOT NULL |
| Número | `preco=decimal,8,2,req` | DECIMAL(8,2) NOT NULL |
| Booleano | `ativo=boolean` | BOOLEAN |
| Data | `nascimento=date,null` | DATE NULL |
| FK | `categoria_id=foreign,categories,id` | Foreign Key |

**Modificadores:**
- `req` = NOT NULL
- `null` = NULL 
- `unique` = UNIQUE
- `index` = INDEX

---

## 🔗 Relacionamentos Rápidos

```json
{
    "schema": "produto_id=foreign,products,id;quantidade=integer,req",
    "foreignKeys": [
        {
            "localKey": "produto_id",
            "foreignTable": "products", 
            "foreignKey": "id",
            "displayField": "nome"
        }
    ]
}
```

---

## 🔄 Comandos de Limpeza

```bash
# Rollback completo
php artisan generate:crud --rollback

# Limpar cache (após rollback)
php artisan cache:clear && php artisan route:clear

# Verificar status das migrations
php artisan migrate:status
```

---

## 🐛 Resolução Rápida de Problemas

### Erro: "Domain not found"
```bash
# Criar o domínio primeiro
php artisan generate:crud --domain
```

### Erro: "Foreign table not exists" 
```bash
# Criar a tabela referenciada primeiro
php artisan generate:crud --config=tabela-pai.json --force
php artisan generate:crud --config=tabela-filha.json --force
```

### Erro: "Permission denied"
```bash
# Windows PowerShell (como Administrador)
icacls app /grant Users:F /T
icacls database /grant Users:F /T
icacls resources /grant Users:F /T
```

---

## 📁 Estrutura Gerada (Resumo)

```
app/Domains/{Domain}/
├── Models/{Model}.php          # Eloquent Model
├── Controllers/{Model}Controller.php  # CRUD Controller  
└── Services/{Model}Service.php # Business Logic

database/migrations/
└── create_{table}_table.php    # Database Schema

resources/frontend/src/domains/{domain}/
├── types/{Model}.ts           # TypeScript Types
├── stores/{model}Store.ts     # Pinia Store
├── services/{model}Service.ts # API Service
└── components/               # Vue Components
    ├── {Model}List.vue
    ├── {Model}Form.vue
    ├── Criar{Model}.vue
    └── Editar{Model}.vue
```

---

## 🎉 Próximos Passos

1. **Teste um exemplo:** `.\scripts\generate-ecommerce.ps1`
2. **Explore o código gerado:** `app/Domains/`
3. **Execute as migrations:** `php artisan migrate`
4. **Teste o frontend:** Abra os componentes Vue
5. **Customize conforme necessário**

---

## 📚 Documentação Completa

- **[MANUAL.md](MANUAL.md)** - Documentação completa
- **[examples/](examples/)** - Exemplos prontos  
- **[scripts/](scripts/)** - Scripts de automação

**Dica:** Sempre use `--force` em produção para pular confirmações!
