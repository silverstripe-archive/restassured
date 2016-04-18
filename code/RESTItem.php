<?php

/**
 * A RESTItem is a Noun that does not contain a sequence of a type of Noun
 *
 * Difference between Collection and Items
 *
 * - Collection only has one type of Item nested under it, but it can have multiple of that type of Item
 * - Item has many types of Nouns (Collections & Items) nested under it, but it can only have one of each type of Noun
 */
trait RESTItem
{
    use RESTNoun;

    abstract public function getID();

    public function LinkFor($item)
    {
        if ($item->parent !== $this) {
            user_error('Tried to get link for noun that is not nested with this item', E_USER_ERROR);
        }
        return Controller::join_links($this->Link(), $item->linkFragment);
    }

    protected function markAsNested($obj, $call)
    {
        $obj->parent = $this;
        $obj->linkFragment = $call;
        return $obj;
    }
}

class RESTItem_Handler extends RESTNoun_Handler
{
}
