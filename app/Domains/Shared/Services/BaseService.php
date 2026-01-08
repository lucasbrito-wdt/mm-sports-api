<?php

namespace App\Domains\Shared\Services;

use App\Domains\Shared\Interfaces\IService;
use App\Domains\Shared\Traits\Dependencies;
use App\Domains\Shared\Utils\IntHelper;
use App\Domains\Shared\Helpers\SortHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Class BaseService
 *
 * This class implements the IService interface and uses the Dependencies trait.
 * It provides basic CRUD operations and a search function.
 */
class BaseService implements IService
{
    use Dependencies;

    /**
     * Retrieves a list of items.
     *
     * @param  array  $options  An array of options for the retrieval operation.
     * @return array The result of the index operation.
     */
    public function index(array $options = [], ?\Closure $builderCallback = null)
    {
        // Criar query base
        $query = $this->model->newQuery();

        // Se houver busca textual, aplicar scope search() do model (LaravelPostgresFts)
        if (!empty($options['search'])) {
            // Verificar se o model tem o método search (trait Searchable)
            $query = $query->search($options['search']);
        }

        // Aplicar filtros específicos (filters) e outras condições
        $query = $this->applyFilters($query, $options);

        // Permite modificar a query com operações adicionais
        if ($builderCallback !== null) {
            $builderCallback($query);
        }

        // Ordenação
        $sortBy = $options['sort_by'] ?? 'id';
        $sortOrder = $options['sort_order'] ?? 'desc';
        $query = SortHelper::applySort($query, $sortBy, $sortOrder);

        // Paginação
        $data = $query->paginate(IntHelper::tryParser($options['per_page'] ?? 15) ?? 15);

        return [
            'data' => $data->items(),
            'total' => $data->total(),
            'page' => $data->currentPage(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
        ];
    }

    /**
     * Retrieves a single item by its ID.
     *
     * @param  string  $id  The ID of the item to retrieve.
     * @return Model The result of the show operation.
     */
    public function show(string $id)
    {
        return $this->findById($id);
    }

    /**
     * Stores a new item.
     *
     * @param  array  $data  The data of the item to store.
     * @return Model The result of the store operation.
     */
    public function store(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Updates an existing item by its ID.
     *
     * @param  array  $data  The new data for the item.
     * @param  string  $id  The ID of the item to update.
     * @return Model The result of the update operation.
     */
    public function update(array $data, string $id)
    {
        $record = $this->findById($id);
        $record->update($data);

        return $record->refresh();
    }

    /**
     * Partially updates an existing item by its ID.
     *
     * @param  array  $data  The new data for the item.
     * @param  string  $id  The ID of the item to update.
     * @return Model The result of the patch operation.
     */
    public function patch(array $data, string $id)
    {
        $record = $this->findById($id);
        $record->update($data);

        return $record->refresh();
    }

    /**
     * Retrieves an item by its ID.
     *
     * @param  string  $id  The ID of the item to retrieve.
     * @return Model The result of the findById operation.
     */
    public function findById(string $id)
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Retrieves an item by a specific column value.
     *
     * @param  string  $column  The column to search in.
     * @param  mixed  $value  The value to search for.
     * @param  string  $operator  The operator to use for the search.
     * @return Model The result of the findBy operation.
     */
    public function findBy(string $column, $value, string $operator = '=')
    {
        return $this->model->where($column, $operator, $value)->firstOrFail();
    }

    /**
     * Deletes an item by its ID.
     *
     * @param  string  $id  The ID of the item to delete.
     * @return bool The result of the destroy operation.
     */
    public function destroy($id)
    {
        $record = $this->findById($id);

        return $record->delete();
    }

    /**
     * Busca registros usando o método search do model, com suporte a paginação.
     *
     * @param  array  $options  Parâmetros da busca. Esperado:
     *   - q (string|null): termo de busca
     *   - per_page (int|null): itens por página (padrão: 15)
     * @param  \Closure|null  $builderCallback  Callback opcional para customizar o builder.
     * @return array{
     *   data: array,
     *   total: int,
     *   page: int,
     *   last_page: int,
     * }
     * @deprecated
     */
    public function search(
        array $options = [],
        ?\Closure $builderCallback = null,
    ) {
        $q = $options['q'] ?? null;

        $query = $this->model->search($q);

        if ($builderCallback !== null) {
            $builderCallback($query);
        }

        $data = $query->paginate(IntHelper::tryParser($options['per_page'] ?? 15) ?? 15);

        return [
            'data' => $data->items(),
            'total' => $data->total(),
            'page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
        ];
    }

    /**
     * Aplica filtros específicos na query
     *
     * Nota: A busca textual (search) é aplicada diretamente na inicialização da query
     * via $this->model->search(), não neste método.
     *
     * @param  Builder  $query
     * @param  array  $options
     * @return Builder
     */
    protected function applyFilters(Builder $query, array $options): Builder
    {
        // Processar filtros específicos do array 'filters'
        if (!empty($options['filters']) && is_array($options['filters'])) {
            foreach ($options['filters'] as $field => $value) {
                // Ignorar valores vazios ou padrão
                if (empty($value) || $value === 'all' || $value === null) {
                    continue;
                }

                // Aplicar where se o campo existir na tabela
                if (Schema::hasColumn($this->model->getTable(), $field)) {
                    $query->where($field, $value);
                }
            }
        }

        return $query;
    }
}
