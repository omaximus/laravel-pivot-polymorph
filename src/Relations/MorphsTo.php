<?php

namespace Pisochek\PivotPolymorph\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Collection as BaseCollection;

class MorphsTo extends MorphToMany
{
    /**
     * The type of the related polymorphic relation.
     *
     * @var string
     */
    protected $relatedMorphType;

    /**
     * Create a new morph to many relationship instance.
     *
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $name
     * @param string $relatedName
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $type
     * @param null|string $relatedType
     * @param string $parentKey
     * @param string $relatedKey
     * @internal param string $relatedPivotKey
     * @internal param string $foreignPivotKey
     */
    public function __construct(
        Model $parent,
        $name,
        $relatedName,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $type,
        $relatedType,
        $parentKey,
        $relatedKey
    ) {
        $query = new Builder(new BaseBuilder($parent->getConnection()));

        parent::__construct(
            $query, $parent, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey
        );

        $this->related = new MorphPivot();
        $this->relatedMorphType = $relatedType;
        $this->pivotColumns = [$foreignPivotKey, $relatedPivotKey, $type, $relatedType];
    }

    /**
     * {@inheritdoc}
     */
    protected function performJoin($query = null)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function shouldSelect(array $columns = ['*'])
    {
        return $this->pivotColumns;
    }

    /**
     * {@inheritdoc}
     */
    public function get($columns = ['*'])
    {
        $columns = $this->query->getQuery()->columns ? [] : $columns;
        $builder = $this->query->applyScopes();
        $groupedResults = $builder->addSelect($this->shouldSelect($columns))
                                  ->getQuery()
                                  ->from($this->table)
                                  ->get()
                                  ->groupBy($this->relatedMorphType);

        $models = [];

        /**
         * @var int $key
         * @var BaseCollection $results
         */
        foreach ($groupedResults as $key => $results) {
            $model = static::getMorphedModel($key);
            /** @var Collection $modelResults */
            $modelResults = $model::whereIn($this->parentKey, $results->pluck($this->relatedPivotKey))->get();

            // Fill pivot table
            foreach ($results as $result) {
                /** @var Model $foundModel */
                $foundModel = $modelResults->where($this->parentKey, $result->{$this->relatedPivotKey})->first();

                if ($foundModel) {
                    $foundModel->setAttribute('pivot_' . $this->foreignPivotKey, $result->{$this->foreignPivotKey});
                    $foundModel->setAttribute('pivot_' . $this->morphType, $this->morphClass);
                    $foundModel->setAttribute('pivot_' . $this->relatedPivotKey, $foundModel->getKey());
                    $foundModel->setAttribute('pivot_' . $this->relatedMorphType, $foundModel->getMorphClass());

                    // Clone to avoid same relation objects
                    array_push($models, clone $foundModel);
                }
            }
        }

        $this->hydratePivotRelation($models);

        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return new Collection($models);
    }

