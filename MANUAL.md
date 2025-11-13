# 📖 Manual de Uso Completo - Laravel CRUD Generator

## 🎯 Visão Geral

O Laravel CRUD Generator é um sistema completo para geração automática de operações CRUD (Create, Read, Update, Delete) em aplicações Laravel com arquitetura de domínios. O sistema suporta geração de backend (Laravel) e frontend (Vue.js/TypeScript), além de um sistema robusto de rollback.

### ✨ Principais Funcionalidades

-   🔧 **Geração de CRUD Completo** - Backend e frontend em um comando
-   🏗️ **Gerador de Domínios** - Criação de estruturas completas de domínios
-   🔄 **Sistema de Rollback** - Desfazer alterações com segurança
-   📝 **Configuração via JSON** - Automação através de arquivos de configuração
-   🔗 **Relacionamentos** - Suporte completo a foreign keys e relacionamentos
-   🧪 **Testes e Documentação** - Geração opcional de testes e docs
-   🎨 **Frontend Moderno** - Componentes Vue.js com TypeScript

---

## 🚀 Instalação e Configuração

### Pré-requisitos

-   Laravel 10+
-   PHP 8.1+
-   Node.js 16+ (para frontend)
-   Composer

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

### 🗄️ Comando de Geração a partir de DBML

```bash
php artisan generate:domains-from-dbml {arquivo.dbml} [opções]
```

### 🎛️ Opções Disponíveis

#### Para `generate:crud`

| Opção             | Descrição                            | Exemplo                |
| ----------------- | ------------------------------------ | ---------------------- |
| `--force`         | Executa sem confirmações interativas | `--force`              |
| `--skip-frontend` | Pula a geração do frontend           | `--skip-frontend`      |
| `--skip-backend`  | Pula a geração do backend            | `--skip-backend`       |
| `--with-tests`    | Inclui geração de testes             | `--with-tests`         |
| `--with-docs`     | Inclui geração de documentação       | `--with-docs`          |
| `--config=`       | Usa configuração via JSON            | `--config=config.json` |
| `--domain`        | Gera um domínio completo             | `--domain`             |
| `--rollback`      | Desfaz alterações anteriores         | `--rollback`           |

#### Para `generate:domains-from-dbml`

| Opção              | Descrição                                      | Exemplo                      |
| ------------------ | ---------------------------------------------- | ---------------------------- |
| `--domain-prefix=` | Adiciona prefixo aos nomes dos domínios       | `--domain-prefix=Admin`      |
| `--force`          | Força criação mesmo se domínio já existir      | `--force`                    |
| `--skip-frontend`  | Não gera arquivos frontend                     | `--skip-frontend`            |
| `--skip-backend`   | Não gera arquivos backend                      | `--skip-backend`             |
| `--dry-run`        | Apenas mostra a ordem sem executar             | `--dry-run`                  |

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

-   Estrutura de diretórios do domínio
-   Model base
-   Controller base
-   Service base
-   Arquivos de configuração

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

-   **CRUD Principal**: Post (Model, Controller, Service, Migration, Seeder, Frontend)
-   **CRUDs Adicionais**: Comment e Tag (todos os arquivos para cada um)
-   **Frontend Completo**: Components, Stores, Services para todos os modelos
-   **Rotas Organizadas**: Todas as rotas no mesmo arquivo de domínio

### 3. 🗄️ Geração de Domínios a partir de DBML

**NOVA FUNCIONALIDADE**: Gere múltiplos domínios automaticamente a partir de um arquivo DBML, respeitando a ordem correta das dependências de chaves estrangeiras.

#### 📋 Comando

```bash
php artisan generate:domains-from-dbml {arquivo.dbml} [opções]
```

#### 🎛️ Opções Disponíveis

| Opção              | Descrição                                      | Exemplo                      |
| ------------------ | ---------------------------------------------- | ---------------------------- |
| `--domain-prefix=` | Adiciona prefixo aos nomes dos domínios       | `--domain-prefix=Admin`      |
| `--force`          | Força criação mesmo se domínio já existir      | `--force`                    |
| `--skip-frontend`  | Não gera arquivos frontend                     | `--skip-frontend`            |
| `--skip-backend`   | Não gera arquivos backend                      | `--skip-backend`             |
| `--dry-run`        | Apenas mostra a ordem sem executar             | `--dry-run`                  |

