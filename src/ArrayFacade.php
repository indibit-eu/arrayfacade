<?php

declare(strict_types=1);

namespace Indibit;

use ArrayAccess;
use ArrayIterator;
use ArrayObject;
use Countable;
use Error;
use Exception;
use IteratorAggregate;
use JJWare\Util\Optional;
use JsonException;
use JsonSerializable;
use Traversable;

/**
 * Wraps PHP's built-in array functions, extends them and supports a functional, object-oriented style inspired by
 * Lodash
 *
 * IMPORTANT: empty(), is_array() and the array_...() functions cannot be called on instances of ArrayFacade
 */
class ArrayFacade implements ArrayAccess, JsonSerializable, Countable, IteratorAggregate
{
    /**
     * @param array|ArrayFacade $collection
     * @return ArrayFacade
     */
    public static function of(ArrayFacade|array $collection): self
    {
        if (is_array($collection)) {
            return new self($collection);
        } elseif ($collection instanceof self) {
            return new self($collection->elements); // TODO warum nicht einfach return $collection?
        } else {
            throw new Error('Expected array or ArrayFacade but got ' . gettype($collection));
        }
    }

    public static function ofElement($element): self
    {
        return new self([$element]);
    }

    public static function ofEmpty(): self
    {
        return new self([]);
    }

    private array $elements;

    private function __construct(array $elements)
    {
        $this->elements = $elements;
    }

    /**
     * @param callable|string $iteratee function or 'property' shorthand
     * @return self
     */
    public function map(callable|string $iteratee): self
    {
        if (is_string($iteratee)) {
            $iteratee = self::property($iteratee);
        } elseif (!is_callable($iteratee)) {
            throw new Error('Expected string or callable but got '.gettype($iteratee));
        }
        /*
         * $iteratee is invoked with (value, index|key), when array_keys(...) is passed to array_map()
         *
         * IMPORTANT: when passing more than one array to array_map(), the returned array always has sequential integer keys!
         *
         * FIXME raises "array_key_exists() expects parameter 2 to be array, null given" when iteratee doesn't return a value
         */
        return new self(array_map($iteratee, $this->elements, array_keys($this->elements)));
    }

    /**
     * @param null $iteratee callable|string function or property shorthand (identity function if null)
     * @return self
     */
    public function flatMap($iteratee=null): self
    {
        if ($iteratee === null) {
            $iteratee = self::identity();       // not allowed as default value
        } else {
            if (is_string($iteratee)) {
                $iteratee = self::property($iteratee);
            } elseif (!is_callable($iteratee)) {
                throw new Error();
            }
        }
        $flattened = [];
        foreach ($this->elements as $key => $value) {
            $result = $iteratee($value, $key, $this->elements);
            if (is_array($result) || ($result instanceof Traversable) || ($result instanceof self)) {
                foreach ($result as $item) {
                    $flattened[] = $item;
                }
            } elseif ($result !== null) {
                $flattened[] = $result;
            }
        }
        return new self($flattened);
    }

    /**
     * @param $iteratee callable|string function or property shorthand
     * @return self
     */
    public function mapValues(callable|string $iteratee): self
    {
        if (is_string($iteratee)) {
            $iteratee = self::property($iteratee);
        } elseif (!is_callable($iteratee)) {
            throw new Error();
        }
        /*
         * we can't use array_map() here, because it would return sequential integer keys when passing the keys as a
         * further array (see map())
         */
        $result = [];
        foreach ($this->elements as $key => $value) {
            $result[$key] = $iteratee($value, $key, $this->elements);
        }
        return new self($result);
    }

    /**
     * Extrahiert einen Ausschnitt eines Arrays
     *
     * @param int $offset Ist offset nicht negativ, beginnt die Sequenz bei diesem Offset in dem array.
     *                    Ist offset negativ, beginnt die Sequenz so viele Elemente vor dem Ende von array.
     * @param int|null $length Ist length angegeben und positiv, enthält die Sequenz bis zu so viele Elemente.
     *                         Ist das Array kürzer als length, dann werden nur die verfügbaren Array-Elemente vorhanden sein.
     *                         Ist length angegeben und negativ, endet die Sequenz so viele Elemente vor dem Ende des Arrays.
     *                         Wenn nicht angegeben, enthält die Sequenz alle Elemente von offset bis zum Ende von array.
     * @return self
     */
    public function slice(int $offset, int $length = null): self
    {
        return new self(array_slice($this->elements, $offset, $length));
    }

