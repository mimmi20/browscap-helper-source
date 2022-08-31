<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2022, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source\Helper;

use SplFileInfo;

use function realpath;

final class FilePath
{
    /** @throws void */
    public function getPath(SplFileInfo $file): string | null
    {
        $realpath = realpath($file->getPathname());

        if (false === $realpath) {
            return null;
        }

        switch ($file->getExtension()) {
            case 'gz':
                $path = 'compress.zlib://' . $realpath;

                break;
            case 'bz2':
                $path = 'compress.bzip2://' . $realpath;

                break;
            case 'tgz':
                $path = 'phar://' . $realpath;

                break;
            default:
                $path = $realpath;

                break;
        }

        return $path;
    }
}
