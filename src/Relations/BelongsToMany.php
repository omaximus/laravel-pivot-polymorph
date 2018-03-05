<?php

namespace Pisochek\PivotPolymorph\Relations;

use Illuminate\Database\Eloquent\Relations\BelongsToMany as IlluminateBelongsToMany;

class BelongsToMany extends IlluminateBelongsToMany
{
    protected $events = [
        'attaching' => null,
        'attached'  => null,
        'detaching' => null,
        'detached'  => null,
        'updating'  => null,
        'updated'   => null,
    ];

    public function attach($id, array $attributes = [], $touch = true)
    {
        $this->fireEvent('attaching', $id, $attributes);
        parent::attach($id, $attributes, $touch);
        $this->fireEvent('attached', $id, $attributes);
    }

    public function updateExistingPivot($id, array $attributes, $touch = true)
    {
        $this->fireEvent('updating', $id, $attributes);
        parent::updateExistingPivot($id, $attributes, $touch);
        $this->fireEvent('updated', $id, $attributes);
    }

    public function detach($ids = null, $touch = true)
    {
        $this->fireEvent('detaching', $ids);
        parent::detach($ids, $touch);
        $this->fireEvent('detached', $ids);
    }

    public function events(array $events)
    {
        $this->events = array_only(array_merge($this->events, $events), array_keys($this->events));

        return $this;
    }

    protected function fireEvent(string $name, ...$parameters)
    {
        if ($this->events[$name]) {
             event(new $this->events[$name]($this->getParent(), $parameters));
        }
    }
}
