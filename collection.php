<?php

namespace pdquery;

interface ICollectionAggregate {
    /**
     * @return Collection
     */
    public function asCollection();
}

class CollectionTools {
    public static function groupBy($collection, $fieldNameOrCallback) {
        $grouped = [];
        if (is_string($fieldNameOrCallback)) {
            $fieldName = $fieldNameOrCallback;
            foreach ($collection as $item) {
                $v = is_array($item) ? (isset($item[$fieldName]) ? $item[$fieldName] : null) : ($item->$fieldName);
                if (!isset($grouped[$v])) $grouped[$v] = [];
                $grouped[$v][] = $item;
            }
        } else {
            $callback = $fieldNameOrCallback;
            foreach ($collection as $item) {
                $key = call_user_func($callback, $item);
                if (!isset($grouped[$key])) $grouped[$key] = [];
                $grouped[$key][] = $item;
            }
        }
        return $grouped;
    }

    public static function indexBy($collection, $fieldNameOrCallback) {
        $indexed = [];
        if (is_string($fieldNameOrCallback)) {
            $fieldName = $fieldNameOrCallback;
            foreach ($collection as $item) {
                $key = is_array($item) ? (isset($item[$fieldName]) ? $item[$fieldName] : null) : ($item->$fieldName);
                $indexed[$key] = $item;
            }
        } else {
            $callback = $fieldNameOrCallback;
            foreach ($collection as $item) {
                $key = call_user_func($callback, $item);
                $indexed[$key] = $item;
            }
        }
        return $indexed;
    }

    public static function getProperty($collection, $propertyName) {
        foreach ($collection as $key => $item) {
            $v = is_array($item) ? (isset($item[$propertyName]) ? $item[$propertyName] : null) : ($item->$propertyName);
            yield $key => $v;
        }
    }

    public static function getPropertyArr($collection, $propertyName) {
        return self::asArray(self::getProperty($collection, $propertyName));
    }

    public static function createMap($collection, $indexPropertyName, $valuePropertyName) {
        $indexed = [];
        if (is_string($indexPropertyName)) {
            $fieldName = $indexPropertyName;
            foreach ($collection as $item) {
                $key = is_array($item) ? (isset($item[$fieldName]) ? $item[$fieldName] : null) : ($item->$fieldName);
                $indexed[$key] = $item[$valuePropertyName];
            }
        } else {
            $callback = $indexPropertyName;
            foreach ($collection as $item) {
                $key = call_user_func($callback, $item);
                $indexed[$key] = $item[$valuePropertyName];
            }
        }
        return $indexed;
    }

    public static function map($collection, callable $callback, $preserveKeys = true) {
        foreach ($collection as $key => $item) {
            if ($preserveKeys) {
                yield $key => $callback($item, $key);
            } else {
                yield $callback($item, $key);
            }
        }
    }

    public static function mapArr($collection, callable $callback, $preserveKeys = true) {
        return self::asArray(self::map($collection, $callback, $preserveKeys));
    }
    
    public static function filter($collection, callable $callback, $preserveKeys = false) {
        foreach ($collection as $key => $item) {
            if ($callback($item, $key)) {
                if ($preserveKeys) {
                    yield $key => $item;
                } else {
                    yield $item;
                }
            }
        }
    }
    
    public static function filterArr($collection, callable $callback, $preserveKeys = false) {
        return self::asArray(self::filter($collection, $callback, $preserveKeys));
    }
    
    public static function concat($collection1, $collection2, $preserveKeys = false) {
        foreach ($collection1 as $key => $val) {
            if ($preserveKeys) {
                yield $key => $val;
            } else {
                yield $val;
            }
        }
        foreach ($collection2 as $key => $val) {
            if ($preserveKeys) {
                yield $key => $val;
            } else {
                yield $val;
            }
        }        
    }

    public static function implode($delimiter, $collection) {
        if (is_array($collection)) return implode($delimiter, $collection);
        $imploded = '';
        foreach ($collection as $elem) {
            if ($imploded != '') $imploded .= $delimiter;
            $imploded .= $elem;
        }
        return $imploded;
    }

    public static function asArray($collection) {
        if ($collection instanceof Collection) return $collection->asArray();
        if (is_array($collection)) return $collection;
        return iterator_to_array($collection);
    }

    public static function collection($data) {
        if ($data instanceof Collection) return $data;
        if ($data instanceof ICollectionAggregate) return $data->asCollection();
        return new Collection($data);
    }
    
    public static function asCollection($data) {
        if ($data instanceof Collection) return $data;
        if ($data instanceof ICollectionAggregate) return $data->asCollection();
        return new Collection($data);        
    }
}



class Collection implements \IteratorAggregate, \Countable, \ArrayAccess {
    protected $data;
    
    public function __construct($data) {
        $this->data = $data;
    }

    public function isArray() {
        return is_array($this->data);
    }

    /**
     * 
     * @return array
     */
    public function asArray() {
        return CollectionTools::asArray($this->data);
    }
    
    /**
     * @return self
     */
    public function copy() {
        $newData = $this->data;
        if (!is_array($newData)) $newData = iterator_to_array($newData);
        return new self($newData);
    }

