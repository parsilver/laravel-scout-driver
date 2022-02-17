<?php

namespace Farzai\ScoutDriver\Drivers;

use Elastic\EnterpriseSearch\Client;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Elastic\EnterpriseSearch\AppSearch\Request;
use Elastic\EnterpriseSearch\AppSearch\Schema;

class AppSearchDriver extends Engine
{
    /**
     * @var \Elastic\EnterpriseSearch\AppSearch\Endpoints
     */
    private $engine;

    /**
     * @var array
     */
    private $config;

    /**
     * @param Client $client
     * @param array $config
     */
    public function __construct(Client $client, array $config)
    {
        $this->engine = $client->appSearch();
        $this->config = $config;
    }

    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $engineName = $this->engineName($models->first());

        foreach ($models->chunk(100) as $chunk) {
            $documents = $chunk->map(function ($model) {
                return array_merge(
                    ['id' => $model->getScoutKey()],
                    $model->toSearchableArray(),
                    $model->scoutMetadata()
                );
            });

            $this->engine->indexDocuments(
                new Request\IndexDocuments($engineName, $documents->values()->all())
            );
        }
    }

    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return \Elastic\EnterpriseSearch\Response\Response|void
     */
    public function delete($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $documentIds = $models->map(fn ($model) => $model->getScoutKey());

        return $this->engine->deleteDocuments(
            new Request\DeleteDocuments($this->engineName($models->first()), $documentIds->all())
        );
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder);
    }

    /**
     * {@inheritDoc}
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, compact('perPage', 'page'));
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  array  $options
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $this->engine,
                $builder->query,
                $options
            );
        }

        $searchRequest = $this->makeFromBuilder($builder, $options);

        return $this->engine->search(
            new Request\Search($this->engineName($builder->model), $searchRequest)
        );
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results['results'])->map(function ($res) {
            return $res['id']['raw'];
        });
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if (count($results['results']) === 0) {
            return $model->newCollection();
        }

        $ids = $this->mapIds($results);
        $idPositions = array_flip($ids->all());

        return $model
            ->getScoutModelsByIds($builder, $ids->all())
            ->filter(fn ($model) => in_array($model->getScoutKey(), $ids->all()))
            ->sortBy(fn ($model) => $idPositions[$model->getScoutKey()])
            ->values();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['meta']['page']['total_results'];
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Elastic\EnterpriseSearch\Response\Response
     */
    public function flush($model)
    {
        throw new \InvalidArgumentException("Unsupported flush all documents for app-search driver.");
    }

    /**
     * @param Builder $builder
     * @param mixed $results
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return \Illuminate\Support\LazyCollection
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        return LazyCollection::make(function () use ($builder, $model, $results) {
            foreach ($this->map($builder, $results, $model) as $item) {
                yield $item;
            }
        });
    }

    /**
     * Create a search index.
     *
     * @param  string  $name
     * @param  array  $options
     * @return mixed
     */
    public function createIndex($name, array $options = [])
    {
        $engine = new Schema\Engine(
            $this->normalizeEngineName($name)
        );

        foreach ($this->config['engine'] ?? [] as $key => $value) {
            $engine->$key = $value;
        }

        return $this->engine->createEngine(
            new Request\CreateEngine($engine)
        );
    }

    /**
     * @param string $name
     * @return \Elastic\EnterpriseSearch\Response\Response|mixed
     */
    public function deleteIndex($name)
    {
        return $this->engine->deleteEngine(
            new Request\DeleteEngine($this->normalizeEngineName($name))
        );
    }

    /**
     * Dynamically call the Elastic App Search client instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->engine->$method(...$parameters);
    }

    /**
     * @param $model
     * @return string
     */
    protected function engineName($model)
    {
        return $this->normalizeEngineName($model->searchableAs());
    }

    /**
     * @param $name
     * @return string
     */
    protected function normalizeEngineName($name)
    {
        return str_replace('_', '-', $name);
    }

    /**
     * @param Builder $builder
     * @param array $options
     * @return Schema\SearchRequestParams|null
     */
    public function makeFromBuilder(Builder $builder, array $options = [])
    {
        $searchRequest = new Schema\SearchRequestParams($builder->query);

        if ($filters = $this->makeFilters($builder)) {
            $searchRequest->filters = $filters;
        }

        if ($sort = $this->makeSort($builder)) {
            $searchRequest->sort = $sort;
        }

        if ($page = $this->makePaginator($builder, $options)) {
            $searchRequest->page = $page;
        }

        return empty($searchRequest) ? null : $searchRequest;
    }

    /**
     * Get the filter array for the query.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return array
     */
    protected function makeFilters(Builder $builder)
    {
        return empty($builder->wheres) ? null : $builder->wheres;
    }

    /**
     * Make the order the results should be in
     */
    protected function makeSort(Builder $builder): ?array
    {
        $sort = collect($builder->orders)->pluck('direction', 'column');

        return $sort->isEmpty() ? null : $sort->all();
    }

    /**
     * Make the page result information
     */
    protected function makePaginator(Builder $builder, array $options)
    {
        $page = new Schema\PaginationResponseObject();
        $page->size = $options['perPage'] ?? $builder->limit;
        $page->current = $options['page'] ?? 1;

        return ! $page->size ? null : $page;
    }
}
