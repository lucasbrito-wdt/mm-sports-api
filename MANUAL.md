# 📖 Manual de Uso Completo - Laravel CRUD Generator

## 🎯 Visão Geral

O Laravel CRUD Generator é um sistema completo para geração automática de operações CRUD (Create, Read, Update, Delete) em aplicações Laravel com arquitetura de domínios. O sistema suporta geração de backend (Laravel) e frontend (Vue.js/TypeScript), além de um sistema robusto de rollback.

### ✨ Principais Funcionalidades

- 🔧 **Geração de CRUD Completo** - Backend e frontend em um comando
- 🏗️ **Gerador de Domínios** - Criação de estruturas completas de domínios
- 🔄 **Sistema de Rollback** - Desfazer alterações com segurança
- 📝 **Configuração via JSON** - Automação através de arquivos de configuração
- 🔗 **Relacionamentos** - Suporte completo a foreign keys e relacionamentos
- 🧪 **Testes e Documentação** - Geração opcional de testes e docs
- 🎨 **Frontend Moderno** - Componentes Vue.js com TypeScript

---

## 🚀 Instalação e Configuração

### Pré-requisitos

- Laravel 10+
- PHP 8.1+
- Node.js 16+ (para frontend)
- Composer

### Verificação da Instalação

O comando está disponível através do Artisan:

```bash
php artisan generate:crud --help
```

---

## 📋 Comandos Disponíveis

### 🔧 Comando Principal

```bash
php artisan generate:crud [opções]
```

### 🎛️ Opções Disponíveis


| Opção           | Descrição                            | Exemplo                |
| ----------------- | -------------------------------------- | ---------------------- |
| `--force`         | Executa sem confirmações interativas | `--force`              |
| `--skip-frontend` | Pula a geração do frontend           | `--skip-frontend`      |
| `--skip-backend`  | Pula a geração do backend            | `--skip-backend`       |
| `--with-tests`    | Inclui geração de testes             | `--with-tests`         |
| `--with-docs`     | Inclui geração de documentação     | `--with-docs`          |
| `--config=`       | Usa configuração via JSON            | `--config=config.json` |
| `--domain`        | Gera um domínio completo              | `--domain`             |
| `--rollback`      | Desfaz alterações anteriores         | `--rollback`           |

---

## 🛠️ Modo de Uso

### 1. 📝 Geração de CRUD Interativo

Execute o comando básico para modo interativo:

```bash
php artisan generate:crud
```

O sistema irá solicitar:

1. **Domínio**: Nome do domínio existente (ex: `Users`, `Products`)
2. **Model**: Nome da model (ex: `Product`, `User`)
3. **Schema**: Definição dos campos da tabela
4. **Foreign Keys**: Relacionamentos opcionais
5. **Confirmações**: Validação antes da geração

#### 🔍 Exemplo Prático - Produto

```bash
$ php artisan generate:crud

🚀 Gerador de CRUD

Domínio: Products
Model: Product
Schema: nome=string,100,req;descricao=text;preco=decimal,8,2,req;categoria_id=foreign,categories,id
Foreign Keys: categoria_id:categories:id:nome

✅ Confirma a geração do CRUD? Sim
```

### 2. 🏗️ Geração de Domínio Completo

Para criar um domínio inteiro (estrutura + CRUD):

```bash
php artisan generate:crud --domain
```

O sistema criará:

- Estrutura de diretórios do domínio
- Model base
- Controller base
- Service base
- Arquivos de configuração

#### 🔗 Múltiplos CRUDs em um Domínio

**NOVA FUNCIONALIDADE**: Agora é possível gerar múltiplos CRUDs relacionados dentro de um único domínio:

