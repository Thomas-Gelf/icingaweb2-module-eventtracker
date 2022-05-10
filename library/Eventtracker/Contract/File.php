<?php

namespace Icinga\Module\Eventtracker\Contract;

interface File
{
    /**
     * Get the SHA1 checksum of the file in binary format
     *
     * @return string
     */
    public function getChecksum(): string;

    /**
     * Get the file contents
     *
     * @return string
     */
    public function getData(): string;

    /**
     * Get the MIME type
     *
     * @return string
     */
    public function getMimeType(): string;

    /**
     * Get the name of the file
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the file size
     *
     * @return int
     */
    public function getSize(): int;
}
