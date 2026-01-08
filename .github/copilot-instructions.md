# Laravel CRUD Generator - AI Agent Instructions

## What This System Does

**Automatic CRUD generator** for Laravel 12 with Domain-Driven Design. Generates complete backend (models, controllers, services, migrations, seeders) and optional frontend (Vue.js components, Pinia stores, TypeScript types).

## Essential Commands

```bash
# Interactive CRUD generation
php artisan generate:crud

# From JSON config (non-interactive)
php artisan generate:crud --config=examples/blog-post.json --force

# Generate complete domain with multiple CRUDs
php artisan generate:crud --domain --config=examples/blog-complete-system.json --force

# Rollback last operation
php artisan generate:crud --rollback

# Dev environment (server + queue + vite)
composer dev

# Tests
composer test
```

## Architecture

### Generator Pipeline
```
CrudGenerator (orchestrator)
  ↓
SchemaValidator + RelationshipValidator
  ↓
Generators (Model, Controller, Service, Migration, Seeder)
  ├─ TemplateManager (processes .stub files)
  ├─ ModelRelationsManager (creates bidirectional relationships)
  └─ RollbackLogger (tracks all operations)
  ↓
Managers (Route, Ability, Summary)
```

### Domain Structure (DDD)
```
app/Domains/{DomainName}/
├── Controllers/{Model}Controller.php    # ResourceController pattern
├── Models/{Model}.php                   # Eloquent with relationships
├── Services/{Model}Service.php          # Business logic
├── Migrations/xxxx_create_{table}_table.php
├── Seeders/{Model}Seeder.php           # Faker integration
└── README.md
```

### Critical Files
- `CrudGenerator.php`: Main orchestrator (1513 lines - REFACTOR NEEDED)
- `app/Domains/Shared/Stubs/*.stub`: Templates with placeholders
- `storage/rollback_log.json`: Operation tracking for undo
- `config/permission_list.php`: Auto-updated with new permissions

## Schema Syntax

```
campo=tipo,tamanho,modificadores;campo2=tipo2,tamanho2
```

### Examples
```php
// Product schema
"name=string,100,req;description=text;price=decimal,8,2,req;active=boolean,req"

// With foreign key
"title=string,200,req;content=text,5000;category_id=integer,req"

// Multiple modifiers
"email=string,255,req,unique,index;status=enum,pending|approved|rejected"
```

### Supported Types
- **String**: `string,{length}` → VARCHAR
- **Text**: `text,{length}` → TEXT
- **Numbers**: `integer`, `bigInteger`, `decimal,{precision},{scale}`, `float`
- **Date/Time**: `date`, `dateTime`, `timestamp`
- **Special**: `boolean`, `json`, `uuid`, `enum,value1|value2`

### Modifiers
- `req` → NOT NULL
- `null` → NULL
- `unique` → UNIQUE constraint
- `index` → INDEX
- `foreign,{table},{key}` → Foreign Key

## Relationships

### Auto-Generated Bidirectional
```json
{
  "foreignKeys": [
    {
      "localKey": "category_id",
      "foreignTable": "categories",
      "foreignKey": "id",
      "displayField": "name"
    }
  ]
}
```

**Result in Model:**
```php
// Product.php
public function category() {
    return $this->belongsTo(Category::class);
}

// Category.php (auto-added)
public function products() {
    return $this->hasMany(Product::class);
}
```

### Supported Relationship Types
- `belongsTo`: FK in current model → `category_id` field
- `hasMany`: FK in related model → auto-created inverse
- `hasOne`: FK in related model
- `belongsToMany`: Pivot table (auto-named: `{model1}_{model2}`)

## Code Generation Patterns

### Controller Pattern
```php
class ProductController extends Controller
{
    public function __construct(private ProductService $service) {}

    public function index(Request $request) {
        $data = $this->service->paginate($request->all());
        return response()->json(['data' => $data]);
    }
}
```

### Service Pattern
```php
class ProductService
{
    public function create(array $data): Product {
        return Product::create($data);
    }

    public function paginate(array $filters): LengthAwarePaginator {
        return Product::query()
            ->when($filters['search'] ?? null, fn($q) => $q->where('name', 'like', "%{$filters['search']}%"))
            ->paginate($filters['per_page'] ?? 15);
    }
}
```

### Migration Pattern
```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->decimal('price', 8, 2);
    $table->foreignId('category_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
});
```

## Template System

Templates in `app/Domains/Shared/Stubs/` use placeholders:

```php
// model.stub
namespace {{namespace}};

class {{modelName}} extends Model
{
    protected $fillable = [{{fillable}}];
    protected $casts = [{{casts}}];
    
    {{relationships}}
}
```

**Processed by TemplateManager:**
```php
$content = str_replace([
    '{{namespace}}' => "App\\Domains\\{$domain}\\Models",
    '{{modelName}}' => $modelName,
    '{{fillable}}' => "'name', 'price', 'active'",
    '{{casts}}' => "'active' => 'boolean', 'price' => 'decimal:2'",
], $stubContent);
```

## Rollback System

Every operation is logged:
```json
{
  "sessions": [{
    "id": "session_12345",
    "action": "generate_crud",
    "domain": "Products",
    "created": ["app/Domains/Products/Models/Product.php"],
    "modified": [{"file": "routes/api.php", "backup": "storage/backups/api.php.bak"}],
    "directories": ["app/Domains/Products"]
  }]
}
```

**Undo process:**
- Deletes created files
- Restores modified files from backups
- Removes created directories (if empty)
- Reverts route/permission changes

## JSON Config Format

```json
{
  "domain": "Products",
  "model": "Product",
  "schema": "name=string,100,req;price=decimal,8,2,req;category_id=integer,req",
  "foreignKeys": [
    {
      "localKey": "category_id",
      "foreignTable": "categories",
      "foreignKey": "id",
      "displayField": "name"
    }
  ],
  "generateCompleteStructure": false,
  "force": true,
  "skipFrontend": false,
  "skipBackend": false
}
```

### Multiple CRUDs in One Domain
```json
{
  "domain": "BlogSystem",
  "model": "Post",
  "schema": "title=string,200,req;content=text,req",
  "generateCompleteStructure": true,
  "crud": [
    {
      "model": "Comment",
      "schema": "post_id=integer,req;text=text,req"
    },
    {
      "model": "Tag",
      "schema": "name=string,50,req,unique"
    }
  ]
}
```

## Permissions System

Auto-generated permissions follow pattern: `{action}:{Model}`

```php
// Auto-added to config/permission_list.php
'Products' => ['create:Product', 'read:Product', 'update:Product', 'delete:Product']
```

Used with middleware:
```php
Route::apiResource('products', ProductController::class)
    ->middleware('permission:read:Product');
```

## Debugging Generated Code

### Check logs
```bash
# Real-time logs
php artisan pail

# Or file
tail -f storage/logs/laravel.log
```

### Validate before committing
```bash
# PHP syntax
composer test

# IDE helpers (regenerate after generation)
php artisan ide-helper:models --write
```

### Common issues
- **Migration fails**: Check if table/column already exists
- **Relationship not working**: Verify FK field exists (`{model}_id`)
- **Permission denied**: Ensure permission added to `config/permission_list.php` and seeded
- **Frontend not loading data**: Check API response format (`{data: [...]}`)

## Extension Points

### Adding a new generator
1. Create class: `app/Console/Commands/Generator/Generators/BackEnd/NewGenerator.php`
2. Implement `generate(array $config): void` method
3. Use `TemplateManager` to process stub
4. Log operations with `RollbackLogger::logFileCreation()`
5. Register in `CrudGenerator::generateCrud()` → `$this->callGenerator()`

### Custom stub template
1. Create `.stub` file in `app/Domains/Shared/Stubs/`
2. Use `{{placeholders}}` for dynamic content
3. Process with `TemplateManager::getStub($name, $replacements)`

## Anti-Patterns

❌ **Don't**:
- Modify generated files manually (they'll be overwritten)
- Bypass RollbackLogger when creating files
- Hardcode paths (use `app_path()`, `base_path()`, `database_path()`)
- Ignore validation errors (fix schema syntax first)
- Run migrations manually for generated code (generator handles it)

✅ **Do**:
- Use JSON config for reproducible generations
- Test schema with `--dry-run` before committing
- Keep examples in `examples/` directory as reference
- Document domain-specific logic in domain's README.md
- Use rollback if generation fails mid-way

## Integration with Frontend

Generated frontend expects:
- **API Base URL**: `{baseUrl}/api/{resource}`
- **Auth**: JWT token in `Authorization: Bearer {token}` header
- **Response format**: `{ data: {...} }` or `{ data: [...] }`
- **Validation errors**: `{ errors: { field: ['message'] } }` (422 status)
- **Permissions**: Array of strings like `['create:Product', 'read:Product']`

See [base-frontend docs](../../frontend/base-frontend/docs/API-CLIENT.md) for frontend integration details.
