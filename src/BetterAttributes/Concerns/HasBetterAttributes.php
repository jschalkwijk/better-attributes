<?php

declare(strict_types=1);

namespace JornSchalkwijk\BetterAttributes\Concerns;

use Attribute;
use Exception;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use JornSchalkwijk\BetterAttributes\Enums\ReflectionClassMethod;

// /**
//  * @method array getPropertiesByAttribute(string $attribute, ?callable $callback = null)
//  * Get all properties that have the given Attribute
//  * @method array getPropertiesByAttributes(array $attribute, ?callable $callback = null)
//  * Get all properties that have all the given Attributes
//  * @method array getPropertiesIncludingAnyAttribute(array $attributes, ?callable $callback = null)
//  * Get all properties that have one of the given Attributes
//  * @method array getPropertiesExceptAttribute(string $attributes, ?callable $callback = null)
//  * Get all properties that don't have the given combination of Attributes.
//  * @method array getPropertiesExceptAttributes(string $attributes, ?callable $callback = null)
//  * Get all properties that don't have the given combination of Attributes.
//  * @method array getPropertiesExceptAnyAttribute(array $attributes, ?callable $callback = null)
//  * Get all properties that don't have any of the given Attributes
//  * @method array getPropertiesWithAttributes(?callable $callback = null)
//  * Get all properties that have one or more attributes
//  * @method array getPropertiesWithoutAttributes(?callable $callback = null)
//  * Get all properties that don't have any attribute
//  */

/**
 * @method array getPropertiesByAttribute(string $attribute, ?callable $callback = null)
 * @method array getPropertiesByAttributes(string $attribute, ?callable $callback = null)
 * @method array getPropertiesIncludingAnyAttribute(string $attribute, ?callable $callback = null)
 * @method array getPropertiesExceptAttribute(string $attribute, ?callable $callback = null)
 * @method array getPropertiesWithoutAttributes(string $attribute, ?callable $callback = null)
 */
trait HasBetterAttributes
{
    protected static array $attributeEvents = [
        'beforeAttributes' => [],
        'onAttribute' => [],
        'afterAttributes' => [],
    ];

    protected static array $methodEvents = [
        'beforeMethod' => [],
        'onMethod' => [],
        'afterMethod' => [],
    ];

    protected static array $propertyEvents = [
        'beforeProperties' => [],
        'onProperty' => [],
        'afterProperties' => [],
    ];

    private static array $allowedFilterMethods = [
        'Attribute',
        'Including',
        'All',
        'Except',
        'Only',
        'Without',
    ];

