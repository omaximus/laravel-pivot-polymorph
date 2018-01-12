<?php

namespace App\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection as BaseCollection;

class MorphsToMany extends MorphToMany
{
    protected $morphId;

    protected $oMorphId;

    /**
     * The type of the related polymorphic relation.
     *
     * @var string
     */
    protected $oMorphType;

    /**
     * Create a new morph to many relationship instance.
     *
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $name
     * @param string $oName
     * @param string $table
     * @param string $id
     * @param string $oId
     * @param string $type
     * @param null|string $oType
     * @param string $foreign
     * @param string $oForeign
     * @internal param string $relatedPivotKey
     * @internal param string $foreignPivotKey
     */
    public function __construct(
        Model $parent, $name, $oName, $table, $id, $oId,
        $type, $oType, $foreign, $oForeign
    ) {
        parent::__construct(new Builder(\DB::query()), $parent, $name, $table, $id, $oId, $foreign, $oForeign);

        $this->morphId = $id;
        $this->oMorphId = $oId;
        $this->oMorphType = $oType;
        $this->pivotColumns = [$id, $oId, $type, $oType];
    }

    /**
     * Set the join clause for the relation query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|null  $query
     * @return $this
     */
    protected function performJoin($query = null)
    {
        return $this;
    }

    protected function shouldSelect(array $columns = ['*'])
    {
        return $this->pivotColumns;
    }

    public function get($columns = ['*'])
    {
        // First we'll add the proper select columns onto the query so it is run with
        // the proper columns. Then, we will get the results and hydrate out pivot
        // models with the result of those columns as a separate model relation.
        $columns = $this->query->getQuery()->columns ? [] : $columns;

        $builder = $this->query->applyScopes();

        $results = $builder->addSelect($this->shouldSelect($columns))
                           ->getQuery()
                           ->from($this->table)
                           ->get()
                           ->groupBy($this->oMorphType);

        $models = [];

        foreach ($results as $key => $result) {
            $model = static::getMorphedModel($key);
            $modelResults = $model::whereIn($this->parentKey, $result->pluck($this->relatedPivotKey))->get()->all();
            $models = array_merge($models, $modelResults);
        }

        $this->hydratePivotRelation($models);

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded. This will solve the
        // n + 1 query problem for the developer and also increase performance.
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return new BaseCollection($models);
    }

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
            return $value->map(function($model) {
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
                if ($item->{$this->oMorphId} === $id && $item->{$this->oMorphType} === $class) {
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

        $detach = $current->except($updatedKeys)->map(function($item) {
            return [$item->{$this->oMorphId}, $item->{$this->oMorphType}];
        })->all();

        if ($detaching && count($detach) > 0) {
            $this->detach($detach);

            $changes['detached'] = $detach;
        }

        return $changes;
    }

    public function updateExistingMorphPivot($id, $class, array $attributes)
    {
        if (in_array($this->updatedAt(), $this->pivotColumns)) {
            $attributes = $this->addTimestampsToAttachment($attributes, true);
        }

        $updated = $this->newPivotQuery()
                        ->where($this->oMorphId, $id)
                        ->where($this->oMorphType, $class)
                        ->update($this->castAttributes($attributes));

        return $updated;
    }

    public function attach($id, array $attributes = [], $touch = true)
    {
        $this->newPivotStatement()->insert($this->formatAttachMorphRecords($this->parseIds($id), $attributes));
    }

    protected function formatAttachMorphRecords($ids, array $attributes)
    {
        $records = [];

        $hasTimestamps = ($this->hasPivotColumn($this->createdAt()) || $this->hasPivotColumn($this->updatedAt()));

        foreach ($ids as $value) {
            $records[] = $this->formatAttachMorphRecord($value, $attributes, $hasTimestamps);
        }

        return $records;
    }

    protected function formatAttachMorphRecord($value, $attributes, $hasTimestamps)
    {
        list($id, $class) = $value;
        $attributes = array_merge($attributes,$values[3] ?? $values['attributes'] ?? []);

        $record[$this->morphType] = $this->morphClass;
        $record[$this->oMorphType] = $class;

        return array_merge($this->baseAttachRecord($id, $hasTimestamps), $record, $this->castAttributes($attributes));
    }

    public function detach($ids = null, $touch = true)
    {
        $query = $this->newPivotQuery();
        $results = [];

        if (! is_null($ids)) {
            $ids = $this->parseIds($ids);

            if (empty($ids)) {
                return 0;
            }

            // TODO: Can be optimized by $class grouping
            foreach ($ids as $item) {
                list($id, $class) = $item;
                $results[] = $query->where($this->oMorphType, $class)->where($this->relatedPivotKey, $id)->delete();
            }
        }

        return $results;
    }
}
