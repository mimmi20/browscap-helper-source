<?php

/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2026, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use BrowscapHelper\Source\Entity\TestProperties;
use BrowserDetector\Iterator\FilterIterator;
use Override;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;

use function assert;
use function file;
use function file_exists;
use function is_string;
use function mb_str_pad;
use function mb_strlen;
use function sprintf;
use function str_replace;

use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;
use const STR_PAD_RIGHT;

final readonly class CrawlerDetectSource implements SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;

    private const string NAME = 'jaybizzle/crawler-detect';

    private const string PATH = 'vendor/jaybizzle/crawler-detect/tests';

    /** @throws void */
    public function __construct(private LoggerInterface $consoleLogger)
    {
        // do nothing
    }

    /** @throws void */
    #[Override]
    public function isReady(string $parentMessage): bool
    {
        if (file_exists(self::PATH)) {
            return true;
        }

        $this->consoleLogger->error(
            $parentMessage . sprintf('- path %s not found', self::PATH),
        );

        return false;
    }

    /**
     * @return iterable<non-empty-string, TestProperties>
     *
     * @throws Exception\SourceException
     */
    #[Override]
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable
    {
        $message = $parentMessage . sprintf('- reading path %s', self::PATH);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->consoleLogger->info(
            mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT),
        );

        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::PATH));
        } catch (UnexpectedValueException $e) {
            throw new Exception\SourceException($e->getMessage(), 0, $e);
        }

        $files = new FilterIterator($iterator, 'txt');

        foreach ($files as $file) {
            $pathName = $file->getPathname();
            $filepath = str_replace('\\', '/', $pathName);
            assert(is_string($filepath));

            $message = $parentMessage . sprintf('- reading file %s', $filepath);

            if (mb_strlen($message) > $messageLength) {
                $messageLength = mb_strlen($message);
            }

            $this->consoleLogger->info(
                mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT),
            );

            $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }

                $uid        = Uuid::uuid4()->toString();
                $isBot      = $file->getBasename('.txt') === 'crawlers';
                $headerName = $file->getPathInfo()?->getFilename() === 'sec_ch_ua'
                    ? 'sec-ch-ua'
                    : 'user-agent';

                yield $uid => new TestProperties(
                    headers: [$headerName => $line],
                    device: [
                        'architecture' => null,
                        'brand' => null,
                        'deviceName' => null,
                        'display' => [
                            'height' => null,
                            'size' => null,
                            'touch' => null,
                            'width' => null,
                        ],
                        'dualOrientation' => null,
                        'manufacturer' => null,
                        'marketingName' => null,
                        'simCount' => null,
                        'type' => null,
                        'bits' => null,
                    ],
                    os: [
                        'bits' => null,
                        'manufacturer' => null,
                        'marketingName' => null,
                        'name' => null,
                        'version' => null,
                    ],
                    client: [
                        'bits' => null,
                        'manufacturer' => null,
                        'modus' => null,
                        'name' => null,
                        'type' => $isBot ? 'crawler' : null,
                        'version' => null,
                    ],
                    engine: [
                        'manufacturer' => null,
                        'name' => null,
                        'version' => null,
                    ],
                    file: $filepath,
                    dateFirst: null,
                    dateLast: null,
                    raw: $line,
                    sources: [
                        $this->getName() => [
                            'date-first' => null,
                            'date-last' => null,
                        ],
                    ],
                );
            }
        }
    }
}
