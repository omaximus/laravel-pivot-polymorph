<?php

namespace App\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;

class MorphsToMany extends MorphToMany
{
    /**
     * The type of the related polymorphic relation.
     *
     * @var string
     */
    protected $relatedMorphType;

    /**
     * The class name of the related morph type constraint.
     *
     * @var string
     */
    protected $relatedMorphClass;

    /**
     * Create a new morph to many relationship instance.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param string $name
     * @param string $relatedName
     * @param string $table
     * @param string $type
     * @param string $relatedType
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param bool $parentKey
     * @param $relatedKey
     * @internal param string $relatedPivotKey
     * @internal param string $foreignPivotKey
     */
    public function __construct(
        Builder $query, Model $parent, $name, $relatedName, $table, $foreignPivotKey, $relatedPivotKey, $type,
        $relatedType, $parentKey, $relatedKey
    ) {
        $this->relatedMorphType = $relatedType;
        $this->relatedMorphClass = $query->getModel()->getMorphClass();

        parent::__construct(
            $query, $parent, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, null, false
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function addWhereConstraints()
    {
        parent::addWhereConstraints();

        $this->query->where($this->table . '.' . $this->relatedMorphType, $this->relatedMorphClass);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addEagerConstraints(array $models)
    {
        parent::addEagerConstraints($models);

        $this->query->where($this->table . '.' . $this->relatedMorphType, $this->relatedMorphClass);
    }

    /**
     * {@inheritdoc}
     */
    protected function baseAttachRecord($id, $timed)
    {
        return Arr::add(
            parent::baseAttachRecord($id, $timed), $this->relatedMorphType, $this->relatedMorphClass
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return parent::getRelationExistenceQuery($query, $parentQuery, $columns)
                     ->where($this->table . '.' . $this->relatedMorphType, $this->relatedMorphClass);
    }

    /**
     * {@inheritdoc}
     */
    protected function newPivotQuery()
    {
        return parent::newPivotQuery()->where($this->table . '.' . $this->relatedMorphType, $this->relatedMorphClass);
    }

    /**
     * {@inheritdoc}
     */
    public function newPivot(array $attributes = [], $exists = false)
    {
        $using = $this->using;

        $pivot = $using ? $using::fromRawAttributes($this->parent, $attributes, $this->table, $exists)
            : MorphsToManyPivot::fromAttributes($this->parent, $attributes, $this->table, $exists);

        $pivot->setPivotKeys($this->foreignPivotKey, $this->relatedPivotKey)
              ->setOtherMorphType($this->relatedMorphType)
              ->setOtherMorphClass($this->relatedMorphClass)
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
        return $this->relatedMorphType;
    }

    /**
     * Get the class name of the related model.
     *
     * @return string
     */
    public function getOtherMorphClass()
    {
        return $this->relatedMorphClass;
    }
}
