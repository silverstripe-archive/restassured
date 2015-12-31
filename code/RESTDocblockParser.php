<?php

require(BASE_PATH.'/restassured/thirdparty/markdown.php');

/**
 * Parses a docblock into a nested array-alike.
 *
 * Handles nested constructs, like:
 *
 * @foo
 *   This is the free-text applying to the foo tag
 *   @bar A
 *   @bar B
 *   @baz
 *
 * This example is two level, but will handle every depth.
 *
 * The result is an object that you access like an array, except that if the property you use to access doesn't exist, it
 * returns a "null-ish" object instead of throwing an error.
 * 
 * Tags can appear more than once at any level. For any tag you can either loop through the result of $block[tagname]
 * or just directly access sub-properties, in which case it returns the value from the first tag found (if it exists)
 * 
 * @package restassured
 */
class RESTDocblockParser implements arrayaccess
{
    public static function parse($source)
    {
        $string = $source;

        if ($source instanceof Reflector) {
            $string = preg_replace('{^[ \t]*(/\*+|\*+/?)}m', '', $source->getDocComment());
        }

        $lines = preg_split('/^/m', $string);
        return new RESTDocblockParser($lines, -1);
    }

    public function __construct(&$lines, $level)
    {
        $res = array();
        $body = array();

        while (count($lines)) {
            $line = array_shift($lines);

            // Blank lines just get a carriage return added (for markdown) and otherwise ignored
            if (!trim($line)) {
                $body[] = "\n";
                continue;
            }

            // Get the indent
            preg_match('/^(\s*)/', $line, $match);
            $indent = $match[1];

            // Check to make sure we're still indented
            if (strlen($indent) <= $level) {
                array_unshift($lines, $line);
                break;
            }

            // Check for tag
            if (preg_match('/^@([^\s]+)(.*)$/', trim($line), $match)) {
                $tag = $match[1];

                $sub = new RESTDocblockParser($lines, strlen($indent));
                $sub['details'] = trim($match[2]);

                if (!isset($res[$tag])) {
                    $res[$tag] = new RESTDocblockParser_Sequence();
                }
                $res[$tag][] = $sub;
            } else {
                $body[] = substr($line, $level > 0 ? $level : 0);
            }
        }

        $res['body'] = Markdown(implode("", $body));

        $this->res = $res;
    }

    public function offsetExists($offset)
    {
        return isset($this->res[$offset]);
    }

    public function offsetGet($offset)
    {
        if (isset($this->res[$offset])) {
            return $this->res[$offset];
        }
        // No match, return the null
        return singleton('RESTDocblockParser_Null');
    }

    public function offsetSet($offset, $value)
    {
        $this->res[$offset] = $value;
    }

    public function offsetUnset($offset)
    { /* NOP */
    }
}

/**
 * When a tag is found in a docblock, there might be none, one, or many of that same tag.
 * This will probably depend per tag, but we don't want to have to pre-specify.
 *
 * So RESTDocblockParser creates one of these for every tag name. This then simulates an array
 * in such a way that you can
 *
 * - Loop over every item in an array
 *  e.g. foreach ($seq as ...)
 * - Access an individual item in the sequence (returns nothing if that item doesn't exist without error)
 *  e.g. echo $seq[0]
 * - Access a field from the first item in the sequence if it exists
 *  e.g. echo $seq['foo']; // (same as $res = $seq[0]; if ($res) echo $res['foo'];)
 *
 * @package restassured
 */
class RESTDocblockParser_Sequence implements arrayaccess, IteratorAggregate
{

    protected $seq = array();

    public function offsetExists($offset)
    {
        return isset($this->seq[$offset]);
    }

    public function offsetGet($offset)
    {
        if (is_numeric($offset)) {
            if (isset($this->seq[$offset])) {
                return $this->seq[$offset];
            }
        } else {
            if (isset($this->seq[0])) {
                return $this->seq[0][$offset];
            }
        }
        // No match, return the null
        return singleton('RESTDocblockParser_Null');
    }

    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            $this->seq[] = $value;
        } else {
            $this->seq[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        unset($this->seq[$offset]);
    }

    public function getIterator()
    {
        return new ArrayIterator($this->seq);
    }
}

/**
 * When a property is accessed that doesn't exist, one of these is returned. It always returns itself, and otherwise
 * tries to act as "null-ish" as possible given PHPs limits
 * 
 * @package restassured
 */
class RESTDocblockParser_Null implements arrayaccess, IteratorAggregate
{
    public function __construct()
    {
    }

    public function offsetExists($offset)
    {
        return false;
    }
    public function offsetGet($offset)
    {
        return $this;
    }
    public function offsetSet($offset, $value)
    { /* NOP */
    }
    public function offsetUnset($offset)
    { /* NOP */
    }
    public function getIterator()
    {
        return new EmptyIterator();
    }

    public function __toString()
    {
        return '';
    }
    public function __toBool()
    {
        return false;
    }
}