#### 🔍 Como Funciona

O comando analisa o arquivo DBML e:

1. **Análise**: Identifica todas as tabelas e seus campos
2. **Dependências**: Mapeia chaves estrangeiras e relacionamentos
3. **Ordenação**: Ordena as tabelas usando topological sort:
   - **FASE 1**: Tabelas sem chaves estrangeiras (criadas primeiro)
   - **FASE 2**: Tabelas com chaves estrangeiras (respeitando dependências)
4. **Geração**: Cria os domínios na ordem correta automaticamente

#### 📝 Exemplos de Uso

**1. Análise sem execução (dry-run)**

```bash
php artisan generate:domains-from-dbml iva.dbml --dry-run
```

**2. Geração completa**

```bash
php artisan generate:domains-from-dbml iva.dbml --force
```

**3. Apenas backend**

```bash
php artisan generate:domains-from-dbml iva.dbml --skip-frontend --force
```

**4. Com prefixo nos domínios**

```bash
php artisan generate:domains-from-dbml iva.dbml --domain-prefix=Admin --force
```

#### 📄 Formato DBML Suportado

O comando suporta o formato padrão DBML:

```dbml
Table companies {
  id integer [primary key]
  name varchar
  document varchar
  email varchar
  logo varchar
}

Table users {
  id integer [primary key]
  name varchar
  email varchar
  company_id integer [ref: > companies.id]
}

Table posts {
  id integer [primary key]
  title varchar
  content text
  user_id integer [ref: > users.id]
}
```

#### 🔄 Ordem de Criação Automática

O comando sempre cria os domínios na seguinte ordem:

**FASE 1: Domínios SEM chaves estrangeiras**
- Companies
- Courses
- Modules
- Classes
- Ebooks
- Products
- Roles
- Permissions

**FASE 2: Domínios COM chaves estrangeiras**
- Users (depende de: Companies)
- Posts (depende de: Users)
- CoursesStudents (depende de: Users, Courses)
- RoleHasUsers (depende de: Users, Roles)
- RoleHasPermissions (depende de: Roles, Permissions)

#### ✅ Validações Automáticas

- ✅ Detecta ciclos de dependência
- ✅ Valida existência de tabelas referenciadas
- ✅ Verifica se arquivo DBML existe
- ✅ Confirma antes de executar (exceto com `--force`)
- ✅ Exibe ordem de criação antes de executar

#### 🎯 Exemplo Completo

```bash
$ php artisan generate:domains-from-dbml iva.dbml --dry-run

🚀 Gerador de Domínios a partir de DBML

📖 Analisando arquivo DBML...
🔀 Ordenando tabelas por dependências...

📋 Ordem de criação dos domínios:

FASE 1: Domínios SEM chaves estrangeiras
───────────────────────────────────────────────────
  1. Companie (companies)
  2. Cours (courses)
  3. Module (modules)
  4. Classe (classes)
  5. Ebook (ebooks)
  6. Product (products)
  7. Role (roles)
  8. Permissiom (permissions)

FASE 2: Domínios COM chaves estrangeiras
───────────────────────────────────────────────────
  9. User (users) → depende de: companies
  10. CoursesStudent (courses_students) → depende de: users, courses
  11. RoleHasUser (role_has_users) → depende de: users, roles
  12. RoleHasPermissiom (role_has_permissions) → depende de: roles, permissions
  13. Post (posts) → depende de: users

✅ Modo dry-run: nenhuma alteração foi feita.
```

#### 💡 Dicas de Uso

- Use `--dry-run` primeiro para verificar a ordem antes de executar
- O comando detecta automaticamente tipos de campos (varchar, text, integer, etc.)
- Chaves estrangeiras são automaticamente marcadas como obrigatórias (`req`)
- Tabelas pivot (many-to-many) são criadas na FASE 2 após suas dependências

### 4. ⚡ Geração via Configuração JSON

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
| --------- | ------ | -------------------- | ----------------------- |
| `domain`  | String | Nome do domínio      | `"Products"`            |
| `model`   | String | Nome da model        | `"Product"`             |
| `schema`  | String | Definição dos campos | `"nome=string,100,req"` |

#### ⚙️ Parâmetros Opcionais

