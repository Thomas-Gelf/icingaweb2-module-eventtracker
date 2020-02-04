<?php

namespace Icinga\Module\Eventtracker;

use InvalidArgumentException;

class MSendCommandLine
{
    protected $arguments;

    protected $slotValues;

    public function __construct($cmd)
    {
        $this->parseCommand($cmd);
        $this->parseSlotValues();
    }

    /**
     * @param SenderInventory $senders
     * @param ObjectClassInventory $classes
     * @return Event
     * @throws \Exception
     */
    public function getEvent(SenderInventory $senders, ObjectClassInventory $classes)
    {
        $timeout = $this->getSlotValue('mc_timeout');
        if (strlen($timeout) > 0) {
            if (! ctype_digit($timeout)) {
                throw new InvalidArgumentException("mc_timeout=$timeout is not a number");
            }
        } else {
            $timeout = null;
        }
        $event = new Event();
        $event->setProperties([
            'host_name'     => $this->getRequiredSlotValue('mc_host'),
            'object_name'   => $this->getRequiredSlotValue('mc_object'),
            'object_class'  => $classes->requireClass($this->getRequiredSlotValue('mc_object_class')),
            'severity'      => $this->getSeverity(),
            'priority'      => $this->getPriority('normal'),
            'message'       => $this->getMessage(),
            'event_timeout' => $timeout,
            'sender_event_id' => $this->getSlotValue('mc_tool_key', ''),
            'sender_id'       => $senders->getSenderId(
                $this->getSlotValue('mc_tool', 'no-tool'),
                $this->getSlotValue('mc_tool_class', 'NO-CLASS')
                // $this->getRequiredSlotValue('mc_tool'),
                // $this->getRequiredSlotValue('mc_tool_class')
            )
        ]);

        return $event;
    }

    public function getSeverity()
    {
        if ($this->hasSlotValue('severity')) {
            return $this->getSlotValue('severity');
        } elseif ($this->hasArgument('-r')) {
            return $this->getArgument('-r');
        } else {
            throw new InvalidArgumentException('Got no severity');
        }
    }

    public function getPriority($default = null)
    {
        return $this->getSlotValue('mc_priority', $default);
    }

    public function getMessage()
    {
        if ($this->hasSlotValue('msg')) {
            return $this->getSlotValue('msg');
        } elseif ($this->hasArgument('-m')) {
            return $this->getArgument('-m');
        } else {
            throw new InvalidArgumentException('Got no message');
        }
    }

    public function hasArgument($name)
    {
        return array_key_exists($name, $this->arguments);
    }

    public function getArgument($name, $default = null)
    {
        if ($this->hasArgument($name)) {
            return $this->arguments[$name];
        } else {
            return $default;
        }
    }

    public function getRequiredArgument($name)
    {
        $value = $this->getArgument($name);
        if ($value === null) {
            throw new InvalidArgumentException("Argument '$name' is required");
        }

        return $value;
    }

    public function getArguments()
    {
        return $this->arguments;
    }

    public function hasSlotValue($name)
    {
        return array_key_exists($name, $this->slotValues);
    }

    public function getSlotValue($name, $default = null)
    {
        if ($this->hasSlotValue($name)) {
            return $this->slotValues[$name];
        } else {
            return $default;
        }
    }

    public function getRequiredSlotValue($name)
    {
        $value = $this->getSlotValue($name);
        if ($value === null) {
            throw new InvalidArgumentException("Slot value '$name' is required");
        }

        return $value;
    }

    public function getSlotValues()
    {
        return $this->slotValues;
    }

    protected function parseCommand($cmd)
    {
        $parts = $this->split($cmd);
        if (empty($parts)) {
            throw new InvalidArgumentException('Unable to parse msend command: ' . $cmd);
        }

        if (substr($parts[0], -5) === 'msend') {
            array_shift($parts);
        }

        $argumentCount = count($parts);
        if ($argumentCount % 2 !== 0) {
            throw new InvalidArgumentException('Argument count is not even: ' . $cmd);
        }

        $args = [];
        for ($i = 0; $i < $argumentCount; $i += 2) {
            $args[$parts[$i]] = $parts[$i + 1];
        }

        $this->arguments = $args;
    }

