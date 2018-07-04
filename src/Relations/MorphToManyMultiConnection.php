<?php

namespace Pisochek\PivotPolymorph\Relations;

use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class MorphToManyMultiConnection extends BelongsToManyMultiConnection
{
    /**
     * The type of the polymorphic relation.
     *
     * @var string
     */
    protected $morphType;

    /**
     * The class name of the morph type constraint.
     *
     * @var string
     */
    protected $morphClass;

    /**
     * Indicates if we are connecting the inverse of the relation.
     *
     * This primarily affects the morphClass constraint.
     *
     * @var bool
     */
    protected $inverse;

    /**
     * @inheritdoc
     */
    public function __construct(
        Builder $query,
        Model $parent,
        $name,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
        $inverse = false
    ) {
        $this->inverse = $inverse;
        $this->morphType = $name . '_type';
        $this->morphClass = $inverse ? $query->getModel()->getMorphClass() : $parent->getMorphClass();

        parent::__construct(
            $query, $parent, $table, $foreignPivotKey,
            $relatedPivotKey, $parentKey, $relatedKey, $relationName
        );
    }

    /**
     * @inheritdoc
     */
    protected function performJoin($query = null)
    {
        $query = $query ?: $this->query;

        $baseTable = $this->related->getTable();

        $key = $baseTable . '.' . $this->relatedKey;

        $ids = $this->parent->getConnection()
                            ->table($this->table)
                            ->where($this->parent->getForeignKey(), $this->parent->getKey())
                            ->where($this->morphType, $this->morphClass)
                            ->distinct()
                            ->pluck($this->relatedPivotKey);

        $query->whereIn($key, $ids);

        return $query;
    }

    /**
     * @inheritdoc
     */
    public function addEagerConstraints(array $models)
    {
        parent::addEagerConstraints($models);

        $this->query->where($this->table . '.' . $this->morphType, $this->morphClass);
    }

    /**
     * @inheritdoc
     */
    protected function baseAttachRecord($id, $timed)
    {
        return Arr::add(
            parent::baseAttachRecord($id, $timed), $this->morphType, $this->morphClass
        );
    }

    /**
     * @inheritdoc
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return parent::getRelationExistenceQuery($query, $parentQuery, $columns)->where(
            $this->table . '.' . $this->morphType, $this->morphClass
        );
    }

    /**
     * @inheritdoc
     */
    protected function newPivotQuery()
    {
        return parent::newPivotQuery()->where($this->morphType, $this->morphClass);
    }

    /**
     * @inheritdoc
     */
    public function newPivot(array $attributes = [], $exists = false)
    {
        $using = $this->using;

        $pivot = $using ? $using::fromRawAttributes($this->parent, $attributes, $this->table, $exists)
            : MorphPivot::fromAttributes($this->parent, $attributes, $this->table, $exists);

        $pivot->setPivotKeys($this->foreignPivotKey, $this->relatedPivotKey)
              ->setMorphType($this->morphType)
              ->setMorphClass($this->morphClass);

        return $pivot;
    }

    /**
     * Get the foreign key "type" name.
     *
     * @return string
     */
    public function getMorphType()
    {
        return $this->morphType;
    }

    /**
     * Get the class name of the parent model.
     *
     * @return string
     */
    public function getMorphClass()
    {
        return $this->morphClass;
    }
}
