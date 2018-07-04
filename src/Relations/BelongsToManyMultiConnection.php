<?php

namespace Pisochek\PivotPolymorph\Relations;

class BelongsToManyMultiConnection extends BelongsToMany
{
    /**
     * @inheritdoc
     */
    protected function addWhereConstraints()
    {
        return $this;
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
                            ->distinct()
                            ->pluck($this->relatedKey);

        $query->whereIn($key, $ids);

        return $query;
    }

    /**
     * @inheritdoc
     */
    protected function aliasedPivotColumns()
    {
        return collect($this->pivotColumns)
            ->map(function ($column) {
                return $this->table . '.' . $column . ' as pivot_' . $column;
            })
            ->push("{$this->related->getTable()}.{$this->parentKey} as pivot_{$this->relatedPivotKey}")
            ->push($this->parent->getConnection()->raw("{$this->parent->getKey()} as pivot_{$this->foreignPivotKey}"))
            ->unique()
            ->all();
    }
}
