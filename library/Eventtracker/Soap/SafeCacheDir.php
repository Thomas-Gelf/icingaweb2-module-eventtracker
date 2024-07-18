<?php

namespace Icinga\Module\Eventtracker\Soap;

use RuntimeException;

class SafeCacheDir
{
    protected const TEMP_DIR_PREFIX = 'eventtrackerSoap-';

    protected static $currentUser;

    public static function getDirectory(): string
    {
        $directory = sprintf(
            '%s/%s%s',
            sys_get_temp_dir(),
            self::TEMP_DIR_PREFIX,
            static::getCurrentUsername()
        );

        static::claimDirectory($directory);

        return $directory;
    }

    public static function getSubDirectory(string $directory): string
    {
        $subDir = static::getDirectory() . "/$directory";
        static::claimDirectory($subDir);

        return $subDir;
    }

    protected static function claimDirectory(string $directory): void
    {
        if (file_exists($directory)) {
            if (static::uidToName(fileowner($directory)) !== static::getCurrentUsername()) {
                throw new RuntimeException(sprintf(
                    '%s exists, but does not belong to %s',
                    $directory,
                    static::getCurrentUsername()
                ));
            }
        } else {
            if (! @mkdir($directory, 0700)) {
                throw new RuntimeException(sprintf(
                    'Could not create %s',
                    $directory
                ));
            }
        }
    }

    protected static function getCurrentUsername(): string
    {
        if (static::$currentUser === null) {
            if (function_exists('posix_geteuid')) {
                static::$currentUser = static::uidToName(posix_geteuid());
            } else {
                throw new RuntimeException(
                    'POSIX methods not available, is php-posix installed and enabled?'
                );
            }
        }

        return static::$currentUser;
    }

    protected static function uidToName(int $uid): string
    {
        $info = posix_getpwuid($uid);
        return $info['name'];
    }
}
