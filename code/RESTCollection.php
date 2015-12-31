<?php

/**
 * A RESTCollection is a Noun that contains a collection of Items
 *
 * Difference between Collection and Items
 *
 * - Collection only has one type of Item nested under it, but it can have multiple of that type of Item
 * - Item has many types of Nouns (Collections & Items) nested under it, but it can only have one of each type of Noun
 */
trait RESTCollection
{
    use RESTNoun;

    abstract public function getItems();
    abstract public function getItem($id);

    public function LinkFor($item)
    {
        if ($item->parent !== $this) {
            user_error('Tried to get link for noun that was not gotten from this collection', E_USER_ERROR);
        }
        return Controller::join_links($this->Link(), $item->getID());
    }

    protected function markAsNested($obj)
    {
        $obj->parent = $this;
        return $obj;
    }
}

class RESTCollection_Handler extends RESTNoun_Handler
{
}