```json
{
  "domain": "BlogSystem",
  "model": "Post",
  "schema": "title=string,200,req;content=text,req",
  "generateCompleteStructure": true,
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

Esta configuração gerará:
- **CRUD Principal**: Post (Model, Controller, Service, Migration, Seeder, Frontend)
- **CRUDs Adicionais**: Comment e Tag (todos os arquivos para cada um)
- **Frontend Completo**: Components, Stores, Services para todos os modelos
- **Rotas Organizadas**: Todas as rotas no mesmo arquivo de domínio

### 3. ⚡ Geração via Configuração JSON

Para automação, use arquivos de configuração:

```bash
php artisan generate:crud --config=config.json --force
```

---

## 📄 Configuração via JSON

### 🔧 Estrutura Básica

Crie um arquivo `config.json`:

```json
{
    "domain": "Products",
    "model": "Product",
    "schema": "nome=string,100,req;descricao=text;preco=decimal,8,2,req",
    "foreignKeys": [],
    "force": true,
    "generateCompleteStructure": false,
    "generateTests": false
}
```

### 📋 Parâmetros de Configuração

#### 🏷️ Parâmetros Obrigatórios


| Parâmetro | Tipo   | Descrição            | Exemplo                 |
| ---------- | ------ | ---------------------- | ----------------------- |
| `domain`   | String | Nome do domínio       | `"Products"`            |
| `model`    | String | Nome da model          | `"Product"`             |
| `schema`   | String | Definição dos campos | `"nome=string,100,req"` |

#### ⚙️ Parâmetros Opcionais


| Parâmetro                  | Tipo    | Padrão | Descrição                  |
| --------------------------- | ------- | ------- | ---------------------------- |
| `foreignKeys`               | Array   | `[]`    | Lista de relacionamentos     |
| `force`                     | Boolean | `false` | Execução sem confirmação |
| `generateCompleteStructure` | Boolean | `false` | Gera estrutura de domínio   |
| `generateTests`             | Boolean | `false` | Inclui testes                |

### 🔗 Configuração de Relacionamentos

```json
{
    "domain": "Products",
    "model": "Product",
    "schema": "nome=string,100,req;categoria_id=foreign,categories,id",
    "foreignKeys": [
        {
            "localKey": "categoria_id",
            "foreignTable": "categories",
            "foreignKey": "id",
            "displayField": "nome"
        }
    ]
}
```

### 📁 Carregamento de Configuração

#### Opção 1: Arquivo Local

```bash
php artisan generate:crud --config=config.json
```

#### Opção 2: Caminho Absoluto

```bash
php artisan generate:crud --config=@/caminho/completo/para/config.json
```

#### Opção 3: JSON Inline

```bash
php artisan generate:crud --config='{"domain":"Test","model":"Test","schema":"nome=string,100,req"}'
```

---

## 🗃️ Definição de Schema

### 📝 Sintaxe do Schema

O schema define os campos da tabela usando a sintaxe:

```
campo=tipo[,tamanho][,precisao][,modificadores]
```

### 🔤 Tipos de Campo Suportados


| Tipo        | Descrição       | Exemplo                              | MySQL Equivalente |
| ----------- | ----------------- | ------------------------------------ | ----------------- |
| `string`    | Texto curto       | `nome=string,100`                    | `VARCHAR(100)`    |
| `text`      | Texto longo       | `descricao=text`                     | `TEXT`            |
| `integer`   | Número inteiro   | `idade=integer`                      | `INT`             |
| `decimal`   | Número decimal   | `preco=decimal,8,2`                  | `DECIMAL(8,2)`    |
| `boolean`   | Verdadeiro/Falso  | `ativo=boolean`                      | `BOOLEAN`         |
| `date`      | Data              | `nascimento=date`                    | `DATE`            |
| `datetime`  | Data e hora       | `criado_em=datetime`                 | `DATETIME`        |
| `timestamp` | Timestamp         | `atualizado=timestamp`               | `TIMESTAMP`       |
| `foreign`   | Chave estrangeira | `categoria_id=foreign,categories,id` | `BIGINT UNSIGNED` |

### 🏷️ Modificadores


| Modificador | Descrição                   | Exemplo                   |
| ----------- | ----------------------------- | ------------------------- |
| `req`       | Campo obrigatório (NOT NULL) | `nome=string,100,req`     |
| `null`      | Campo opcional (NULL)         | `descricao=text,null`     |
| `unique`    | Valor único                  | `email=string,100,unique` |
| `index`     | Criar índice                 | `codigo=string,50,index`  |

### 📋 Exemplos Práticos

#### Produto Simples

```
nome=string,100,req;preco=decimal,8,2,req;ativo=boolean
```

#### Usuário Completo

```
nome=string,100,req;email=string,100,req,unique;senha=string,255,req;nascimento=date,null;perfil_id=foreign,perfis,id
```

#### Post de Blog

```
titulo=string,200,req;conteudo=text,req;publicado=boolean;autor_id=foreign,users,id;categoria_id=foreign,categorias,id
```

---

## 🔗 Sistema de Relacionamentos

### 🏗️ Configuração de Foreign Keys

Os relacionamentos são definidos em duas partes:

1. **No Schema**: `campo=foreign,tabela,chave`
2. **No Array foreignKeys**: Detalhes do relacionamento

### 📋 Exemplo Completo

```json
{
    "domain": "Pedidos",
    "model": "Pedido",
    "schema": "numero=string,50,req;cliente_id=foreign,clientes,id;produto_id=foreign,produtos,id;quantidade=integer,req",
    "foreignKeys": [
        {
            "localKey": "cliente_id",
            "foreignTable": "clientes",
            "foreignKey": "id",
            "displayField": "nome"
        },
        {
            "localKey": "produto_id",
            "foreignTable": "produtos",
            "foreignKey": "id",
            "displayField": "nome"
        }
    ]
}
```

### 🔄 Relacionamentos Bidirecionais

O sistema automaticamente configura relacionamentos nos dois sentidos:

- **belongsTo**: No model filho
- **hasMany**: No model pai

---

## 📁 Estrutura de Arquivos Gerados

### 🗂️ Backend (Laravel)

```
app/
├── Domains/
│   └── {Domain}/
│       ├── Models/
│       │   └── {Model}.php
│       ├── Controllers/
│       │   └── {Model}Controller.php
│       ├── Services/
│       │   └── {Model}Service.php
│       └── README.md
├── database/
│   ├── migrations/
│   │   └── create_{table}_table.php
│   └── seeders/
│       └── {Model}Seeder.php
└── routes/
    └── api.php (atualizado)
