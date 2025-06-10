# 📋 Exemplos de Configuração - Laravel CRUD Generator

Esta pasta contém exemplos práticos de configuração para diferentes cenários de uso do Laravel CRUD Generator.

## 🎯 Como Usar

Para usar qualquer exemplo, execute:

```bash
php artisan generate:crud --config=examples/[nome-do-arquivo].json --force
```

---

## 📝 Exemplos Disponíveis

> **🎉 NOVIDADE**: Agora é possível gerar múltiplos CRUDs relacionados dentro de um único domínio!

### 🔥 Sistema Completo com Múltiplos CRUDs

#### [`blog-complete-system.json`](blog-complete-system.json) - **NOVO!**
Sistema de blog completo com múltiplos CRUDs relacionados
```bash
php artisan generate:crud --config=@examples/blog-complete-system.json --domain --force
```

**Gera automaticamente:**
- 📦 **CRUD Principal**: Post (artigos do blog)
- 📦 **CRUDs Adicionais**: Comment, Tag, Category
- 🎨 **Frontend Completo**: Components Vue.js para todos os modelos
- 🔧 **Backend Completo**: Models, Controllers, Services, Migrations
- 🔗 **Relacionamentos**: Configurados automaticamente entre models

### 1. 🛒 E-commerce

#### [`ecommerce-category.json`](ecommerce-category.json)
Categoria de produtos para e-commerce
```bash
php artisan generate:crud --config=examples/ecommerce-category.json --force
```

#### [`ecommerce-product.json`](ecommerce-product.json)  
Produto com relacionamento para categoria
```bash
php artisan generate:crud --config=examples/ecommerce-product.json --force
```

#### [`ecommerce-order.json`](ecommerce-order.json)
Pedido com relacionamento para usuário
```bash
php artisan generate:crud --config=examples/ecommerce-order.json --force
```

### 2. 📝 Sistema de Blog

#### [`blog-category.json`](blog-category.json)
Categoria de posts
```bash
php artisan generate:crud --config=examples/blog-category.json --force
```

#### [`blog-post.json`](blog-post.json)
Post com relacionamentos para autor e categoria
```bash
php artisan generate:crud --config=examples/blog-post.json --force
```

### 3. 👥 Gestão de Usuários

#### [`user-profile.json`](user-profile.json)
Perfil de usuário
```bash
php artisan generate:crud --config=examples/user-profile.json --force
```

#### [`user-address.json`](user-address.json)
Endereço do usuário
```bash
php artisan generate:crud --config=examples/user-address.json --force
```

### 4. 📚 Sistema Educacional

#### [`education-course.json`](education-course.json)
Curso educacional
```bash
php artisan generate:crud --config=examples/education-course.json --force
```

#### [`education-student.json`](education-student.json)
Estudante com relacionamentos
```bash
php artisan generate:crud --config=examples/education-student.json --force
```

### 5. 🏥 Sistema de Saúde

#### [`health-patient.json`](health-patient.json)
Paciente
```bash
php artisan generate:crud --config=examples/health-patient.json --force
```

#### [`health-appointment.json`](health-appointment.json)
Consulta médica
```bash
php artisan generate:crud --config=examples/health-appointment.json --force
```

---

## 🔧 Sequência de Geração Recomendada

Para sistemas completos, gere na seguinte ordem:

### E-commerce
1. `ecommerce-category.json` (categorias primeiro)
2. `ecommerce-product.json` (produtos dependem de categorias)
3. `ecommerce-order.json` (pedidos dependem de usuários)

### Blog
1. `blog-category.json` (categorias primeiro)
2. `blog-post.json` (posts dependem de categorias e usuários)

### Educacional
1. `education-course.json` (cursos primeiro)
2. `education-student.json` (estudantes com relacionamentos)

---

## ⚡ Execução em Lote

Para gerar múltiplos CRUDs de uma vez:

```bash
# E-commerce completo
php artisan generate:crud --config=examples/ecommerce-category.json --force
php artisan generate:crud --config=examples/ecommerce-product.json --force
php artisan generate:crud --config=examples/ecommerce-order.json --force

# Blog completo
php artisan generate:crud --config=examples/blog-category.json --force
php artisan generate:crud --config=examples/blog-post.json --force
```

---

## 🔄 Rollback

Se precisar desfazer todas as alterações:

```bash
php artisan generate:crud --rollback
```

---

## 📝 Personalização

Para criar suas próprias configurações, copie um exemplo e modifique:

```bash
cp examples/ecommerce-product.json my-config.json
# Edite my-config.json conforme necessário
php artisan generate:crud --config=my-config.json --force
```

---

## 📚 Documentação

Para detalhes completos sobre configuração, consulte:
- [`MANUAL.md`](../MANUAL.md) - Manual completo
- [`README.md`](../README.md) - Guia rápido