    protected function parseSlotValues()
    {
        $param = $this->getArgument('-b');
        $parts = preg_split('/(?<!\\\);/', $param, -1, PREG_SPLIT_NO_EMPTY);
        $values = [];
        foreach ($parts as $part) {
            if (strpos($part, '=') === false) {
                throw new InvalidArgumentException('Cannot split slot value: ' . $part);
            }
            list($key, $val) = preg_split('/=/', $part, 2);
            $values[$key] = $val;
        }

        $this->slotValues = $values;
    }

    protected function unEscapeSlotArgument($value)
    {
        return stripcslashes(str_replace(
            ['\a', '\b', '\f', '\n', '\r', '\t', '\v'],
            ["\a", "\b", "\f", "\n", "\r", "\t", "\v"],
            $value
        ));
    }

    /**
     * Shamelessly stolen from clue/php-arguments, will be added as a dependency
     * to our reactbundle
     *
     * Splits the given command line string into an array of command arguments
     *
     * @param string $command command line string
     * @return string[] array of command line argument strings
     * @throws \RuntimeException
     */
    protected function split($command)
    {
        // whitespace characters count as argument separators
        static $ws = array(
            ' ',
            "\r",
            "\n",
            "\t",
            "\v",
        );

        $i = 0;
        $args = array();

        while (true) {
            // skip all whitespace characters
            // @codingStandardsIgnoreStart
            for (;isset($command[$i]) && in_array($command[$i], $ws); ++$i);
            // @codingStandardsIgnoreEnd

            // command string ended
            if (!isset($command[$i])) {
                break;
            }

            $inQuote = null;
            $quotePosition = 0;
            $argument = '';
            $part = '';

            // read a single argument
            for (; isset($command[$i]); ++$i) {
                $c = $command[$i];

                if ($inQuote === "'") {
                    // we're within a 'single quoted' string
                    if (
                        $c === '\\'
                        && isset($command[$i + 1])
                        && ($command[$i + 1] === "'" || $command[$i + 1] === '\\')
                    ) {
                        // escaped single quote or backslash ends up as char in argument
                        $part .= $command[++$i];
                        continue;
                    } elseif ($c === "'") {
                        // single quote ends
                        $inQuote = null;
                        $argument .= $part;
                        $part = '';
                        continue;
                    }
                } else {
                    // we're not within any quotes or within a "double quoted" string
                    if ($c === '\\' && isset($command[$i + 1])) {
                        if ($command[$i + 1] === 'u') {
                            // this looks like a unicode escape sequence
                            // use JSON parser to interpret this
                            $c = json_decode('"' . substr($command, $i, 6) . '"');
                            if ($c !== null) {
                                // on success => use interpreted and skip sequence
                                $argument .= stripcslashes($part) . $c;
                                $part = '';
                                $i += 5;
                                continue;
                            }
                        }

                        // escaped characters will be interpreted when part is complete
                        $part .= $command[$i] . $command[$i + 1];
                        ++$i;
                        continue;
                    } elseif ($inQuote === '"' && $c === '"') {
                        // double quote ends
                        $inQuote = null;

                        // previous double quoted part should be interpreted
                        $argument .= stripcslashes($part);
                        $part = '';
                        continue;
                    } elseif ($inQuote === null && ($c === '"' || $c === "'")) {
                        // start of quotes found
                        $inQuote = $c;
                        $quotePosition = $i;

                        // previous unquoted part should be interpreted
                        $argument .= stripcslashes($part);
                        $part = '';
                        continue;
                    } elseif ($inQuote === null && in_array($c, $ws)) {
                        // whitespace character terminates unquoted argument
                        break;
                    }
                }

                $part .= $c;
            }

            // end of argument reached. Still in quotes is a parse error.
            if ($inQuote !== null) {
                throw new \RuntimeException($inQuote, $quotePosition);
            }

            // add remaining part to current argument
            if ($part !== '') {
                $argument .= stripcslashes($part);
            }

            $args[] = $argument;
        }

        return $args;
    }
}
