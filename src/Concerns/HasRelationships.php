<?php

namespace Pisochek\PivotPolymorph\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasRelationships as IlluminateHasRelationships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Pisochek\PivotPolymorph\Relations\BelongsToMany;
use Pisochek\PivotPolymorph\Relations\MorphsTo;
use Pisochek\PivotPolymorph\Relations\MorphsToMany;

trait HasRelationships
{
    use IlluminateHasRelationships;

    /**
     * Define fully polymorphic relation
     *
     * @param $name
     * @param $relatedName
     * @param $table
     * @param null $type
     * @param null $relatedType
     * @param null $foreignPivotKey
     * @param null $relatedPivotKey
     * @param null $parentKey
     * @param null $relatedKey
     *
     * @return \Pisochek\PivotPolymorph\Relations\MorphsTo
     */
    public function morphsTo(
        $name, $relatedName, $table, $foreignPivotKey = null, $relatedPivotKey = null,
        $parentKey = null, $type = null, $relatedType = null, $relatedKey = null
    ) {
        list($relatedType, $relatedPivotKey) = $this->getMorphs(
            Str::snake($relatedName), $relatedType, $relatedPivotKey
        );
        list($type, $foreignPivotKey) = $this->getMorphs(Str::snake($name), $type, $foreignPivotKey);

        return new MorphsTo(
            $this, $name, $relatedName, $table, $foreignPivotKey, $relatedPivotKey, $type, $relatedType,
            $parentKey ?: $this->getKeyName(), $relatedKey ?: 'id'
        );
    }

    /**
     * Define fully polymorphic relation with specified related model
     *
     * @param string $related
     * @param string $name
     * @param string $relatedName
     * @param string $table
     * @param string|null $type
     * @param string|null $relatedType
     * @param string|null $foreignPivotKey
     * @param string|null $relatedPivotKey
     * @param null $parentKey
     * @param null $relatedKey
     *
     * @return \Pisochek\PivotPolymorph\Relations\MorphsToMany
     */
    public function morphsToMany(
        $related, $name, $relatedName, $table, $foreignPivotKey = null, $relatedPivotKey = null, $type = null,
        $relatedType = null, $parentKey = null, $relatedKey = null
    ) {
        list($type, $foreignPivotKey) = $this->getMorphs(Str::snake($name), $type, $foreignPivotKey);
        list($relatedType, $relatedPivotKey) = $this->getMorphs(
            Str::snake($relatedName), $relatedType, $relatedPivotKey
        );

        $instance = $this->newRelatedInstance($related);

        $table = $table ?: (Str::plural($name) . '_' . Str::plural($relatedName));

        return new MorphsToMany(
            $instance->newQuery(), $this, $name, $relatedName, $table, $foreignPivotKey, $relatedPivotKey, $type,
            $relatedType, $parentKey ?: $this->getKeyName(), $relatedKey ?: $instance->getKeyName()
        );
    }

    protected function newBelongsToMany(Builder $query, Model $parent, $table, $foreignPivotKey, $relatedPivotKey,
        $parentKey, $relatedKey, $relationName = null)
    {
        return new BelongsToMany($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
    }
}