```

### 🎨 Frontend (Vue.js/TypeScript)

```
resources/
└── frontend/
    └── src/
        └── domains/
            └── {domain}/
                ├── types/
                │   └── {Model}.ts
                ├── stores/
                │   └── {model}Store.ts
                ├── services/
                │   └── {model}Service.ts
                ├── components/
                │   ├── {Model}List.vue
                │   ├── {Model}Form.vue
                │   ├── Criar{Model}.vue
                │   └── Editar{Model}.vue
                └── pages/
                    ├── Criar{Model}.vue
                    └── Editar{Model}.vue
```

---

## 🔄 Sistema de Rollback

### 🛡️ Funcionalidade de Rollback

O sistema mantém um log de todas as alterações para permitir rollback seguro:

```bash
php artisan generate:crud --rollback
```

### 📝 Log de Rollback

O arquivo de log fica em: `storage/framework/rollback/rollback.json`

Estrutura do log:

```json
{
    "timestamp": "2024-01-15T10:30:00Z",
    "domain": "Products",
    "model": "Product",
    "files_created": [
        "app/Domains/Products/Models/Product.php",
        "database/migrations/2024_01_15_103000_create_products_table.php"
    ],
    "files_updated": [
        "routes/web.php"
    ],
    "frontend_files": [
        "resources/frontend/src/domains/products/types/Product.ts"
    ]
}
```

### ⚠️ Cuidados com Rollback

- Sempre fazer backup antes de executar rollback
- Verificar se não há dados importantes nas tabelas
- Rollback remove arquivos permanentemente

---

## 🎛️ Exemplos Avançados

### 🛒 E-commerce Completo

#### 1. Categoria de Produtos

```json
{
    "domain": "Category",
    "model": "Category",
    "schema": "nome=string,100,req;descricao=text,null;ativa=boolean",
    "foreignKeys": [],
    "force": true
}
```

#### 2. Produtos

```json
{
    "domain": "Product",
    "model": "Product",
    "schema": "nome=string,100,req;descricao=text;preco=decimal,10,2,req;categoria_id=foreign,categories,id;ativo=boolean",
    "foreignKeys": [
        {
            "localKey": "categoria_id",
            "foreignTable": "categories",
            "foreignKey": "id",
            "displayField": "nome"
        }
    ],
    "force": true
}
```

#### 3. Pedidos

```json
{
    "domain": "Orders",
    "model": "Order",
    "schema": "numero=string,50,req,unique;cliente_id=foreign,users,id;total=decimal,10,2,req;status=string,50",
    "foreignKeys": [
        {
            "localKey": "cliente_id",
            "foreignTable": "users",
            "foreignKey": "id",
            "displayField": "name"
        }
    ],
    "force": true
}
```

### 📝 Blog System

#### 1. Categorias

```bash
php artisan generate:crud --config='{"domain":"Blog","model":"Category","schema":"nome=string,100,req;slug=string,100,req,unique;descricao=text","foreignKeys":[],"force":true}'
```

#### 2. Posts

```bash
php artisan generate:crud --config='{"domain":"Blog","model":"Post","schema":"titulo=string,200,req;slug=string,200,req,unique;conteudo=text,req;publicado=boolean;autor_id=foreign,users,id;categoria_id=foreign,categories,id","foreignKeys":[{"localKey":"autor_id","foreignTable":"users","foreignKey":"id","displayField":"name"},{"localKey":"categoria_id","foreignTable":"categories","foreignKey":"id","displayField":"nome"}],"force":true}'
```

---

## 🐛 Troubleshooting

### ❗ Problemas Comuns

#### 1. Erro de Permissão

```
Error: Permission denied
```

**Solução**: Verificar permissões de escrita nas pastas:

```bash
chmod -R 775 app/Domains/
chmod -R 775 database/migrations/
chmod -R 775 resources/frontend/
```

#### 2. Domínio Não Encontrado

```
Error: Domain 'Products' not found
```

**Solução**: Verificar se o domínio existe ou criar com `--domain`:

```bash
php artisan generate:crud --domain
```

#### 3. Arquivo de Configuração Inválido

```
Error: Invalid JSON configuration
```

**Solução**: Validar JSON:

```bash
# Usar ferramenta online ou
cat config.json | jq .
```

#### 4. Foreign Key Inválida

```
Error: Foreign table 'categories' not found
```

**Solução**: Verificar se a tabela referenciada existe:

```sql
SHOW TABLES LIKE 'categories';
```

### 🔧 Comandos de Diagnóstico

#### Verificar Estrutura de Domínios

```bash
ls -la app/Domains/
```

#### Verificar Migrations

```bash
php artisan migrate:status
```

#### Verificar Rotas

```bash
php artisan route:list | grep -i {model}
```

#### Limpar Cache

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

---

## 📚 Boas Práticas

### 🎯 Nomenclatura

#### Padrões Recomendados

- **Domínios**: PascalCase plural (`Products`, `Users`, `Orders`)
- **Models**: PascalCase singular (`Product`, `User`, `Order`)
- **Campos**: snake_case (`nome_completo`, `data_nascimento`)
- **Tabelas**: snake_case plural (`products`, `users`, `orders`)

#### Exemplos

```json
{
    "domain": "UserManagement",      // ✅ Correto
    "model": "User",                 // ✅ Correto
    "schema": "nome_completo=string,100,req"  // ✅ Correto
}
```

### 🏗️ Estrutura de Projeto

#### Organização por Domínio

```
app/Domains/
├── UserManagement/
│   ├── Models/User.php
│   ├── Controllers/UserController.php
│   └── Services/UserService.php
├── ProductCatalog/
│   ├── Models/Product.php
│   └── Models/Category.php
└── OrderManagement/
    ├── Models/Order.php
    └── Models/OrderItem.php
