<?php

namespace App\Database;

use Illuminate\Database\Eloquent\Concerns\HasRelationships as IlluminateHasRelationships;
use Illuminate\Support\Str;

trait HasRelationships
{
    use IlluminateHasRelationships;

    /**
     * Define fully polymorphic relation
     *
     * @param $name
     * @param $oName
     * @param $table
     * @param null $type
     * @param null $oType
     * @param null $id
     * @param null $oId
     * @param null $foreign
     * @param null $oForeign
     *
     * @return \App\Database\MorphsTo
     */
    public function morphsTo(
        $name, $oName, $table, $type = null, $oType = null, $id = null, $oId = null, $foreign = null, $oForeign = null
    ) {
        list($oType, $oId) = $this->getMorphs(Str::snake($oName), $oType, $oId);
        list($type, $id) = $this->getMorphs(Str::snake($name), $type, $id);

        return new MorphsTo(
            $this, $name, $oName, $table, $id, $oId, $type, $oType, $foreign ?: $this->getKeyName(),
            $oForeign ?: 'id'
        );
    }

    /**
     * Define fully polymorphic relation with specified related model
     *
     * @param string $related
     * @param string $name
     * @param string $oName
     * @param string $table
     * @param string|null $type
     * @param string|null $oType
     * @param string|null $id
     * @param string|null $oId
     * @param null $foreign
     * @param null $oForeign
     *
     * @return \App\Database\MorphsToMany
     */
    public function morphsToMany(
        $related, $name, $oName, $table, $type = null, $oType = null, $id = null, $oId = null, $foreign = null,
        $oForeign = null
    ) {
        list($type, $id) = $this->getMorphs(Str::snake($name), $type, $id);
        list($oType, $oId) = $this->getMorphs(Str::snake($oName), $oType, $oId);

        $instance = $this->newRelatedInstance($related);

        $table = $table ?: (Str::plural($name) . '_' . Str::plural($oName));

        return new MorphsToMany(
            $instance->newQuery(), $this, $name, $oName, $table, $id, $oId, $type, $oType,
            $foreign ?: $this->getKeyName(), $oForeign ?: $instance->getKeyName()
        );
    }

    public function morphsOne()
    {
        // TODO: implement
    }
}
