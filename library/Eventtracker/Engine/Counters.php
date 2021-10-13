<?php

namespace Icinga\Module\Eventtracker\Engine;

class Counters
{
    /** @var array */
    protected $counters = [];

    /**
     * @param string $counterName
     * @param int $count
     */
    public function increment($counterName, $count = 1)
    {
        if (isset($this->counters[$counterName])) {
            $this->counters[$counterName] += $count;
        } else {
            $this->counters[$counterName] = $count;
        }
    }

    /**
     * @param string $counterName
     * @return int
     */
    public function get($counterName)
    {
        if (isset($this->counters[$counterName])) {
            return $this->counters[$counterName];
        }

        return 0;
    }

    /**
     * @return array
     */
    public function getAll()
    {
        return $this->counters;
    }

    /**
     * @param string $counterName
     * @return array
     */
    public function getAndReset($counterName)
    {
        if (isset($this->counters[$counterName])) {
            $value = $this->counters[$counterName];
            unset($this->counters[$counterName]);

            return $value;
        }
        return $this->counters;
    }

    /**
     * @return array
     */
    public function getAndResetAll()
    {
        $counters = $this->counters;
        $this->counters = [];

        return $counters;
    }

    /**
     * @param string $counterName
     */
    public function reset($counterName)
    {
        unset($this->counters[$counterName]);
    }

    public function listNames()
    {
        $names = array_keys($this->counters);
        sort($names);

        return $names;
    }

    public function calculateDiffFrom(Counters $counters)
    {
        $result = new Counters();
        foreach (array_unique(array_merge($this->listNames(), $counters->listNames())) as $name) {
            $diff = $this->get($name) - $counters->get($name);
            if ($diff !== 0) {
                $result->increment($name, $diff);
            }
        }

        return $result;
    }

    /**
     * @return Counters
     */
    public function snapshot()
    {
        return clone($this);
    }

    public function isEmpty()
    {
        return empty($this->counters);
    }

    public function renderSummary()
    {
        if ($this->isEmpty()) {
            return '-';
        }

        $parts = [];
        foreach ($this->counters as $name => $value) {
            $parts[] = "$name = $value";
        }

        return implode(', ', $parts);
    }
}
