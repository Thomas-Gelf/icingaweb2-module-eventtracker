<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Module\Eventtracker\Contract\File as FileObject;

class File
{
    protected static $tableName = 'file';

    public static function exists(FileObject $file, Db $db)
    {
        $result = $db->fetchOne(
            $db->select()
                ->from(self::$tableName, 'COUNT(*)')
                ->where('checksum = ?', $file->getChecksum())
        );

        return $result;
    }

    public static function persist(FileObject $file, Db $db)
    {
        $db->insert(self::$tableName, [
            'checksum'  => $file->getChecksum(),
            'data'      => $file->getData(),
            'size'      => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'ctime'     => Time::unixMilli()
        ]);
    }
}