    /**
     * 
     * @return $this
     */    
    public function mapAll(callable $callback) {
        $this->data = $callback($this->data);
        return $this;    
    }

    /**
     * 
     * @return $this
     */
    public function map(callable $callback, $preserveKeys = true) {
        $newdata = CollectionTools::map($this->data, $callback, $preserveKeys);
        $this->data = $newdata;
        return $this;
        //return new self($newdata);
    }

    /**
     * 
     * @return $this
     */
    public function filter(callable $callback, $preserveKeys = false) {
        $newdata = CollectionTools::filter($this->data, $callback, $preserveKeys);
        $this->data = $newdata;
        return $this;
        //return new self($newdata);
    }

    /**
     * 
     * @return $this
     */    
    public function append($collection2, $preserveKeys = false) {
        $this->data = CollectionTools::concat($this->data, $collection2, $preserveKeys);
        return $this;
    }

    /**
     * @return string
     */
    public function join($delimiter) {
        return CollectionTools::implode($delimiter, $this->data);
    }

    /**
     * @return $this
     */
    public function getProperty($propertyName) {
        $newdata = CollectionTools::getProperty($this->data, $propertyName);
        $this->data = $newdata;
        return $this;
        //return new self($newdata);
    }

    /**
     * @return $this
     */
    public function indexBy($fieldNameOrCallback) {
        $newdata = CollectionTools::indexBy($this->data, $fieldNameOrCallback);
        $this->data = $newdata;
        return $this;
        //return new self($newdata);
    }

    /**
     * @return $this
     */
    public function createMap($indexPropertyName, $valuePropertyName) {
        $this->data = CollectionTools::createMap($this->data, $indexPropertyName, $valuePropertyName);
        return $this;
    }

    /**
     * @return $this
     */
    public function groupBy($fieldNameOrCallback) {
        $newdata = CollectionTools::groupBy($this->data, $fieldNameOrCallback);
        $this->data = $newdata;
        return $this;
        //return new self($newdata);
    }

    /**
     * @return IQuery
     */
    public function query($idFieldName = 'id') {
        $ds = new CollectionDataSource($this->data, $idFieldName);
        return $ds->query();
    }

    public function getIterator() {
        if ($this->data instanceof \Iterator) return $this->data;
        if ($this->data instanceof \IteratorAggregate) return $this->data->getIterator();
        return new \ArrayIterator($this->data);
    }

    public function count() {
        return count($this->data);
    }

    private function prepareDataForArrayAccess() {
        if (is_array($this->data)) return;
        if ($this->data instanceof \ArrayAccess) return;
        $this->data = CollectionTools::asArray($this->data);
    }


    public function get($key, $defaultValue = null) {
        if (!is_array($this->data)) $this->prepareDataForArrayAccess();
        if (is_array($this->data)) {
            return array_key_exists($key, $this->data) ? $this->data[$key] :$defaultValue;
        } elseif ($this->data instanceof \ArrayAccess) {
            return $this->data->offsetExists($key) ? $this->data->offsetGet($key) : $defaultValue;
        } else {
            throw new \Exception("PdQuery collection internal error - invalid inner data type");
        }
    }

    public function req($key) {
        if (!is_array($this->data)) $this->prepareDataForArrayAccess();
        if (is_array($this->data)) {
            if (!array_key_exists($key, $this->data)) {
                throw new \Exception("Required key $key not found in collection");
            }
            return $this->data[$key];
        } elseif ($this->data instanceof \ArrayAccess) {
            if (!$this->data->$this->offsetExists($key)) {
                throw new \Exception("Required key $key not found in collection");
            }
            return $this->data->offsetGet($key);
        } else {
            throw new \Exception("PdQuery collection internal error - invalid inner data type");
        }
    }

    public function offsetExists($offset) {
        if (!is_array($this->data)) $this->prepareDataForArrayAccess();
        if (is_array($this->data)) {
            return array_key_exists($offset, $this->data);
        } elseif ($this->data instanceof \ArrayAccess) {
            return $this->data->offsetExists($offset);
        } else {
            throw new \Exception("PdQuery collection internal error - invalid inner data type");
        }
    }

    public function offsetGet($offset) {
        if (!is_array($this->data)) $this->prepareDataForArrayAccess();
        if (is_array($this->data)) {
            return $this->data[$offset];
        } elseif ($this->data instanceof \ArrayAccess) {
            return $this->data->offsetGet($offset);
        } else {
            throw new \Exception("PdQuery collection internal error - invalid inner data type");
        }
    }

    public function offsetSet($offset, $value) {
        if (!is_array($this->data)) $this->prepareDataForArrayAccess();
        if (is_array($this->data)) {
            $this->data[$offset] = $value;
        } elseif ($this->data instanceof \ArrayAccess) {
            $this->data->offsetSet($offset, $value);
        } else {
            throw new \Exception("PdQuery collection internal error - invalid inner data type");
        }
    }

    public function offsetUnset($offset) {
        if (!is_array($this->data)) $this->prepareDataForArrayAccess();
        if (is_array($this->data)) {
            unset($this->data[$offset]);
        } elseif ($this->data instanceof \ArrayAccess) {
            $this->data->offsetUnset($offset);
        } else {
            throw new \Exception("PdQuery collection internal error - invalid inner data type");
        }
    }
}