| Parâmetro                   | Tipo    | Padrão  | Descrição                 |
| --------------------------- | ------- | ------- | ------------------------- |
| `foreignKeys`               | Array   | `[]`    | Lista de relacionamentos  |
| `force`                     | Boolean | `false` | Execução sem confirmação  |
| `generateCompleteStructure` | Boolean | `false` | Gera estrutura de domínio |
| `generateTests`             | Boolean | `false` | Inclui testes             |

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

#### Tipos Básicos

| Tipo         | Descrição         | Exemplo                                | MySQL Equivalente | Frontend        |
| ------------ | ----------------- | -------------------------------------- | ----------------- | --------------- |
| `string`     | Texto curto       | `nome=string,100`                      | `VARCHAR(100)`    | Input text      |
| `text`       | Texto longo       | `descricao=text`                       | `TEXT`            | Textarea        |
| `integer`    | Número inteiro    | `idade=integer`                        | `INT`             | Input number    |
| `bigInteger` | Número grande     | `contador=bigInteger`                  | `BIGINT`          | Input number    |
| `decimal`    | Número decimal    | `preco=decimal,8,2`                    | `DECIMAL(8,2)`    | Input currency  |
| `float`      | Número flutuante  | `taxa=float`                           | `FLOAT`           | Input currency  |
| `double`     | Decimal duplo     | `valor=double`                         | `DOUBLE`          | Input currency  |
| `boolean`    | Verdadeiro/Falso  | `ativo=boolean`                        | `BOOLEAN`         | Switch          |
| `date`       | Data              | `nascimento=date`                      | `DATE`            | Date picker     |
| `datetime`   | Data e hora       | `criado_em=datetime`                   | `DATETIME`        | DateTime picker |
| `timestamp`  | Timestamp         | `atualizado=timestamp`                 | `TIMESTAMP`       | DateTime picker |
| `time`       | Hora              | `horario=time`                         | `TIME`            | Input text      |
| `json`       | Dados JSON        | `metadata=json`                        | `JSON`            | Textarea        |
| `enum`       | Lista de valores  | `status=enum,ativo\|inativo\|pendente` | `ENUM`            | Select          |
| `foreign`    | Chave estrangeira | `categoria_id=foreign,categories,id`   | `BIGINT UNSIGNED` | Autocomplete    |

#### Tipos Especiais (Detectados pelo Nome do Campo)

O sistema detecta automaticamente campos especiais baseado no **nome do campo** e aplica máscaras e validações apropriadas:

| Padrão Nome  | Tipo Gerado | Frontend               | Validação        | Exemplo                    |
| ------------ | ----------- | ---------------------- | ---------------- | -------------------------- |
| `*cpf*`      | CPF         | Input com máscara CPF  | Validador CPF    | `cpf=string,14,req`        |
| `*cnpj*`     | CNPJ        | Input com máscara CNPJ | Validador CNPJ   | `cnpj=string,18,req`       |
| `*telefone*` | Telefone    | Input com máscara Tel  | Validador Tel    | `telefone=string,15,req`   |
| `*celular*`  | Celular     | Input com máscara Cel  | Validador Tel    | `celular=string,15,req`    |
| `*mobile*`   | Celular     | Input com máscara Cel  | Validador Tel    | `mobile=string,15,req`     |
| `*preco*`    | Moeda       | Input currency         | Numérico         | `preco=decimal,10,2,req`   |
| `*valor*`    | Moeda       | Input currency         | Numérico         | `valor_total=decimal,10,2` |
| `*price*`    | Moeda       | Input currency         | Numérico         | `price=decimal,10,2`       |
| `*image*`    | Imagem      | Image uploader         | Arquivo imagem   | `imagem=string,255`        |
| `*foto*`     | Imagem      | Image uploader         | Arquivo imagem   | `foto_perfil=string,255`   |
| `*avatar*`   | Imagem      | Image uploader         | Arquivo imagem   | `avatar=string,255`        |
| `*file*`     | Arquivo     | File uploader          | Arquivo genérico | `arquivo=string,255`       |
| `*arquivo*`  | Arquivo     | File uploader          | Arquivo genérico | `arquivo_pdf=string,255`   |
| `*document*` | Arquivo     | File uploader          | Arquivo genérico | `document=string,255`      |
| `*email*`    | Email       | Input email            | Validador email  | `email=string,100,req`     |

