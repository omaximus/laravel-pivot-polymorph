<?php

namespace App\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;

class MorphToManyThrough extends MorphToMany
{
    /**
     * The type of the related polymorphic relation.
     *
     * @var string
     */
    protected $oMorphType;

    /**
     * The class name of the related morph type constraint.
     *
     * @var string
     */
    protected $oMorphClass;

    /**
     * Create a new morph to many relationship instance.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $name
     * @param string $oName
     * @param string $table
     * @param string $type
     * @param string $oType
     * @param string $id
     * @param string $oId
     * @param bool $foreign
     * @param $oForeign
     * @internal param string $relatedPivotKey
     * @internal param string $foreignPivotKey
     */
    public function __construct(
        Builder $query, Model $parent, $name, $oName, $table, $id, $oId, $type, $oType, $foreign, $oForeign
    ) {
        $this->oMorphType = $oType;
        $this->oMorphClass = $query->getModel()->getMorphClass();
        parent::__construct($query, $parent, $name, $table, $id, $oId, $foreign, $oForeign, null, false);
    }

    /**
     * Set the where clause for the relation query.
     *
     * @return $this
     */
    protected function addWhereConstraints()
    {
        parent::addWhereConstraints();

        $this->query->where($this->table . '.' . $this->oMorphType, $this->oMorphClass);

        return $this;
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        parent::addEagerConstraints($models);

        $this->query->where($this->table . '.' . $this->oMorphType, $this->oMorphClass);
    }

    /**
     * Create a new pivot attachment record.
     *
     * @param  int   $id
     * @param  bool  $timed
     * @return array
     */
    protected function baseAttachRecord($id, $timed)
    {
        return Arr::add(
            parent::baseAttachRecord($id, $timed), $this->oMorphType, $this->oMorphClass
        );
    }

    /**
     * Add the constraints for a relationship count query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return parent::getRelationExistenceQuery($query, $parentQuery, $columns)
                     ->where($this->table . '.' . $this->oMorphType, $this->oMorphClass);
    }

    /**
     * Create a new query builder for the pivot table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newPivotQuery()
    {
        return parent::newPivotQuery()->where($this->table . '.' . $this->oMorphType, $this->oMorphClass);
    }

    /**
     * Create a new pivot model instance.
     *
     * @param  array  $attributes
     * @param  bool   $exists
     * @return \Illuminate\Database\Eloquent\Relations\Pivot
     */
    public function newPivot(array $attributes = [], $exists = false)
    {
        $using = $this->using;

        $pivot = $using ? $using::fromRawAttributes($this->parent, $attributes, $this->table, $exists)
            : MorphThroughPivot::fromAttributes($this->parent, $attributes, $this->table, $exists);

        $pivot->setPivotKeys($this->foreignPivotKey, $this->relatedPivotKey)
              ->setOtherMorphType($this->oMorphType)
              ->setOtherMorphClass($this->oMorphClass)
              ->setMorphType($this->morphType)
              ->setMorphClass($this->morphClass);

        return $pivot;
    }

    /**
     * Get related the foreign key "type" name.
     *
     * @return string
     */
    public function getOtherMorphType()
    {
        return $this->oMorphType;
    }

    /**
     * Get the class name of the related model.
     *
     * @return string
     */
    public function getOtherMorphClass()
    {
        return $this->oMorphClass;
    }
}
