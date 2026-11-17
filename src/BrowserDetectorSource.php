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
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use UnexpectedValueException;

use function array_change_key_case;
use function assert;
use function date;
use function file_exists;
use function is_array;
use function is_string;
use function mb_str_pad;
use function mb_strlen;
use function sprintf;
use function str_replace;

use const CASE_LOWER;
use const STR_PAD_RIGHT;

final readonly class BrowserDetectorSource implements SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;

    private const string NAME = 'mimmi20/browser-detector';

    private const string PATH = 'vendor/mimmi20/browser-detector/tests/data';

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

        $files = new FilterIterator($iterator, 'yaml');

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

            try {
                $data = Yaml::parseFile($filepath);
            } catch (ParseException $e) {
                throw new Exception\SourceException($e->getMessage(), 0, $e);
            }

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $test) {
                /** @var array{headers: array<non-empty-string, non-empty-string>, device: array{architecture: string|null, deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, dualOrientation: bool|null, simCount: int|null, display: array{width: int|null, height: int|null, touch: bool|null, size: float|null, type?: string|null}, type: string|null, ismobile: bool, istv: bool, bits: int|null}, os: array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}, client: array{name: string|null, version: string|null, manufacturer: string|null, type: string|null, isbot: bool, bits: int|null, modus: string|null}, engine: array{name: string|null, version: string|null, manufacturer: string|null}} $test */
                if (!is_array($test)) {
                    continue;
                }

                if (!is_array($test['headers']) || !isset($test['headers']['user-agent'])) {
                    continue;
                }

                if ($test['headers']['user-agent'] === 'this is a fake ua to trigger the fallback') {
                    continue;
                }

                $uid = Uuid::uuid4()->toString();

                yield $uid => new TestProperties(
                    headers: array_change_key_case($test['headers'], CASE_LOWER),
                    result: $this->buildResult(
                        consoleLogger: $this->consoleLogger,
                        data: $test,
                        headers: array_change_key_case($test['headers'], CASE_LOWER),
                    ),
                    file: $filepath,
                    dateFirst: date('Y-m-d'),
                    dateLast: date('Y-m-d'),
                    raw: $test,
                );
            }
        }
    }
}
