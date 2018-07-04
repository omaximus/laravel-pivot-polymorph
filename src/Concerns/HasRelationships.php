<?php

namespace Pisochek\PivotPolymorph\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasRelationships as IlluminateHasRelationships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Pisochek\PivotPolymorph\Relations\BelongsToMany;
use Pisochek\PivotPolymorph\Relations\MorphsTo;
use Pisochek\PivotPolymorph\Relations\MorphsToMany;
use Pisochek\PivotPolymorph\Relations\MorphToManyMultiConnection;

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
        $name,
        $relatedName,
        $table,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $parentKey = null,
        $type = null,
        $relatedType = null,
        $relatedKey = null
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
        $related,
        $name,
        $relatedName,
        $table,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $type = null,
        $relatedType = null,
        $parentKey = null,
        $relatedKey = null
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

    /**
     * Define a polymorphic, inverse many-to-many multi connection relationship.
     *
     * @param $related
     * @param $name
     * @param null $table
     * @param null $foreignPivotKey
     * @param null $relatedPivotKey
     * @param null $parentKey
     * @param null $relatedKey
     * @return \Pisochek\PivotPolymorph\Relations\MorphToManyMultiConnection
     */
    public function morphedByManyMultiConnection(
        $related,
        $name,
        $table = null,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $parentKey = null,
        $relatedKey = null
    ) {
        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();

        // For the inverse of the polymorphic many-to-many relations, we will change
        // the way we determine the foreign and other keys, as it is the opposite
        // of the morph-to-many method since we're figuring out these inverses.
        $relatedPivotKey = $relatedPivotKey ?: $name . '_id';

        return $this->morphToManyMultiConnection(
            $related, $name, $table, $foreignPivotKey,
            $relatedPivotKey, $parentKey, $relatedKey, true
        );
    }

    /**
     * Define a polymorphic many-to-many multi connection relationship.
     *
     * @param $related
     * @param $name
     * @param null $table
     * @param null $foreignPivotKey
     * @param null $relatedPivotKey
     * @param null $parentKey
     * @param null $relatedKey
     * @param bool $inverse
     * @return \Pisochek\PivotPolymorph\Relations\MorphToManyMultiConnection
     */
    public function morphToManyMultiConnection(
        $related,
        $name,
        $table = null,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $parentKey = null,
        $relatedKey = null,
        $inverse = false
    ) {
        $caller = $this->guessBelongsToManyRelation();

        // First, we will need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we will make the query
        // instances, as well as the relationship instances we need for these.
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $name . '_id';

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        // Now we're ready to create a new query builder for this related model and
        // the relationship instances for this relation. This relations will set
        // appropriate query constraints then entirely manages the hydrations.
        $table = $table ?: Str::plural($name);

        return $this->newMorphToManyMultiConnection(
            $instance->newQuery(), $this, $name, $table,
            $foreignPivotKey, $relatedPivotKey, $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(), $caller, $inverse
        );
    }

    /**
     * Instantiate a new MorphToManyMultiConnection relationship.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model $parent
     * @param $name
     * @param $table
     * @param $foreignPivotKey
     * @param $relatedPivotKey
     * @param $parentKey
     * @param $relatedKey
     * @param null $relationName
     * @param bool $inverse
     * @return \Pisochek\PivotPolymorph\Relations\MorphToManyMultiConnection
     */
    protected function newMorphToManyMultiConnection(
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
        return new MorphToManyMultiConnection(
            $query,
            $parent,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName,
            $inverse
        );
    }

    /**
     * @inheritdoc
     */
    protected function newBelongsToMany(
        Builder $query,
        Model $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null
    ) {
        return new BelongsToMany(
            $query,
            $parent,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName
        );
    }
}
