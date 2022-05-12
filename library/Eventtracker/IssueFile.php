<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Module\Eventtracker\Contract\File as FileObject;

class IssueFile
{
    protected static $tableName = 'issue_file';

    public static function getTableName()
    {
        return static::$tableName;
    }

    public static function persist(Issue $issue, FileObject $file, Db $db)
    {
        $db->insert(self::$tableName, [
            'issue_uuid'        => $issue->getUuid(),
            'file_checksum'     => $file->getChecksum(),
            'filename'          => $file->getName(),
            'filename_checksum' => hex2bin(sha1($file->getName())),
            'ctime'             => Time::unixMilli()
        ]);
    }
}