### 🏷️ Modificadores

| Modificador | Descrição                    | Exemplo                   |
| ----------- | ---------------------------- | ------------------------- |
| `req`       | Campo obrigatório (NOT NULL) | `nome=string,100,req`     |
| `null`      | Campo opcional (NULL)        | `descricao=text,null`     |
| `unique`    | Valor único                  | `email=string,100,unique` |
| `index`     | Criar índice                 | `codigo=string,50,index`  |

### 📋 Exemplos Práticos

#### Produto Simples

```
nome=string,100,req;preco=decimal,8,2,req;ativo=boolean
```

#### Produto com Status (Enum)

```
nome=string,100,req;preco=decimal,10,2,req;status=enum,disponivel|esgotado|em_breve,req;descricao=text
```

#### Usuário Completo

```
nome=string,100,req;email=string,100,req,unique;senha=string,255,req;cpf=string,14,req;telefone=string,15;nascimento=date,null;perfil_id=foreign,perfis,id
```

#### Post de Blog

```
titulo=string,200,req;conteudo=text,req;publicado=boolean;autor_id=foreign,users,id;categoria_id=foreign,categorias,id
```

#### Cadastro de Pessoa Física

```
nome_completo=string,150,req;cpf=string,14,req,unique;email=string,100,req;telefone=string,15;celular=string,15,req;nascimento=date,req;foto_perfil=string,255
```

#### Produto E-commerce Completo

```
nome=string,200,req;descricao=text,req;preco=decimal,10,2,req;preco_promocional=decimal,10,2;estoque=integer,req;status=enum,ativo|inativo|esgotado,req;imagem_principal=string,255;categoria_id=foreign,categories,id
```

#### Pedido com Múltiplos Status

```
numero_pedido=string,50,req,unique;cliente_id=foreign,clients,id,req;valor_total=decimal,10,2,req;status=enum,pendente|processando|enviado|entregue|cancelado,req;data_pedido=datetime,req
```

#### Documento com Upload

```
titulo=string,200,req;descricao=text;arquivo_pdf=string,255,req;data_upload=datetime,req;categoria=enum,contrato|nota_fiscal|recibo|outros,req;usuario_id=foreign,users,id
```

---

## 🎨 Detalhamento de Tipos de Campo

### 📊 Campo Enum - Lista de Valores Predefinidos

O campo `enum` permite criar campos com opções predefinidas, gerando automaticamente um **select dropdown** no frontend.

#### Sintaxe

```
campo=enum,opcao1|opcao2|opcao3[,modificadores]
```

#### Características

-   **Backend**: Cria validação `in:opcao1,opcao2,opcao3`
-   **Frontend**: Gera componente `AppSelect` com as opções
-   **Separador**: Use `|` (pipe) para separar os valores
-   **Case Sensitive**: Os valores são sensíveis a maiúsculas/minúsculas

#### Exemplos de Uso

**Status de Produto**

```
status=enum,ativo|inativo|esgotado,req
```

**Prioridade de Tarefa**

```
prioridade=enum,baixa|média|alta|crítica,req
```

**Tipo de Pessoa**

```
tipo_pessoa=enum,física|jurídica,req
```

**Forma de Pagamento**

```
forma_pagamento=enum,dinheiro|cartão_crédito|cartão_débito|pix|boleto,req
```

**Estado Civil**

```
estado_civil=enum,solteiro|casado|divorciado|viúvo
```

#### Exemplo Completo em JSON

```json
{
    "domain": "Orders",
    "model": "Order",
    "schema": "numero_pedido=string,50,req;cliente_id=foreign,clients,id;valor_total=decimal,10,2,req;status=enum,pendente|processando|enviado|entregue|cancelado,req;forma_pagamento=enum,dinheiro|cartão|pix|boleto,req;data_pedido=datetime,req",
    "foreignKeys": [
        {
            "localKey": "cliente_id",
            "foreignTable": "clients",
            "foreignKey": "id",
            "displayField": "nome"
        }
    ],
    "force": true
}
```

#### Validação Gerada

No backend, o sistema gera automaticamente:

```php
'status' => ['required', 'in:pendente,processando,enviado,entregue,cancelado']
```

#### Frontend Gerado