    /**
     * Teilt ein Array in mehrere Teil-Arrays mit jeweils maximaler Länge
     *
     * @param int $chunkSize Größe der Teil-Arrays
     * @return $this
     */
    public function chunk(int $chunkSize): self
    {
        return (new self(array_chunk($this->elements, $chunkSize)))
            ->map(fn ($e) => new self($e));
    }

    /**
     * Schnittmenge mit einem anderen Array ermitteln, wobei die Elemente mit === verglichen werden
     *
     * @param ArrayFacade $other
     * @return self
     */
    public function intersection(ArrayFacade $other): self
    {
        /*
         * array_intersect() erhält die Schlüssel, was in den meisten Fällen unerwartet ist
         */
        return new self(array_values(array_intersect($this->elements, $other->elements)));
    }

    /**
     * Schnittmenge mit einem anderen Array ermitteln, wobei $iteratee auf jedes Element angewendet wird, um das
     * Kriterium für den Vergleich zu ermitteln. Die resultierenden Elemente werden mit === verglichen.
     *
     * @param ArrayFacade $other
     * @param callable|string $iteratee
     * @return $this
     */
    public function intersectionBy(ArrayFacade $other, callable|string $iteratee): self
    {
        if (is_string($iteratee)) {
            $iteratee = self::property($iteratee);
        } elseif (!is_callable($iteratee)) {
            throw new Error();
        }
        /*
         * array_intersect() erhält die Schlüssel, was in den meisten Fällen unerwartet ist
         */
        return new self(
            array_values(
                array_uintersect(
                    $this->elements,
                    $other->elements,
                    fn ($l, $r) => $iteratee($l) === $iteratee($r) ? 0 : 1
                )
            )
        );
    }

    /**
     * @param ArrayFacade $other
     * @return ArrayFacade
     */
    public function difference(ArrayFacade $other): self
    {
        /*
         * array_diff() erhält die Schlüssel, was in den meisten Fällen unerwartet ist
         */
        return new self(array_values(array_diff($this->elements, $other->elements)));
    }

    /**
     * @param ArrayFacade $other
     * @param callable $comparator callback ( mixed $a, mixed $b ) : int kleiner als, gleich oder größer als Null
     * zurückgeben, wenn das erste Argument respektive kleiner, gleich oder größer als das zweite ist
     * @return ArrayFacade
     */
    public function differenceWith(ArrayFacade $other, callable $comparator): self
    {
        /*
         * array_udiff() erhält die Schlüssel, was in den meisten Fällen unerwartet ist
         */
        return new self(array_values(array_udiff($this->elements, $other->elements, $comparator)));
    }

    /**
     * $iteratee wird auf jedes Element angewendet, um das Kriterium für den Vergleich zu bilden
     *
     * @param ArrayFacade $other
     * @param callable|string $iteratee
     * @return $this
     */
    public function differenceBy(ArrayFacade $other, callable|string $iteratee): self
    {
        $r = [];
        if (is_string($iteratee)) {
            $iteratee = self::property($iteratee);
        } elseif (!is_callable($iteratee)) {
            throw new Error();
        }
        $otherMapped = $other->map($iteratee);
        foreach ($this->elements as $e) {
            if (!$otherMapped->includes($iteratee($e))) {
                $r[] = $e;
            }
        }
        return new self($r);
    }

    /**
     * @param callable $iteratee
     * @return $this
     * TODO in forEach/each umbenennen
     */
    public function walk(callable $iteratee): self
    {
        /*
         * array_walk() invokes $iteratee with (value, index|key)
         */
        array_walk($this->elements, $iteratee);
        return $this;
    }

    public function keys(): self
    {
        return new self(array_keys($this->elements));
    }

    /**
     * @param $iteratee callable|string
     * @return Optional Summe der Elemente des Arrays, wobei die Funktion den zu addierenden Wert je Element produziert
     */
    public function sumBy(callable|string $iteratee): Optional
    {
        if ($this->isEmpty()) {
            return Optional::empty();
        }
        if (is_string($iteratee)) {
            $iteratee = self::property($iteratee);
        } elseif (!is_callable($iteratee)) {
            throw new Error();
        }
        $s = 0;
        foreach ($this->elements as $e) {
            $summand = $iteratee($e);
            if (!is_numeric($summand)) {
                throw new Error();
            }
            $s += $summand;
        }
        return Optional::of($s);
    }

