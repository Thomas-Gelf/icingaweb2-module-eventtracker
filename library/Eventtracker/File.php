<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as Db;
use Icinga\Module\Eventtracker\Contract\File as FileObject;

class File
{
    use PropertyHelpers;

    protected static $tableName = 'file';

    protected $properties = [
        'checksum'  => null,
        'data'      => null,
        'size'      => null,
        'mime_type' => null,
        'ctime'     => null
    ];

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

    public static function loadAllByIssue(Issue $issue, Db $db): array
    {
        $files = [];

        $q = $db
            ->select()
            ->from(['f' => static::$tableName])
            ->joinInner(
                ['i' => IssueFile::getTableName()], 'f.checksum = i.file_checksum', ['filename', 'issue_uuid']
            )
            ->where('i.issue_uuid = ?', $issue->getUuid());

        foreach ($db->fetchAll($q) as $row) {
            $file = new static;
            $file->properties['issue_uuid'] = null;
            $file->properties['filename'] = null;
            $file->setProperties($row);
            $file->setStored();
            $files[] = $file;
        }

        return $files;
    }

    public static function loadAllBySetOfIssues(SetOfIssues $issues, Db $db): array
    {
        $files = [];

        $q = $db
            ->select()
            ->from(['f' => static::$tableName])
            ->joinInner(
                ['i' => IssueFile::getTableName()], 'f.checksum = i.file_checksum', ['filename', 'issue_uuid']
            )
            ->where('i.issue_uuid IN (?)', $issues->getUuids());

        foreach ($db->fetchAll($q) as $row) {
            $file = new static;
            $file->properties['issue_uuid'] = null;
            $file->properties['filename'] = null;
            $file->setProperties($row);
            $file->setStored();
            $files[] = $file;
        }

        return $files;
    }

    public static function loadByIssueUuidsAndChecksums(array $uuids, array $checksums, Db $db): array
    {
        $files = [];

        $q = $db
            ->select()
            ->from(['f' => static::$tableName])
            ->joinInner(
                ['i' => IssueFile::getTableName()], 'f.checksum = i.file_checksum', ['filename', 'issue_uuid']
            )
            ->where('i.issue_uuid IN (?) AND f.checksum IN (?)', [$uuids, $checksums]);

        foreach ($db->fetchAll($q) as $row) {
            $file = new static;
            $file->properties['issue_uuid'] = null;
            $file->properties['filename'] = null;
            $file->setProperties($row);
            $file->setStored();
            $files[] = $file;
        }

        return $files;
    }

    public static function loadByIssueUuidAndChecksum(string $uuid, string $checksum, string $filenameChecksum, Db $db): ?self
    {
        $q = $db
            ->select()
            ->from(['f' => static::$tableName])
            ->joinInner(
                ['i' => IssueFile::getTableName()], 'f.checksum = i.file_checksum', ['filename', 'issue_uuid']
            )
            ->where('i.issue_uuid = ?', $uuid)
            ->where('i.file_checksum = ?', $checksum)
            ->where('i.filename_checksum = ?', $filenameChecksum);

        $row = $db->fetchRow($q);
        if ($row === false) {
            return null;
        }

        $file = new static;
        $file->properties['issue_uuid'] = null;
        $file->properties['filename'] = null;
        $file->setProperties($row);
        $file->setStored();

        return $file;
    }
}