```vue
<AppSelect
    v-model="data.status"
    label="Status"
    placeholder="Selecione o status"
    :items="[
        { title: 'pendente', value: 'pendente' },
        { title: 'processando', value: 'processando' },
        { title: 'enviado', value: 'enviado' },
        { title: 'entregue', value: 'entregue' },
        { title: 'cancelado', value: 'cancelado' },
    ]"
    :rules="[rules.requiredValidator]"
/>
```

### 💰 Campos Monetários (Currency)

Campos detectados como valores monetários recebem formatação especial:

**Nomes detectados**: `preco`, `valor`, `price`, `cost`, `custo`
**Tipos**: `decimal`, `float`, `double`

```
preco=decimal,10,2,req
valor_total=decimal,10,2
preco_promocional=decimal,10,2
```

**Frontend**: Input com máscara de moeda (R$ 0.000,00)

### 📱 Campos com Máscara

#### CPF

```
cpf=string,14,req
cpf_responsavel=string,14
```

**Máscara**: 000.000.000-00
**Validação**: Validador de CPF

#### CNPJ

```
cnpj=string,18,req
cnpj_empresa=string,18
```

**Máscara**: 00.000.000/0000-00
**Validação**: Validador de CNPJ

#### Telefone/Celular

```
telefone=string,15
celular=string,15,req
telefone_comercial=string,15
```

**Máscara**: (00) 0000-0000 ou (00) 00000-0000
**Validação**: Formato de telefone brasileiro

### 📁 Campos de Upload

#### Imagem

```
foto_perfil=string,255
imagem_principal=string,255
avatar=string,255
```

**Frontend**: Image uploader com preview
**Validação**: Tipos de arquivo de imagem (jpg, png, gif, etc.)

#### Arquivo Genérico

```
arquivo_pdf=string,255
documento=string,255
file_anexo=string,255
```

**Frontend**: File uploader
**Validação**: Qualquer tipo de arquivo

### 🔗 Campos de Relacionamento (Foreign Keys)

```
categoria_id=foreign,categories,id
usuario_id=foreign,users,id
```

**Frontend**: Autocomplete com busca
**Backend**: Validação de existência na tabela relacionada

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

