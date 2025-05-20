<?php

namespace Icinga\Module\Eventtracker\Stream;

use Evenement\EventEmitterTrait;
use React\EventLoop\Loop;
use React\Stream\WritableStreamInterface;

class BufferedReader implements WritableStreamInterface
{
    use EventEmitterTrait;

    const NEWLINE = PHP_EOL;

    protected string $buffer = '';
    protected bool $writable = true;

    public function processBuffer()
    {
        $lastPos = 0;
        while (false !== ($pos = strpos($this->buffer, self::NEWLINE, $lastPos))) {
            $this->emit('line', [substr($this->buffer, $lastPos, $pos - $lastPos)]);
            $lastPos = $pos + 1;
        }
        if ($lastPos !== 0) {
            $this->buffer = substr($this->buffer, $lastPos);
        }
    }

    public function append($string)
    {
        $this->buffer .= $string;
        if (strpos($string, self::NEWLINE) !== false) {
            Loop::futureTick([$this, 'processBuffer']);
        }
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function write($data)
    {
        $this->append($data);
    }

    public function end($data = null)
    {
        if ($data !== null) {
            $this->append($data);
        }
        $this->writable = false;
        $this->close();
    }

    public function close()
    {
        $this->processBuffer();
        $this->buffer = '';
        $this->emit('close');
    }
}
