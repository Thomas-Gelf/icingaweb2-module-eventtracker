<?php

namespace Icinga\Module\Eventtracker;

use gipfl\Json\JsonSerialization;
use Icinga\Module\Eventtracker\Contract\File;
use InvalidArgumentException;
use RuntimeException;

class FrozenMemoryFile implements File, JsonSerialization
{
    /** @var string */
    protected $checksum;

    /** @var string */
    protected $data;

    /** @var resource */
    protected $f;

    /** @var string */
    protected $filename;

    /** @var string */
    protected $mimeType;

    /** @var int */
    protected $size;

    /** @var array */
    protected $stat;

    /**
     * Create a new frozen memory file from binary data encoded with base64
     *
     * @param string $filename
     * @param string $data
     *
     * @return static
     *
     * @throws RuntimeException If the initial opening or writing to the memory file fails
     */
    public static function fromBinary(string $filename, string $data): self
    {
        $f = fopen('php://memory', 'w+');
        if ($f === false) {
            // TODO(lippserd): Is it even possible for fopen("php://memory") to fail?
            throw new RuntimeException("Can't open php://memory stream");
        }

        if (fwrite($f, $data) === false) {
            throw new RuntimeException("Can't write data to memory file");
        }

        $mimeType = mime_content_type($f);
        if ($mimeType === false) {
            throw new RuntimeException("Can't get MIME type");
        }

        fclose($f);

        $file = new static;
        $file->checksum = sha1($data, true);
        $file->data = $data;
        $file->filename = $filename;
        $file->mimeType = $mimeType;
        $file->size = mb_strlen($data);

        return $file;
    }

    /**
     * Create a new frozen memory file from binary data encoded with base64
     *
     * @param string $filename
     * @param string $data
     *
     * @return static
     *
     * @throws InvalidArgumentException If decoding fails
     * @throws RuntimeException If the initial opening or writing to the memory file fails
     */
    public static function fromBase64($filename, $data): self
    {
        $decoded = base64_decode($data);
        if ($decoded === false) {
            throw new InvalidArgumentException("Can't decode base64");
        }

        return static::fromBinary($filename, $decoded);
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getName(): string
    {
        return $this->filename;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public static function fromSerialization($any): File
    {
        return static::fromBase64($any->name, $any->content_base64);
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'name' => $this->getName(),
            'content_base64' => base64_encode($this->data),
        ];
    }
}
