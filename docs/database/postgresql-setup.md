# PostgreSQL — criação do banco (MM Sports API)

O projeto usa **PostgreSQL** em ambiente de desenvolvimento e produção.

O comando `php artisan migrate` **não cria o banco** — só aplica migrations. O banco alvo (`DB_DATABASE`) precisa existir, com o padrão de encoding e locale abaixo.

## Modo automático (recomendado)

1. No `.env`: `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_DATABASE` (ex.: `mm_sports`), `DB_USERNAME` / `DB_PASSWORD` (usuário **da aplicação** após o banco existir; para a primeira criação use credenciais com `CREATEDB` ou preencha `DB_ROOT_USER` e `DB_ROOT_PASSWORD` com um superusuário, ex. `postgres`).
2. Criar o banco (se ainda não existir) e rodar migrations em sequência:

```bash
composer run db:setup
```

Equivale a:

```bash
php artisan db:create
php artisan migrate
```

O `db:create` conecta em `DB_BOOTSTRAP_DATABASE` (padrão `postgres`), verifica se `DB_DATABASE` já existe e, se não existir, executa o `CREATE DATABASE` **no mesmo formato** da seção manual.

## Modo manual (SQL)

1. Ajuste `meu_banco` para o mesmo nome de `DB_DATABASE` no `.env`.
2. Conecte como superuser (ex.: `postgres`) e execute **tal qual**:

```sql
CREATE DATABASE meu_banco
    ENCODING = 'UTF8'
    LOCALE_PROVIDER = icu
    ICU_LOCALE = 'pt-BR-u-ks-level1'
    LC_COLLATE = 'pt_BR.UTF-8'
    LC_CTYPE = 'pt_BR.UTF-8'
    TEMPLATE = template0;
```

3. Depois: `php artisan migrate` (ou `composer run db:setup` se o banco já existir — o `db:create` só avisa e segue).

> **Requisitos:** `LOCALE_PROVIDER = icu` e `ICU_LOCALE` exigem PostgreSQL com suporte ICU (típico em 15+). O locale `pt_BR.UTF-8` precisa existir no sistema. Se o `CREATE` falhar, liste os locales no host e ajuste `LC_*` conforme a documentação da versão do servidor.

## Variáveis de ambiente (Laravel)

Exemplo mínimo no `.env` (veja também `.env.example`):

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=meu_banco
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha

# Opcional: superusuário usado só por php artisan db:create
DB_ROOT_USER=postgres
DB_ROOT_PASSWORD=...
```

Em seguida: `composer run db:setup` ou `php artisan migrate` se o banco já existir.

## Testes (Pest / PHPUnit)

O `phpunit.xml` pode manter `DB_CONNECTION=sqlite` e banco em memória **apenas** para a suíte de testes, sem alterar o ambiente local com PostgreSQL. Ajuste conforme a política do repositório.
