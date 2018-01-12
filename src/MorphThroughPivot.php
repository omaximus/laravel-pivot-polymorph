<?php

namespace App\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

class MorphThroughPivot extends MorphPivot
{
    /**
     * The type of the polymorphic relation.
     *
     * Explicitly define this so it's not included in saved attributes.
     *
     * @var string
     */
    protected $oMorphType;

    /**
     * The value of the polymorphic relation.
     *
     * Explicitly define this so it's not included in saved attributes.
     *
     * @var string
     */
    protected $oMorphClass;

    /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery(Builder $query)
    {
        $query->where($this->oMorphType, $this->oMorphClass);

        return parent::setKeysForSaveQuery($query);
    }

    /**
     * Delete the pivot model record from the database.
     *
     * @return int
     */
    public function delete()
    {
        $query = $this->getDeleteQuery();

        $query->where($this->oMorphType, $this->oMorphClass);

        return $query->delete();
    }

    /**
     * Set the morph type for the related in pivot.
     *
     * @param  string  $morphType
     * @return $this
     */
    public function setOtherMorphType($morphType)
    {
        $this->oMorphType = $morphType;

        return $this;
    }

    /**
     * Set the morph class for the related in pivot.
     *
     * @param  string  $morphClass
     * @return \Illuminate\Database\Eloquent\Relations\MorphPivot
     */
    public function setOtherMorphClass($morphClass)
    {
        $this->oMorphClass = $morphClass;

        return $this;
    }
}
