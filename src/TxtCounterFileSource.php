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
use function explode;
use function fclose;
use function feof;
use function fgets;
use function file_exists;
use function fopen;
use function is_string;
use function mb_str_pad;
use function mb_strlen;
use function mb_trim;
use function sprintf;
use function str_replace;

use const STR_PAD_RIGHT;

final readonly class TxtCounterFileSource implements SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;

    private const string NAME = 'ctxt-files';

    /** @throws void */
    public function __construct(private LoggerInterface $consoleLogger, private string $dir)
    {
        // do nothing
    }

    /** @throws void */
    #[Override]
    public function isReady(string $parentMessage): bool
    {
        if (file_exists($this->dir)) {
            return true;
        }

        $this->consoleLogger->error(
            $parentMessage . sprintf('- path %s not found', $this->dir),
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
        $message = $parentMessage . sprintf('- reading path %s', $this->dir);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->consoleLogger->info(
            mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT),
        );

        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir));
        } catch (UnexpectedValueException $e) {
            throw new Exception\SourceException($e->getMessage(), 0, $e);
        }

        $files = new FilterIterator($iterator, 'ctxt');

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

            $handle = @fopen($filepath, 'r');

            if ($handle === false) {
                $this->consoleLogger->error('reading file ' . $filepath . ' caused an error');

                continue;
            }

            while (!feof($handle)) {
                $line = fgets($handle, 65535);

                if ($line === false) {
                    continue;
                }

                $line = mb_trim($line);

                if ($line === '') {
                    continue;
                }

                $parts = explode(' ', $line, 2);

                if (empty($parts[1])) {
                    continue;
                }

                $uid = Uuid::uuid4()->toString();

                yield $uid => new TestProperties(
                    headers: ['user-agent' => $parts[1]],
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
                        'ismobile' => null,
                        'istv' => null,
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
                        'type' => null,
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
                    raw: $parts,
                );
            }

            fclose($handle);
        }
    }
}