    /**
     * Handle dynamic method calls.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters): mixed
    {
        try {
            return parent::__call($method, $parameters);
        } catch (Exception $e) {
        }

        if ($this->shouldResolve($method)) {
            return $this->resolveMethod($method, $parameters);
        }
    }

    private function shouldResolve($method): bool
    {
        // Determine if the method is a candidate for a BetterAttributes method
        if (
            !$this->startsWith($method, ReflectionClassMethod::cases())
            ||
            !$this->endsWith($method, ['Attribute', 'Attributes'])
        ) {
            return false;
        }

        return true;
    }

    private function resolveMethod($method, $parameters): array
    {
        // Determine if the called method has a valid filter condition compatibly with BetterAttributes
        foreach (ReflectionClassMethod::cases() as $case) {

            if (!$this->startsWith($method, $case->value)) {
                continue;
            }

            $betterMethod = $case;
            print($betterMethod->value);
            $condition = ($this->endsWith($method, 'Attribute')) ? substr($case->value, -9) : substr($case->value, -10);
            if (in_array(substr($condition, strlen($case->value)), self::$allowedFilterMethods)) {
                break;
            }
        }

        if (!isset($betterMethod)) {
            return;
        }

        return $this->getBetterAttributes($betterMethod, $condition, ...$parameters);
    }

    private function hasAttribute($target, string $attribute, ?callable $callback = null): bool
    {
        $target->setAccessible(true);

        if (!empty($target->getAttributes($attribute))) {
            if (!is_null($callback)) {
                $callback($target, $target->getAttributes($attribute));
            }
            return true;
        }

        return false;
    }

    // private function hasOnlyAttributes($target, array $attribute, ?callable $callback = null): bool
    // {
    //     return false;
    // }

    private function hasAnyAttribute(ReflectionProperty|ReflectionMethod $target, array $attributes, ?callable $callback = null): bool
    {
        $target->setAccessible(true);
        $filterdAttributes = [];

        foreach ($attributes as $attribute) {
            if (!empty($propertyAttributes = $target->getAttributes($attribute))) {
                $filterdAttributes[$attribute] = $propertyAttributes;
            }
        }

        if (empty($filterdAttributes)) {
            return false;
        }

        if (!is_null($callback)) {
            $callback($target, $filterdAttributes);
        }

        return true;
    }

    private function hasAllAttributes(ReflectionProperty|ReflectionMethod $target, array $attributes, ?callable $callback = null)
    {
        $target->setAccessible(true);
        $filterdAttributes = [];

        foreach ($attributes as $attribute) {
            if (!empty($propertyAttributes = $target->getAttributes($attribute))) {
                $filterdAttributes[$attribute] = $propertyAttributes;
                continue;
            }

            return false;
        }

        if (!is_null($callback)) {
            $callback($target, $filterdAttributes);
        }

        return true;
    }

    // protected function hasNoneOfAttributes(ReflectionProperty|ReflectionMethod $target, array $attribute, ?callable $callback = null)
    // {
    //     if (!empty($target->getAttributes($attribute))) {
    //         if (!is_null($callback)) {
    //             $callback($target, $target->getAttributes($attribute));
    //         }
    //         return true;
    //     }

    //     return false;
    // }

    private function hasNoAttributes(ReflectionProperty|ReflectionMethod $target, ?callable $callback = null)
    {
        if (empty($target->getAttributes())) {
            if (!is_null($callback)) {
                $callback($target);
            }
            return true;
        }

        return false;
    }

    public function isValidAttribute($attribute): bool
    {
        // if (!class_exists($attribute, false)) {
        //     throw new LogicException("Unable to load class: $attribute");
        // }

        return count((new ReflectionClass($attribute))->getAttributes(Attribute::class)) === 1;
    }

    private function filter(
        ReflectionClassMethod $method,
        $condition,
        string|array $attributes,
        $callback
    ): array {
        // if(is_array($attributes)){

        // }

        // if (!is_array($attributes) && !$this->isValidAttribute($attributes)) {
        //     throw new LogicException("This class is not defined as an Attribute: $attributes");
        // }

        $reflectionClassMethod = $method->value;
        $filterMethod = 'has' . ucfirst($condition);
        return array_filter(
            (new ReflectionClass(get_class($this)))->$reflectionClassMethod(),
            function (ReflectionProperty|ReflectionMethod $property) use ($filterMethod, $attributes, $callback) {
                return $this->$filterMethod($property, $attributes, $callback);
            }
        );
    }

    private function getBetterAttributes(
        ReflectionClassMethod $method,
        string $filterCondition,
        string|array $attributes,
        ?callable $callback = null
    ): array {
        return $this->filter(
            method: $method,
            condition: $filterCondition,
            attributes: $attributes,
            callback: $callback
        );
    }

    // protected function getPropertiesByAttribute(
    //     string $attribute,
    //     ?callable $callback = null
    // ): array {
    //     return $this->filter(
    //         method: ReflectionClassMethod::PROPERTIES,
    //         condition: 'attribute',
    //         attributes: $attribute,
    //         callback: $callback
    //     );
    // }

    protected function getPropertiesByAttributes(
        array $attributes,
        ?callable $callback = null
    ): array {
        return $this->filter(
            method: ReflectionClassMethod::PROPERTIES,
            condition: 'allAttributes',
            attributes: $attributes,
            callback: $callback
        );
    }

    protected function getPropertiesByAnyAttribute(
        array $attributes,
        ?callable $callback = null
    ): array {
        return $this->filter(
            method: ReflectionClassMethod::PROPERTIES,
            condition: 'anyAttribute',
            attributes: $attributes,
            callback: $callback
        );
    }

    // protected function getPropertiesByAttribute(string $attribute, ?callable $callback = null): array
    // {
    //     return array_filter(
    //         (new ReflectionClass(get_class($this)))->getProperties(),
    //         function (ReflectionProperty $property) use ($attribute, $callback) {
    //             return $this->hasAttribute($property, $attribute, $callback);
    //         }
    //     );
    // }

    // protected function getPropertiesByAttributes(array $attributes, ?callable $callback = null): array
    // {
    //     return array_filter(
    //         (new ReflectionClass(get_class($this)))->getProperties(),
    //         function (ReflectionProperty $property) use ($attributes, $callback) {
    //             return $this->hasAllAttributes($property, $attributes, $callback);
    //         }
    //     );
    // }

    // protected function getPropertiesByAnyAttribute(array $attributes = null, ?callable $callback = null): array
    // {
    //     return array_filter(
    //         (new ReflectionClass(get_class($this)))->getProperties(),
    //         function (ReflectionProperty $property) use ($attributes, $callback) {
    //             return $this->hasAnyAttribute($property, $attributes, $callback);
    //         }
    //     );
    // }

    protected function getMethodsByAttribute(array|string $attribute, ?callable $callback = null): array
    {
        return array_filter(
            (new ReflectionClass(get_class($this)))->getMethods(),
            function (ReflectionMethod $method) use ($attribute, $callback) {
                $reflectionMethod = new ReflectionMethod($this->class, $method->getName());
                $reflectionMethod->setAccessible(true);
                if (
                    !empty($method->getAttributes($attribute))
                ) {
                    if (!is_null($callback)) {
                        $callback($method);
                    }
                    return true;
                }

                return false;
            }
        );
    }

    protected function getMethodsByAttributes(array|string $attribute, ?callable $callback = null): array
    {
        return array_filter(
            (new ReflectionClass(get_class($this)))->getMethods(),
            function (ReflectionMethod $method) use ($attribute, $callback) {
                $reflectionMethod = new ReflectionMethod($this->class, $method->getName());
                $reflectionMethod->setAccessible(true);
                if (
                    !empty($method->getAttributes($attribute))
                ) {
                    if (!is_null($callback)) {
                        $callback($method);
                    }
                    return true;
                }

                return false;
            }
        );
    }

    protected function getMethodsByAnyAttribute(?array $attributes = null, ?callable $callback = null): array
    {
        return array_filter(
            (new ReflectionClass(get_class($this)))->getProperties(),
            function (ReflectionMethod $method) use ($attributes, $callback) {
                $filterdAttributes = [];

                foreach ($attributes as $attribute) {
                    if (!empty($methodAttributes = $method->getAttributes($attribute))) {
                        $filterdAttributes[$attribute] = $methodAttributes;
                    }
                }

                if (empty($filterdAttributes)) {
                    return false;
                }

                if (!is_null($callback)) {
                    $callback($method, $filterdAttributes);
                }

                return true;
            }
        );
    }

    /**
     * Determine if a given string starts with a given substring.
     *
     * @param  string  $haystack
     * @param  string|string[]  $needles
     * @return bool
     */
    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle instanceof ReflectionClassMethod) {
                $needle = $needle->value;
            }
            if ((string) $needle !== '' && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string ends with a given substring.
     *
     * @param  string  $haystack
     * @param  string|string[]  $needles
     * @return bool
     */
    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle instanceof ReflectionClassMethod) {
                $needle = $needle->value;
            }

            if ((string) $needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
