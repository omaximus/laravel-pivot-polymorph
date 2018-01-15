#Polymorphic relations for eloquent.

This package supports fully morph many-to-many relation.

##Usage

Include trait `\Pisochek\PivotPolymorph\Concerns\HasRelationships` to model.
 Then like any other relation, write, for example:
 
 `return $this->morphsTo('parentName', 'relatedName', 'table');`
 
 Any your thoughts and corrections/extensions are much appreciated, functionality is not full yet.