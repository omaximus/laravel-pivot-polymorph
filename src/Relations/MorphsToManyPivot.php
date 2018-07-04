<?php

namespace Pisochek\PivotPolymorph\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

class MorphsToManyPivot extends MorphPivot
{
    /**
     * The type of the polymorphic relation.
     *
     * Explicitly define this so it's not included in saved attributes.
     *
     * @var string
     */
    protected $relatedMorphType;

    /**
     * The value of the polymorphic relation.
     *
     * Explicitly define this so it's not included in saved attributes.
     *
     * @var string
     */
    protected $relatedMorphClass;

    /**
     * {@inheritdoc}
     */
    protected function setKeysForSaveQuery(Builder $query)
    {
        $query->where($this->relatedMorphType, $this->relatedMorphClass);

        return parent::setKeysForSaveQuery($query);
    }

    /**
     * {@inheritdoc}
     */
    public function delete()
    {
        $query = $this->getDeleteQuery();

        $query->where($this->relatedMorphType, $this->relatedMorphClass);

        return $query->delete();
    }

    /**
     * Set the morph type for the related in pivot.
     *
     * @param  string $morphType
     * @return $this
     */
    public function setOtherMorphType($morphType)
    {
        $this->relatedMorphType = $morphType;

        return $this;
    }

    /**
     * Set the morph class for the related in pivot.
     *
     * @param  string $morphClass
     * @return \Illuminate\Database\Eloquent\Relations\MorphPivot
     */
    public function setOtherMorphClass($morphClass)
    {
        $this->relatedMorphClass = $morphClass;

        return $this;
    }
}