```

### 🔒 Segurança

#### Foreign Keys

- Sempre definir relacionamentos completos
- Usar `ON DELETE CASCADE` quando apropriado
- Validar integridade referencial

#### Validações

- Usar `req` para campos obrigatórios
- Implementar `unique` quando necessário
- Definir tamanhos apropriados para strings

### ⚡ Performance

#### Índices

- Adicionar `index` em campos de busca frequente
- Foreign keys automaticamente recebem índices
- Considerar índices compostos para consultas complexas

#### Paginação

- Frontend gerado inclui paginação automática
- Configurar limites apropriados no backend

---

## 🔧 Configurações Avançadas

### 🎨 Customização de Templates

O sistema permite customização de templates através de stubs:

```bash
php artisan vendor:publish --tag=crud-generator-stubs
```

### 🔌 Extensões

#### Adicionar Novos Tipos de Campo

1. Editar `SchemaValidator.php`
2. Adicionar processamento em `MigrationGenerator.php`
3. Atualizar frontend em `TypesGenerator.php`

#### Custom Generators

1. Implementar interface `GeneratorInterface`
2. Registrar no `CrudGenerator.php`
3. Adicionar ao pipeline de geração

### 🌐 Localização

O sistema suporta múltiplas linguagens:

- Labels automáticos baseados em nomes de campos
- Mensagens de validação traduzidas
- Documentação em português

---

## 📊 Monitoramento e Logs

### 📝 Logs do Sistema

Os logs ficam em:

- **Aplicação**: `storage/logs/laravel.log`
- **Rollback**: `storage/framework/rollback/rollback_log.json`
- **Geração**: Output do comando

### 📈 Métricas

Para monitorar o uso:

```bash
# Contar CRUDs gerados
find app/Domains -name "*Controller.php" | wc -l

# Verificar migrations
php artisan migrate:status | grep -c "Y"

# Arquivos frontend gerados
find resources/frontend -name "*.vue" | wc -l
```

---

## 🆘 Suporte e Comunidade

### 📞 Canais de Suporte

- **Issues**: Reportar bugs e solicitar features
- **Documentação**: Este manual e README.md
- **Código**: Análise do código fonte

### 🤝 Contribuição

Para contribuir:

1. Fork do repositório
2. Criar branch para feature
3. Implementar melhorias
4. Testes abrangentes
5. Pull request

### 📋 Roadmap

Funcionalidades planejadas:

- [ ]  Suporte a relacionamentos many-to-many
- [ ]  Geração de APIs REST
- [ ]  Integração com GraphQL
- [ ]  Testes automatizados mais abrangentes
- [ ]  Interface web para configuração

---

## 📄 Licença

MIT License - Veja o arquivo LICENSE para detalhes.

---

## 🎉 Conclusão

O Laravel CRUD Generator é uma ferramenta poderosa para acelerar o desenvolvimento de aplicações Laravel com arquitetura de domínios. Com suporte completo a backend, frontend e rollback, permite criar CRUDs robustos em minutos.

Para dúvidas específicas, consulte os exemplos neste manual ou analise o código fonte dos geradores.

**Bom desenvolvimento! 🚀**
