<?php

namespace Sabre\VObject;

/**
 * Property
 *
 * A property is always in a KEY:VALUE structure, and may optionally contain
 * parameters.
 *
 * @copyright Copyright (C) 2007-2013 fruux GmbH. All rights reserved.
 * @author Evert Pot (http://evertpot.com/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class Property extends Node {

    /**
     * Property name.
     *
     * This will contain a string such as DTSTART, SUMMARY, FN.
     *
     * @var string
     */
    public $name;

    /**
     * Property group.
     *
     * This is only used in vcards
     *
     * @var string
     */
    public $group;

    /**
     * List of parameters
     *
     * @var string
     */
    public $parameters = array();

    /**
     * Current value
     *
     * @var mixed
     */
    protected $value;

    /**
     * Factory method for creating new properties
     *
     * This method automatically searches for the correct property class, based
     * on its name.
     *
     * You can specify the parameters either in key=>value syntax, in which case
     * parameters will automatically be created, or you can just pass a list of
     * Parameter objects.
     *
     * @param string $name
     * @param string|null|array $value
     * @param array $parameters
     * @return Property
     */
    static public function create($name, $value = null, array $parameters = array()) {

        return NodeFactory::createProperty($name, $value, $parameters);

    }

    /**
     * Creates the generic property.
     *
     * You can specify the parameters either in key=>value syntax, in which case
     * parameters will automatically be created, or you can just pass a list of
     * Parameter objects.
     *
     * @param string $name
     * @param string|array|null $value
     * @param array $parameters List of parameters
     * @return void
     */
    public function __construct($name, $value = null, array $parameters = array()) {

        if (strpos($name,'.')) {
            $p = explode('.', $name, 2);
            $this->group = $p[0];
            $this->name = strtoupper($p[1]);
        } else {
            $this->name = strtoupper($name);
        }

        $this->setValue($value);
        foreach($parameters as $k=>$child) {
            if ($child instanceof Parameter) {

                // Parameter object
                $this->add($child);
            } else {

                // Parameter key=>value
                $this->add($k, $child);
            }
        }

    }

    /**
     * Updates the current value.
     *
     * This may be either a single, or multiple strings in an array.
     *
     * @param string|array $value
     * @return void
     */
    public function setValue($value) {

        $this->value = $value;

    }

    /**
     * Returns the current value
     *
     * This may be either a single, or multiple strings in an array.
     *
     * @param string|array $value
     * @return void
     */
    public function getValue() {

        return $this->value;

    }

    /**
     * Adds a new parameter, and returns the new item.
     *
     * This method has 2 possible signatures:
     *
     * add(Parameter $param) // Adds a new parameter as an object.
     * add($name, string|array $value) // Adds a new parameter by name.
     *
     * @return Node
     */
    public function add($a1, $a2 = null) {

        if ($a1 instanceof Parameter) {
            if (!is_null($a2)) {
                throw new \InvalidArgumentException('The second argument must not be specified, when passing a VObject');
            }
            $a1->parent = $this;
            $this->parameters[] = $a1;

            return $a1;

        } elseif(is_string($a1)) {

            $parameter = new Parameter($a1, $a2);
            $parameter->parent = $this;
            $this->parameters[] = $parameter;

            return $parameter;

        } else {

            throw new \InvalidArgumentException('The first argument must either be a Node a string');

        }

    }

    /**
     * Returns an iterable list of children
     *
     * @return array
     */
    public function parameters() {

        return $this->parameters;

    }

    /**
     * Turns the object back into a serialized blob.
     *
     * @return string
     */
    public function serialize() {

        $str = $this->name;
        if ($this->group) $str = $this->group . '.' . $this->name;

        foreach($this->parameters as $param) {

            $str.=';' . $param->serialize();

        }

        $src = array(
            '\\',
            "\n",
        );
        $out = array(
            '\\\\',
            '\n',
        );
        $str.=':' . str_replace($src, $out, $this->getValue());

        $out = '';
        while(strlen($str)>0) {
            if (strlen($str)>75) {
                $out.= mb_strcut($str,0,75,'utf-8') . "\r\n";
                $str = ' ' . mb_strcut($str,75,strlen($str),'utf-8');
            } else {
                $out.=$str . "\r\n";
                $str='';
                break;
            }
        }

        return $out;

    }

    /**
     * Called when this object is being cast to a string.
     *
     * If the property only had a single value, you will get just that. In the
     * case the property had multiple values, the contents will be escaped and
     * combined with ,.
     *
     * @return string
     */
    public function __toString() {

        return (string)$this->getValue();

    }

    /* ArrayAccess interface {{{ */

    /**
     * Checks if an array element exists
     *
     * @param mixed $name
     * @return bool
     */
    public function offsetExists($name) {

        if (is_int($name)) return parent::offsetExists($name);

        $name = strtoupper($name);

        foreach($this->parameters as $parameter) {
            if ($parameter->name == $name) return true;
        }
        return false;

    }

    /**
     * Returns a parameter, or parameter list.
     *
     * @param string $name
     * @return Node
     */
    public function offsetGet($name) {

        if (is_int($name)) return parent::offsetGet($name);
        $name = strtoupper($name);

        $result = array();
        foreach($this->parameters as $parameter) {
            if ($parameter->name == $name)
                $result[] = $parameter;
        }

        if (count($result)===0) {
            return null;
        } elseif (count($result)===1) {
            return $result[0];
        } else {
            $result[0]->setIterator(new ElementList($result));
            return $result[0];
        }

    }

    /**
     * Creates a new parameter
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function offsetSet($name, $value) {

        if (is_int($name)) parent::offsetSet($name, $value);

        if (is_scalar($value)) {
            if (!is_string($name))
                throw new \InvalidArgumentException('A parameter name must be specified. This means you cannot use the $array[]="string" to add parameters.');

            $this->offsetUnset($name);
            $parameter = new Parameter($name, $value);
            $parameter->parent = $this;
            $this->parameters[] = $parameter;

        } elseif ($value instanceof Parameter) {
            if (!is_null($name))
                throw new \InvalidArgumentException('Don\'t specify a parameter name if you\'re passing a \\Sabre\\VObject\\Parameter. Add using $array[]=$parameterObject.');

            $value->parent = $this;
            $this->parameters[] = $value;
        } else {
            throw new \InvalidArgumentException('You can only add parameters to the property object');
        }

    }

    /**
     * Removes one or more parameters with the specified name
     *
     * @param string $name
     * @return void
     */
    public function offsetUnset($name) {

        if (is_int($name)) parent::offsetUnset($name);
        $name = strtoupper($name);

        foreach($this->parameters as $key=>$parameter) {
            if ($parameter->name == $name) {
                $parameter->parent = null;
                unset($this->parameters[$key]);
            }

        }

    }
    /* }}} */

    /**
     * This method is automatically called when the object is cloned.
     * Specifically, this will ensure all child elements are also cloned.
     *
     * @return void
     */
    public function __clone() {

        foreach($this->parameters as $key=>$child) {
            $this->parameters[$key] = clone $child;
            $this->parameters[$key]->parent = $this;
        }

    }

    /**
     * Validates the node for correctness.
     *
     * The following options are supported:
     *   - Node::REPAIR - If something is broken, and automatic repair may
     *                    be attempted.
     *
     * An array is returned with warnings.
     *
     * Every item in the array has the following properties:
     *    * level - (number between 1 and 3 with severity information)
     *    * message - (human readable message)
     *    * node - (reference to the offending node)
     *
     * @param int $options
     * @return array
     */
    public function validate($options = 0) {

        $warnings = array();

        // Checking if our value is UTF-8
        if (!StringUtil::isUTF8($this->value)) {
            $warnings[] = array(
                'level' => 1,
                'message' => 'Property is not valid UTF-8!',
                'node' => $this,
            );
            if ($options & self::REPAIR) {
                $this->value = StringUtil::convertToUTF8($this->value);
            }
        }

        // Checking if the propertyname does not contain any invalid bytes.
        if (!preg_match('/^([A-Z0-9-]+)$/', $this->name)) {
            $warnings[] = array(
                'level' => 1,
                'message' => 'The propertyname: ' . $this->name . ' contains invalid characters. Only A-Z, 0-9 and - are allowed',
                'node' => $this,
            );
            if ($options & self::REPAIR) {
                // Uppercasing and converting underscores to dashes.
                $this->name = strtoupper(
                    str_replace('_', '-', $this->name)
                );
                // Removing every other invalid character
                $this->name = preg_replace('/([^A-Z0-9-])/u', '', $this->name);

            }

        }

        // Validating inner parameters
        foreach($this->parameters as $param) {
            $warnings = array_merge($warnings, $param->validate($options));
        }

        return $warnings;

    }

}