    /**
     * {@inheritdoc}
     */
    protected function parseIds($value)
    {
        if ($value instanceof Model) {
            return [[$value->getKey(), $value->getMorphClass()]];
        }

        if ($value instanceof Collection) {
            return $value->map(function (Model $model) {
                return [$model->getKey(), $model->getMorphClass()];
            })->toArray();
        }

        if ($value instanceof BaseCollection) {
            return $value->map(function ($model) {
                if ($model instanceof Model) {
                    return [$model->getKey(), $model->getMorphClass()];
                } else {
                    return $model;
                }
            })->toArray();
        }

        $value = (array)$value;

        if (count($value) === count($value, COUNT_RECURSIVE)) {
            $value = [$value];
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function sync($ids, $detaching = true)
    {
        $changes = ['attached' => [], 'detached' => [], 'updated' => []];

        // Existing records
        $current = $this->newPivotQuery()->get();
        // Records to be synced
        $parsedIds = $this->parseIds($ids);
        $updatedKeys = [];

        foreach ($parsedIds as $values) {
            list($id, $class) = $values;
            $attributes = $values[3] ?? $values['attributes'] ?? [];

            // Suppose it's new
            $new = true;
            foreach ($current as $key => $item) {

                // If this record exists - update it, go out of the loop and mark it as not new
                if ($item->{$this->relatedPivotKey} === $id && $item->{$this->relatedMorphType} === $class) {
                    if (count($attributes) > 0 &&
                        $this->updateExistingMorphPivot($id, $class, $attributes)) {
                        $changes['updated'][] = [$id, $class];
                    }

                    $new = false;
                    $updatedKeys[] = $key;

                    break;
                }
            }
            if ($new) {
                $this->attach($values);
                $changes['attached'][] = [$id, $class];
            }
        }

        $detach = $current->except($updatedKeys)->map(function ($item) {
            return [$item->{$this->relatedPivotKey}, $item->{$this->relatedMorphType}];
        })->all();

        if ($detaching && count($detach) > 0) {
            $this->detach($detach);

            $changes['detached'] = $detach;
        }

        return $changes;
    }

    /**
     * Update an existing pivot record on the table.
     *
     * @param int $id Record identifier
     * @param string $class Record type
     * @param array $attributes Custom attributes
     *
     * @return int
     */
    public function updateExistingMorphPivot($id, $class, array $attributes)
    {
        if (in_array($this->updatedAt(), $this->pivotColumns)) {
            $attributes = $this->addTimestampsToAttachment($attributes, true);
        }

        $updated = $this->newPivotQuery()
                        ->where($this->relatedPivotKey, $id)
                        ->where($this->relatedMorphType, $class)
                        ->update($this->castAttributes($attributes));

        return $updated;
    }

    /**
     * {@inheritdoc}
     */
    public function attach($id, array $attributes = [], $touch = true)
    {
        $this->newPivotStatement()->insert($this->formatAttachMorphRecords($this->parseIds($id), $attributes));
    }

    /**
     * Create an array of records to insert into the pivot table.
     *
     * @param array $ids Array of ids and types
     * @param array $attributes Custom attributes
     *
     * @return array            Formatted array
     */
    protected function formatAttachMorphRecords($ids, array $attributes)
    {
        $records = [];

        $hasTimestamps = ($this->hasPivotColumn($this->createdAt()) || $this->hasPivotColumn($this->updatedAt()));

        foreach ($ids as $value) {
            $records[] = $this->formatAttachMorphRecord($value, $attributes, $hasTimestamps);
        }

        return $records;
    }

    /**
     * Create a full attachment record payload.
     *
     * @param array $value Array    containing id and type
     * @param array $attributes Custom attributes
     * @param bool $hasTimestamps Flag to determine timestamps existence
     *
     * @return array                Formatted array
     */
    protected function formatAttachMorphRecord($value, $attributes, $hasTimestamps)
    {
        list($id, $class) = $value;
        $attributes = array_merge($attributes, $values[3] ?? $values['attributes'] ?? []);

        $record[$this->morphType] = $this->morphClass;
        $record[$this->relatedMorphType] = $class;

        return array_merge($this->baseAttachRecord($id, $hasTimestamps), $record, $this->castAttributes($attributes));
    }

    /**
     * {@inheritdoc}
     */
    public function detach($ids = null, $touch = true)
    {
        $query = $this->newPivotQuery();
        $results = [];

        if (!is_null($ids)) {
            $ids = $this->parseIds($ids);

            if (empty($ids)) {
                return 0;
            }

            // TODO: Can be optimized by $class grouping
            foreach ($ids as $item) {
                list($id, $class) = $item;
                $results[] = $query->where($this->relatedMorphType, $class)
                                   ->where($this->relatedPivotKey, $id)
                                   ->delete();
            }
        } else {
            $results[] = $query->delete();
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, new Collection());
        }

        return $models;
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            if (isset($dictionary[$key = $model->{$this->parentKey}])) {
                $model->setRelation(
                    $relation, new Collection($dictionary[$key])
                );
            }
        }

        return $models;
    }

    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return parent::getRelationExistenceQuery($query->from($this->table), $parentQuery, $columns);
    }
}
