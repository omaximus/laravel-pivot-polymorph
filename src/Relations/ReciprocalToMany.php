<?php

namespace Pisochek\PivotPolymorph\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Pisochek\PivotPolymorph\Concerns\HasRelationships;

class ReciprocalToMany extends MorphToMany
{
    use HasRelationships;
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
     * The class name of the related name constraint.
     *
     * @var string
     */
    protected $relatedName;

    /**
     * The class name of the type constraint.
     *
     * @var string
     */
    protected $type;

    /**
     * The class name of the name constraint.
     *
     * @var string
     */
    protected $name;


    /**
     * Create a new reciprocal to many relationship instance.
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
        Builder $query,
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
        $this->relatedMorphType = $relatedType;

        $this->relatedMorphClass = $query->getModel()->getMorphClass();

        parent::__construct(
            $query,
            $parent,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            null,
            false
        );

        $this->relatedName = $relatedName;

        $this->type = $type;

        $this->name = $name;

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
    protected function shouldSelect($query = null)
    {
        return $this->pivotColumns;
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
    public function newPivotQuery()
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

    /**
     * Get the related model.
     *
     * @return string
     */
    public function getReciprocalClass()
    {
        return $this->parent->MorphsToMany($this->relatedMorphClass, $this->relatedName, $this->name, $this->table);
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
        ->get()
        ->groupBy($this->relatedMorphType);

        $reciprocal = $this->getReciprocalClass();
        $reciprocalBuilder = $reciprocal->query->applyScopes();
        $reciprocalResults = $reciprocalBuilder->addSelect($this->shouldSelect($columns))
        ->getQuery()
        ->get()
        ->groupBy($this->type);

        $groupedResultModels = $this->getModels($groupedResults);
        $relatedResultModels = $this->getModels($reciprocalResults, true);
        $models = array_merge($groupedResultModels, $relatedResultModels);

        $this->hydratePivotRelation($models);

        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }
        return new Collection($models);
    }

    /**
     * Get models.
     *
     * @return string
     */
    public function getModels($groupedResults , $related = null)
    {
        $models = [];

        foreach ($groupedResults as $key => $results) {
            /** @var Model $model */
            $model = static::getMorphedModel($key);
            /** @var \Illuminate\Database\Query\Builder $modelQuery */

            $modelQuery = $model::whereIn($this->parentKey, $results->pluck($related ? $this->foreignPivotKey : $this->relatedPivotKey)); 
                /** @var Collection $modelResults */
                $modelResults = $modelQuery->get();

            // Fill pivot table
            foreach ($results as $result) {
                /** @var Model $foundModel */
                $foundModel = $modelResults->where($this->parentKey, $result->{$related ? $this->foreignPivotKey : $this->relatedPivotKey})->first();

                if ($foundModel) {
                    $foundModel->setAttribute('pivot_' . $related ? $this->relatedPivotKey : $this->foreignPivotKey, $result->{$related ? $this->relatedPivotKey : $this->foreignPivotKey});
                    $foundModel->setAttribute('pivot_' . $this->morphType, $this->morphClass);
                    $foundModel->setAttribute('pivot_' . $related ? $this->foreignPivotKey : $this->relatedPivotKey, $foundModel->getKey());
                    $foundModel->setAttribute('pivot_' . $related ? $this->type : $this->relatedMorphType, $foundModel->getMorphClass());

                    // Clone to avoid same relation objects
                    array_push($models, clone $foundModel);
                }
            }
        }

        return $models;
    }
}
