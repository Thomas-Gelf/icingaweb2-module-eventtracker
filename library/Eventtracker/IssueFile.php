<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Module\Eventtracker\Contract\File as FileObject;
use Ramsey\Uuid\UuidInterface;

class IssueFile
{
    protected static $tableName = 'issue_file';

    public static function getTableName()
    {
        return static::$tableName;
    }

    public static function persist(UuidInterface $issueUuid, FileObject $file, Db $db)
    {
        $db->insert(self::$tableName, [
            'issue_uuid'        => $issueUuid->getBytes(),
            'file_checksum'     => $file->getChecksum(),
            'filename'          => $file->getName(),
            'filename_checksum' => hex2bin(sha1($file->getName())),
            'ctime'             => Time::unixMilli()
        ]);
    }
}