-   **belongsTo**: No model filho
-   **hasMany**: No model pai

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
    "files_updated": ["routes/web.php"],
    "frontend_files": [
        "resources/frontend/src/domains/products/types/Product.ts"
    ]
}
```

### ⚠️ Cuidados com Rollback

-   Sempre fazer backup antes de executar rollback
-   Verificar se não há dados importantes nas tabelas
-   Rollback remove arquivos permanentemente

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

-   **Domínios**: PascalCase plural (`Products`, `Users`, `Orders`)
-   **Models**: PascalCase singular (`Product`, `User`, `Order`)
-   **Campos**: snake_case (`nome_completo`, `data_nascimento`)
-   **Tabelas**: snake_case plural (`products`, `users`, `orders`)

#### Exemplos

```json
{
    "domain": "UserManagement", // ✅ Correto
    "model": "User", // ✅ Correto
    "schema": "nome_completo=string,100,req" // ✅ Correto
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

-   Sempre definir relacionamentos completos
-   Usar `ON DELETE CASCADE` quando apropriado
-   Validar integridade referencial

#### Validações

-   Usar `req` para campos obrigatórios
-   Implementar `unique` quando necessário
-   Definir tamanhos apropriados para strings

### ⚡ Performance

#### Índices

-   Adicionar `index` em campos de busca frequente
-   Foreign keys automaticamente recebem índices
-   Considerar índices compostos para consultas complexas

#### Paginação

-   Frontend gerado inclui paginação automática
-   Configurar limites apropriados no backend

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

-   Labels automáticos baseados em nomes de campos
-   Mensagens de validação traduzidas
-   Documentação em português

---

## 📊 Monitoramento e Logs

### 📝 Logs do Sistema

Os logs ficam em:

-   **Aplicação**: `storage/logs/laravel.log`
-   **Rollback**: `storage/framework/rollback/rollback_log.json`
-   **Geração**: Output do comando

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

-   **Issues**: Reportar bugs e solicitar features
-   **Documentação**: Este manual e README.md
-   **Código**: Análise do código fonte

### 🤝 Contribuição

Para contribuir:

1. Fork do repositório
2. Criar branch para feature
3. Implementar melhorias
4. Testes abrangentes
5. Pull request

### 📋 Roadmap

Funcionalidades planejadas:

-   [ ] Suporte a relacionamentos many-to-many
-   [ ] Geração de APIs REST
-   [ ] Integração com GraphQL
-   [ ] Testes automatizados mais abrangentes
-   [ ] Interface web para configuração

---

## � Referência Rápida de Campos

### Sintaxe Geral

```
campo=tipo[,opção1][,opção2][,modificadores]
```

### Tabela de Referência Rápida

| Tipo Campo        | Sintaxe Exemplo                      | Frontend        | Validação Backend             |
| ----------------- | ------------------------------------ | --------------- | ----------------------------- |
| Texto curto       | `nome=string,100,req`                | Input text      | required, string, max:100     |
| Texto longo       | `descricao=text`                     | Textarea        | string                        |
| Número inteiro    | `quantidade=integer,req`             | Input number    | required, integer             |
| Número decimal    | `preco=decimal,10,2,req`             | Input currency  | required, numeric             |
| Booleano          | `ativo=boolean`                      | Switch          | boolean                       |
| Data              | `nascimento=date,req`                | Date picker     | required, date                |
| Data e hora       | `agendamento=datetime,req`           | DateTime picker | required, date_format         |
| Lista valores     | `status=enum,ativo\|inativo,req`     | Select          | required, in:ativo,inativo    |
| CPF               | `cpf=string,14,req`                  | Input + máscara | required, string, cpf         |
| CNPJ              | `cnpj=string,18,req`                 | Input + máscara | required, string, cnpj        |
| Telefone          | `telefone=string,15`                 | Input + máscara | string, telefone              |
| Email             | `email=string,100,req,unique`        | Input email     | required, email, unique       |
| Moeda             | `valor_total=decimal,10,2,req`       | Input currency  | required, numeric             |
| Imagem            | `foto_perfil=string,255`             | Image upload    | string, max:255               |
| Arquivo           | `documento=string,255`               | File upload     | string, max:255               |
| Chave estrangeira | `categoria_id=foreign,categories,id` | Autocomplete    | integer, exists:categories,id |
| JSON              | `metadata=json`                      | Textarea        | json                          |

### Modificadores Disponíveis

| Modificador | Uso       | Descrição                      |
| ----------- | --------- | ------------------------------ |
| `req`       | `,req`    | Campo obrigatório              |
| `null`      | `,null`   | Permite valor NULL             |
| `unique`    | `,unique` | Valor deve ser único na tabela |
| `index`     | `,index`  | Cria índice no banco de dados  |

### Exemplos de Schemas Completos

#### CRUD Simples

```bash
php artisan generate:crud --config='{"domain":"Products","model":"Product","schema":"nome=string,100,req;preco=decimal,10,2,req;ativo=boolean","force":true}'
```

#### CRUD com Enum

```bash
php artisan generate:crud --config='{"domain":"Orders","model":"Order","schema":"numero=string,50,req;status=enum,pendente|pago|enviado|entregue,req;valor=decimal,10,2,req","force":true}'
```

#### CRUD com Relacionamento

```bash
php artisan generate:crud --config='{"domain":"Products","model":"Product","schema":"nome=string,100,req;preco=decimal,10,2,req;categoria_id=foreign,categories,id","foreignKeys":[{"localKey":"categoria_id","foreignTable":"categories","foreignKey":"id","displayField":"nome"}],"force":true}'
```

#### CRUD Completo com Múltiplos Tipos

```bash
php artisan generate:crud --config='{"domain":"Clients","model":"Client","schema":"nome=string,150,req;email=string,100,req,unique;cpf=string,14,req,unique;telefone=string,15;celular=string,15,req;nascimento=date;foto=string,255;ativo=boolean;tipo=enum,pessoa_física|pessoa_jurídica,req","force":true}'
```

---

## �📄 Licença

MIT License - Veja o arquivo LICENSE para detalhes.

---

## 🎉 Conclusão

O Laravel CRUD Generator é uma ferramenta poderosa para acelerar o desenvolvimento de aplicações Laravel com arquitetura de domínios. Com suporte completo a backend, frontend e rollback, permite criar CRUDs robustos em minutos.

Para dúvidas específicas, consulte os exemplos neste manual ou analise o código fonte dos geradores.

**Bom desenvolvimento! 🚀**
