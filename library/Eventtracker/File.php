<?php

namespace Icinga\Module\Eventtracker;

use gipfl\ZfDb\Adapter\Adapter as Db;
use gipfl\ZfDb\Select;
use Icinga\Module\Eventtracker\Contract\File as FileObject;
use Ramsey\Uuid\UuidInterface;

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

    public static function exists(FileObject $file, Db $db): bool
    {
        $result = (int) $db->fetchOne(
            $db->select()
                ->from(self::$tableName, 'COUNT(*)')
                ->where('checksum = ?', $file->getChecksum())
        );

        return $result > 0;
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
        return static::fetchFiles($db, static::selectFiles($db)->where('i.issue_uuid = ?', $issue->getUuid()));
    }

    public static function loadAllBySetOfIssues(SetOfIssues $issues, Db $db): array
    {
        return static::fetchFiles($db, static::selectFiles($db)->where('i.issue_uuid IN (?)', $issues->getUuids()));
    }

    public static function loadByIssueUuidsAndChecksums(array $uuids, array $checksums, Db $db): array
    {
        return static::fetchFiles(
            $db,
            static::selectFiles($db)->where('i.issue_uuid IN (?) AND f.checksum IN (?)', [$uuids, $checksums])
        );
    }

    public static function loadByIssueUuidAndChecksum(
        UuidInterface $uuid,
        string $checksum,
        string $filenameChecksum,
        Db $db
    ): ?self {
        $row = $db->fetchRow(
            static::selectFiles($db)
            ->where('i.issue_uuid = ?', $uuid->getBytes())
            ->where('i.file_checksum = ?', $checksum)
            ->where('i.filename_checksum = ?', $filenameChecksum)
        );
        if ($row === false) {
            return null;
        }

        return static::createFileFromRow($row);
    }

    protected static function selectFiles(Db $db): Select
    {
        return $db->select()
            ->from(['f' => static::$tableName])
            ->joinInner(['i' => IssueFile::getTableName()], 'f.checksum = i.file_checksum', ['filename', 'issue_uuid'])
            ->order('i.ctime');
    }

    protected static function createFileFromRow($row): self
    {
        $file = new static;
        $file->properties['issue_uuid'] = null;
        $file->properties['filename'] = null;
        $file->setProperties($row);
        $file->setStored();

        return $file;
    }

    protected static function fetchFiles(Db $db, $q): array
    {
        $files = [];
        foreach ($db->fetchAll($q) as $row) {
            $files[] = static::createFileFromRow($row);
        }

        return $files;
    }
}