    /**
     * @return Optional Summe der Werte
     */
    public function sum(): Optional
    {
        return $this->isEmpty()
            ? Optional::empty()
            : Optional::of(array_sum($this->elements));
    }

    /**
     * @return Optional der höchste Wert
     */
    public function max(): Optional
    {
        return $this->isEmpty()
            ? Optional::empty()
            : Optional::of(max($this->elements));
    }

    /**
     * @param $iteratee callable|string Prädikat
     * @return Optional der niedrigste Wert anhand des Prädikats
     */
    public function minBy(callable|string $iteratee): Optional
    {
        if ($this->isEmpty()) {
            return Optional::empty();
        }
        if (is_string($iteratee)) {
            $iteratee = self::property($iteratee);
        } elseif (!is_callable($iteratee)) {
            throw new Error();
        }
        /*
         * TODO sicher nicht der effizienteste Weg...
         */
        $grouped = $this->groupBy($iteratee);
        return Optional::of($grouped[min($grouped->keys()->toArray())][0]);
    }

    /**
     * @return self
     */
    public function uniq(): self
    {
        $uniq = self::ofEmpty();
        foreach ($this->elements as $element) {
            if (!$uniq->includes($element)) {
                $uniq[] = $element;
            }
        }
        return $uniq;
    }

    /**
     * @param callable|string $iteratee wird für jedes Element aufgerufen und bildet das Kriterium für die Eindeutigkeit
     * @return self Kopie ohne Duplikate, das nur noch das erste Vorkommen eines Elements enthält
     */
    public function uniqBy(callable|string $iteratee): self
    {
        if (is_string($iteratee)) {
            $iteratee = self::property($iteratee);
        } elseif (!is_callable($iteratee)) {
            throw new Error();
        }
        $uniq = self::ofEmpty();
        foreach ($this->elements as $element) {
            $elementResult = $iteratee($element);
            $includedInUniq = $uniq->some(function ($uniqElement) use ($iteratee, $elementResult) {
                return $iteratee($uniqElement) == $elementResult;
            });
            if (!$includedInUniq) {
                $uniq[] = $element;
            }
        }
        return $uniq;
    }

    /**
     * @param string $glue
     * @param string|null $lastGlue possibly different last glue
     * @return string
     */
    public function join(string $glue, string $lastGlue = null): string
    {
        if ($lastGlue === null) {
            return join($glue, $this->elements);
        } else {
            $s = '';
            $n = count($this->elements);
            for ($i = 0; $i < $n; $i++) {
                $s .= $this->elements[$i];
                if ($i === $n - 2) {
                    $s .= $lastGlue;
                } elseif ($i < $n - 2) {
                    $s .= $glue;
                }
            }
            return $s;
        }
    }

    /**
     * @param callable|array|string $predicate
     * @return Optional
     */
    public function find(callable|array|string $predicate): Optional
    {
        if (is_array($predicate)) {
            $predicate = self::matches($predicate);
        } elseif (is_string($predicate)) {
            $predicate = self::property($predicate);
        } elseif (!is_callable($predicate)) {
            throw new Error('Expected array, string or callable but got '.gettype($predicate));
        }
        foreach ($this->elements as $element) {
            if ($predicate($element)) {
                return Optional::of($element);
            }
        }
        return Optional::empty();
    }

    /**
     * @param callable|array|string $predicate
     * @return Optional
     */
    public function findByKey(callable|array|string $predicate): Optional
    {
        if (is_array($predicate)) {
            $predicate = self::matches($predicate);
        } elseif (is_string($predicate)) {
            $predicate = self::property($predicate);
        } elseif (!is_callable($predicate)) {
            throw new Error('Expected array, string or callable but got '.gettype($predicate));
        }
        foreach ($this->elements as $key => $element) {
            if ($predicate($key)) {
                return Optional::of($element);
            }
        }
        return Optional::empty();
    }

    public function indexOf($value): ?int
    {
        $i = 0;
        foreach ($this->elements as $element) {
            if ($element == $value) {
                return $i;
            }
            $i++;
        }
        return null;
    }

