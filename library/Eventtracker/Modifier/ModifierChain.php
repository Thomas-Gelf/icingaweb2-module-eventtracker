<?php

namespace Icinga\Module\Eventtracker\Modifier;

use JsonSerializable;

class ModifierChain implements JsonSerializable
{
    protected $modifiers = [];

    /**
     * ModifierChain constructor.
     * @param array $modifiers
     */
    public function __construct(array $modifiers)
    {
        foreach ($modifiers as $pair) {
            $this->addModifier($pair[1], $pair[0]);
        }
    }

    public static function fromSerialization(array $serializedModifiers)
    {
        $modifiers = [];
        foreach ($serializedModifiers as $modifier) {
            /** @var Modifier $class Just a hint, it's a string */
            $class = __NAMESPACE__ . '\\' . $modifier[1];
            $modifiers[] = [
                $modifier[0],
                new $class(
                    Settings::fromSerialization(isset($modifier[2]) ? $modifier[2] : (object) [])
                )
            ];
        }
        return new static($modifiers);
    }

    public function process(\stdClass $object)
    {
        foreach ($this->modifiers as list($propertyName, $modifier)) {
            assert($modifier instanceof Modifier);
            $value = $modifier->transform($object, $propertyName);
            if ($value instanceof ModifierUnset) {
                ObjectUtils::unsetSpecificValue($object, $propertyName);
            } else {
                ObjectUtils::setSpecificValue($object, $propertyName, $value);
            }
        }
    }

    public function addModifier(Modifier $modifier, $propertyName)
    {
        $this->modifiers[] = [$propertyName, $modifier];
    }

    public function jsonSerialize()
    {
        $result = [];
        foreach ($this->modifiers as $modifier) {
            $instance = $modifier[1];
            assert($instance instanceof Modifier);
            $result[] = [$modifier[0], $instance::getName(), $instance->getSettings()->jsonSerialize()];
        }

        return $result;
    }
}