    /**
     * @param callable|array|string $predicate
     * @return bool ob das Prädikat für mindestens ein Element wahr ist
     */
    public function some(callable|array|string $predicate): bool
    {
        if (is_array($predicate)) {
            $predicate = self::matches($predicate);
        } elseif (is_string($predicate)) {
            $predicate = self::property($predicate);
        } elseif (!is_callable($predicate)) {
            throw new Error('Expected array, string or callable but got '.gettype($predicate));
        }
        foreach ($this->elements as $element) {
            if ($predicate($element)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param callable|array|string $predicate
     * @return bool ob das Prädikat für alle Elemente wahr ist
     */
    public function every(callable|array|string $predicate): bool
    {
        if (is_array($predicate)) {
            $predicate = self::matches($predicate);
        } elseif (is_string($predicate)) {
            $predicate = self::property($predicate);
        } elseif (!is_callable($predicate)) {
            throw new Error('Expected array, string or callable but got '.gettype($predicate));
        }
        foreach ($this->elements as $element) {
            if (!$predicate($element)) {
                return false;
            }
        }
        return true;
    }

    /**
     * This may be called in PHP list syntax:
     *
     * list($positive, $negative) = $a->partition(...);
     *
     * @param callable|array|string $predicate
     * @return self
     */
    public function partition(callable|array|string $predicate): self
    {
        if (is_array($predicate)) {
            $predicate = self::matches($predicate);
        } elseif (is_string($predicate)) {
            $predicate = self::property($predicate);
        } elseif (!is_callable($predicate)) {
            throw new Error('Expected array, string or callable but got '.gettype($predicate));
        }

        $positive = [];
        $negative = [];
        foreach ($this->elements as $element) {
            if ($predicate($element)) {
                $positive[] = $element;
            } else {
                $negative[] = $element;
            }
        }
        return new self([new self($positive), new self($negative)]);
    }

    /**
     * @param callable|string ...$iteratees
     * @return self
     */
    public function sortBy(...$iteratees): self
    {
        $iteratees = self::of($iteratees)->map(function ($it) {
            if (is_string($it)) {
                return self::property($it);
            } elseif (is_callable($it)) {
                return $it;
            }
            throw new Error('Expected string or callable but got '.gettype($it));
        });
        $elements = (new ArrayObject($this->elements))->getArrayCopy();
        usort($elements, function ($l, $r) use ($iteratees) {
            foreach ($iteratees as $iteratee) {
                if ($iteratee($l) == $iteratee($r)) {
                    continue;
                } elseif ($iteratee($l) > $iteratee($r)) {
                    return 1;
                } elseif ($iteratee($l) < $iteratee($r)) {
                    return -1;
                }
            }
            return 0;
        });
        return self::of($elements);
    }

    /**
     * Anhand der Werte filtern
     * @param callable|array|string $predicate wird mit (Wert, Schlüssel) aufgerufen
     * @param bool $preserveKeys Schlüssel beibehalten?
     * @return self
     */
    public function filter(callable|array|string $predicate, bool $preserveKeys=false): self
    {
        if (is_array($predicate)) {
            $predicate = self::matches($predicate);
        } elseif (is_string($predicate)) {
            $predicate = self::property($predicate);
        } elseif (!is_callable($predicate)) {
            throw new Error();
        }
        /*
         * array_filter() retains the keys
         * ARRAY_FILTER_USE_BOTH: Wert und Schlüssel übergeben
         */
        if ($preserveKeys) {
            return new self(array_filter($this->elements, $predicate, ARRAY_FILTER_USE_BOTH));
        } else {
            return new self(array_values(array_filter($this->elements, $predicate, ARRAY_FILTER_USE_BOTH)));
        }
    }

    /**
     * Variant of groupBy where the key is not a property but an object (array)
     *
     * [
     *   {id: o1, type: {id: t1}},
     *   {id: o2, type: {id: t1}},
     *   {id: o3, type: {id: t2}}
     * ].groupByObject('type', 'id', 'objects')
     * = [
     *   {id: t1, objects: [ {id: o1}, {id: o2} ]},
     *   {id: t2, objects: [ {id: o3} ]}
     * ]
     *
     * @param string $keyObjectProperty property (or path) that references the key object
     * @param string $keyObjectIdProperty identifying property of the key object
     * @param string $valueProperty property that will contain the array of matching values
     * @param bool $removeKeyObjectFromValues
     * @return self
     */
    public function groupByObject(
        string $keyObjectProperty,
        string $keyObjectIdProperty,
        string $valueProperty,
        bool $removeKeyObjectFromValues = true
    ): self {
        list($itemsWithKey, $itemsWithoutKey) = $this->partition(function ($item) use ($keyObjectProperty) {
            $keyObject = self::property($keyObjectProperty)($item);
            return !empty($keyObject);
        });
        $result = $itemsWithKey
            ->map($keyObjectProperty)
            ->uniqBy($keyObjectIdProperty)
            ->walk(function (&$keyObject) use ($keyObjectProperty, $keyObjectIdProperty, $valueProperty, $removeKeyObjectFromValues) {
                $values = $this->filter(function ($item) use ($keyObject, $keyObjectProperty, $keyObjectIdProperty) {
                    $_keyObject = self::property($keyObjectProperty)($item);
                    return !empty($_keyObject) && $_keyObject[$keyObjectIdProperty] == $keyObject[$keyObjectIdProperty];
                });
                if ($removeKeyObjectFromValues) {
                    $values->walk(function (&$value) use ($keyObjectProperty) {
                        unset($value[$keyObjectProperty]);  // TODO
                    });
                }
                $keyObject[$valueProperty] = $values;
            });
        if (!$itemsWithoutKey->isEmpty()) {
            $result[] = [$keyObjectIdProperty => null, $valueProperty => $itemsWithoutKey];
        }
        return $result;
    }

    /**
     * Die Elemente zu einem gerichteten azyklischen Graphen zusammensetzen:
     *
     * toTree([
     *   { id: 1, parent: { id: 2 } },
     *   { id: 2, parent: null },
     *   { id: 3, parent: { id: 1 } },
     *   { id: 4, parent: { id: 2 } }
     * ], 'id', 'parent', 'children')
     *
     * =
     *
     * {
     *   id: 2,
     *   children: [
     *     { id: 1, children: [ { id: 3 } ] },
     *     { id: 4 }
     *   ]
     * }
     *
     * @param string $idKey
     * @param string $parentPath
     * @param string $childrenKey
     * @return self
     * @throws Exception
     */
    public function toGraph(string $idKey = 'id', string $parentPath = 'parent', string $childrenKey = 'children'): self
    {
        if ($this->isEmpty()) {
            return $this;
        }
        /*
         * Check vorher: fehlende Objekte?
         */
        $missingIds = $this->filter(fn ($e) => self::property($parentPath)($e) !== null)->map($parentPath)->map($idKey)->uniq()  // IDs der referenzierten Eltern
            ->difference($this->map('id')->uniq())                            // IDs der Elemente
        ;
        if (!$missingIds->isEmpty()) {
            throw new Exception('Elemente werden als Elter referenziert, sind aber nicht Teil der Liste: '.$missingIds->join(','));
        }
        /*
         * TODO auf Zyklen testen?
         */
        /*
         * Wurzeln sind Elemente, die keinen Elter referenzieren
         */
        return self::toGraphRecursive($this, fn ($e) => self::property($parentPath)($e) === null, $idKey, $parentPath, $childrenKey);
    }

    /**
     * @param self $elements verbleibende Liste von Elemente
     * @param callable $predicate Bedingung dafür, dass das Element eine Wurzel auf der aktuellen Ebene ist
     * @param string $idKey Schlüssel zur ID
     * @param string $parentPath Schlüssel zum Elternobjekt
     * @param string $childrenKey Schlüssel, unter dem die Kindobjekte angelegt werden
     * @return self
     */
    private static function toGraphRecursive(self $elements, callable $predicate, string $idKey, string $parentPath, string $childrenKey): self
    {
        list($roots, $leaves) = $elements->partition($predicate);
        return $roots->map(function ($r) use ($leaves, $idKey, $parentPath, $childrenKey) {
            unset($r[$parentPath]);     // TODO das müsste man anpassen, um statt Schlüssel auch Pfade zu unterstützen
            $r[$childrenKey] = self::toGraphRecursive($leaves, fn ($l) => (self::property($parentPath)($l))[$idKey] === $r[$idKey], $idKey, $parentPath, $childrenKey);
            return $r;
        });
    }

    /**
     * @return Optional
     */
    public function head(): Optional
    {
        if ($this->isEmpty()) {
            return Optional::empty();
        }
        return Optional::of($this->elements[0]);
    }

    /**
     * @param array $values
     * @return self die Vereinigung der beiden Listen
     */
    public function concat(... $values): self
    {
        /*
         * ACHTUNG: array_merge() überschreibt Elemente und indiziert sie neu (https://www.php.net/manual/de/function.array-merge.php)
         * TODO das Verhalten von array_merge() ist zu riskant
         */
        $result = $this->elements;
        foreach ($values as $value) {
            if (is_array($value)) {
                $result = array_merge($result, $value);
            } elseif ($value instanceof self) {
                $result = array_merge($result, $value->elements);
            } else {
                $result[] = $value;
            }
        }
        return new self($result);
    }

    /**
     * @param ArrayFacade $other
     * @return $this die Vereinigung der beiden assoziativen Arrays
     */
    public function union(self $other): self
    {
        /*
         * mit dem Operator + wird anders als bei array_merge() nicht neu indiziert
         * TODO aus der Operator hat riskante Nebeneffekte, wenn man Listen damit vereinigt
         */
        return new self($this->elements + $other->elements);
    }

    /**
     * @param $value
     * @return bool
     */
    public function includes($value): bool
    {
        return in_array($value, $this->elements, false);
    }

    /**
     * @param callable|string $iteratee
     * @return self
     */
    public function groupBy(callable|string $iteratee): self
    {
        if (is_string($iteratee)) {
            $iteratee = self::property($iteratee);
        } elseif (!is_callable($iteratee)) {
            throw new Error('Expected string or callable but got '.gettype($iteratee));
        }
        $result = [];
        foreach ($this->elements as $element) {
            $key = $iteratee($element);
            if (!(is_string($key) or is_int($key))) {
                /*
                 * PHP can only use strings and integers as keys
                 */
                $key = strval($key);
            }
            if (array_key_exists($key, $result)) {
                $result[$key][] = $element;
            } else {
                $result[$key] = new self([$element]);
            }
        }
        return new self($result);
    }

    /**
     * The corresponding value of each key is the last element responsible for generating the key.
     *
     * @param callable|string $iteratee
     * @param bool $checkForUniqueKeys überprüfen, ob die Schlüssel eindeutig sind?
     * @return ArrayFacade
     * @throws Exception
     */
    public function keyBy(callable|string $iteratee, bool $checkForUniqueKeys = false): self
    {
        if (is_string($iteratee)) {
            $iteratee = self::property($iteratee);
        } elseif (!is_callable($iteratee)) {
            throw new Error();
        }
        $result = [];
        foreach ($this->elements as $element) {
            $key = $iteratee($element);
            if ($checkForUniqueKeys && array_key_exists($key, $result)) {
                throw new Exception('Schlüssel nicht eindeutig');
            }
            $result[$key] = $element;
        }
        return new self($result);
    }

    /**
     * Whether a offset exists
     * @param mixed $offset
     * @return boolean true on success or false on failure
     */
    public function offsetExists($offset): bool
    {
        if (!($offset === null | is_string($offset) || is_int($offset))) {
            throw new Error('Expected string or int as offset but got '.gettype($offset));
        }
        return isset($this->elements[$offset]);
    }

    /**
     * Offset to retrieve
     * @param mixed $offset
     * @return mixed Can return all value types
     */
    public function offsetGet($offset): mixed
    {
        return $this->offsetExists($offset)
            ? $this->elements[$offset]
            : null;
    }

    /**
     * Offset to set
     * @param mixed $offset The offset to assign the value to
     * @param mixed $value The value to set
     */
    public function offsetSet($offset, $value): void
    {
        if (isset($offset)) {
            $this->elements[$offset] = $value;
        } else {
            $this->elements[] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->elements[$offset]);
    }

    public function jsonSerialize(): mixed
    {
        return $this->elements;
    }

    public function count(): int
    {
        return count($this->elements);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->count() == 0;
    }

    public function get($key): Optional
    {
        return $this->containsKey($key)
            ? Optional::of($this->elements[$key])
            : Optional::empty();
    }

    /**
     * @return Optional zufälliges Element aus einer Liste
     */
    public function getRandom(): Optional
    {
        return $this->isEmpty()
            ? Optional::empty()
            : Optional::of($this->elements[mt_rand(0, $this->count() - 1)]);
    }

    /**
     * @param int $count
     * @return $this Mehrere zufällige und eindeutige Elemente aus einer Liste
     */
    public function getRandomList(int $count): self
    {
        $result = self::ofEmpty();
        if ($this->count() <= $count) {
            return $this;
        }
        do {
            $random = $this->getRandom()->get();
            if (!$result->includes($random)) {
                $result[] = $random;
            }
        } while ($result->count() < $count);
        return $result;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->elements);
    }

    /**
     * @param mixed $key
     * @return bool
     */
    public function containsKey(mixed $key): bool
    {
        return array_key_exists($key, $this->elements);
    }

    /**
     * A::of({ 'a' => { 'b' => { 'c' => 1 } } })->containsPath('a.b.c') === true
     *
     * @param string $path
     * @return bool ob der Pfad existiert
     */
    public function containsPath(string $path): bool
    {
        $pathElements = explode('.', $path);
        $val = $this->elements;
        foreach ($pathElements as $pathElement) {
            if (is_array($val)) {
                if (array_key_exists($pathElement, $val)) {     // array_key_exists() geht nicht für ArrayFacade
                    $val = $val[$pathElement];
                } else {
                    return false;
                }
            } elseif ($val instanceof ArrayFacade) {
                if ($val->containsKey($pathElement)) {
                    $val = $val[$pathElement];      // auf Objekte von ArrayFacade können wir zwar mit [] zugreifen, nicht aber mit ->{}
                } else {
                    return false;
                }
            } elseif (is_object($val)) {
                if (property_exists($val, $pathElement)) {
                    $val = $val->{$pathElement};
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * @param ArrayFacade $other
     * @return bool whether the wrapped elements equal the other's by identity (===)
     */
    public function equals(ArrayFacade $other): bool
    {
        $n = $this->count();
        if ($n !== $other->count()) {
            return false;
        }
        while ($n > 0) {
            if ($this->elements[--$n] !== $other->elements[$n]) {
                return false;
            }
        }
        return true;
    }

    public function values(): self
    {
        return new self(array_values($this->elements));
    }

    public function toArray(): array
    {
        return $this->elements;
    }

    /**
     * @return string
     * @throws JsonException
     */
    public function __toString(): string
    {
        return json_encode($this, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    public function push($element): void
    {
        $this->elements[] = $element;
    }

    public function pop()
    {
        return array_pop($this->elements);
    }

    /**
     * @return $this Kopie in umgekehrter Reihenfolge
     */
    public function reverse(): self
    {
        /*
         * preserve_keys=true würde die numerischen Schlüssel beibehalten, was unerwartet wäre
         */
        return new self(array_reverse($this->elements, false));
    }

    /**
     * @param string $path
     * @return callable a function that returns the value at path of a given object
     */
    private static function property(string $path): callable
    {
        /*
         * in ArrayFacade verlegen?
         */
        $pathElements = explode('.', $path);
        return function ($element) use ($pathElements) {
            $val = $element;
            foreach ($pathElements as $pathElement) {
                if (is_array($val)) {
                    if (array_key_exists($pathElement, $val)) {     // array_key_exists() geht nicht für ArrayFacade
                        $val = $val[$pathElement];
                    } else {
                        throw new Exception("Property $pathElement not found, only have " . join(', ', array_keys($val)));
                    }
                } elseif ($val instanceof ArrayFacade) {
                    if ($val->containsKey($pathElement)) {
                        $val = $val[$pathElement];      // auf Objekte von ArrayFacade können wir zwar mit [] zugreifen, nicht aber mit ->{}
                    } else {
                        throw new Exception("Property $pathElement not found, only have " . $val->keys()->join(', '));
                    }
                } elseif (is_object($val)) {
                    if (property_exists($val, $pathElement)) {
                        $val = $val->{$pathElement};
                    } else {
                        $properties = get_object_vars($val);
                        if (count($properties)) {
                            throw new Exception("Property $pathElement not found, only have " . join(', ', $properties));
                        } else {
                            throw new Exception("Property $pathElement not found, object has no properties");
                        }
                    }
                } else {
                    throw new Exception("Failed to access property $pathElement of value of type " . gettype($val));
                }
            }
            return $val;
        };
    }

    /**
     * @param array $expected
     * @return callable a function that performs a partial comparison between an actual object and an expected (partial)
     *  one, returning true if the actual object has equivalent property values, else false
     */
    private static function matches(array $expected): callable
    {
        return function ($actual) use ($expected) {
            foreach ($expected as $key => $expectedValue) {
                $actualValue = ArrayFacade::property($key)($actual);
                if ($actualValue != $expectedValue) {      // TODO don't use fixed comparison
                    return false;
                }
            }
            return true;
        };
    }

    public static function identity(): callable
    {
        return fn ($x, ...$ignored) => $x;
    }
}
