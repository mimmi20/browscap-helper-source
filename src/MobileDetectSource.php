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

use BrowserDetector\Iterator\FilterIterator;
use Override;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UaResult\Result\Result;
use UnexpectedValueException;

use function assert;
use function file_exists;
use function is_int;
use function is_string;
use function mb_str_pad;
use function mb_strlen;
use function mb_trim;
use function sprintf;
use function str_replace;

use const STR_PAD_RIGHT;

final readonly class MobileDetectSource implements SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;

    private const string NAME = 'mobiledetect/mobiledetectlib';

    private const string PATH = 'vendor/mobiledetect/mobiledetectlib/tests/providers/vendors';

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
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<non-empty-string, array{headers: array<non-empty-string, non-empty-string>, result: Result, file: string|null, date-first: string|null, date-last: string|null, raw: mixed}>
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

        $files = new FilterIterator($iterator, 'php');

        foreach ($files as $file) {
            $filepath = $file->getPathname();
            $filepath = str_replace('\\', '/', $filepath);
            assert(is_string($filepath));

            $message = $parentMessage . sprintf('- reading file %s', $filepath);

            if (mb_strlen($message) > $messageLength) {
                $messageLength = mb_strlen($message);
            }

            $this->consoleLogger->info(
                mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT),
            );

            $provider = include $filepath;

            foreach ($provider as $vendorData) {
                foreach ($vendorData as $ua => $testData) {
                    if (is_int($ua)) {
                        continue;
                    }

                    $agent = mb_trim((string) $ua);

                    if ($agent === '') {
                        continue;
                    }

                    $uid = Uuid::uuid4()->toString();

                    yield $uid => [
                        'headers' => ['user-agent' => $agent],
                        'result' => $this->buildResult(
                            consoleLogger: $this->consoleLogger,
                            data: [
                                'client' => [
                                    'bits' => null,
                                    'isbot' => null,
                                    'manufacturer' => null,
                                    'modus' => null,
                                    'name' => null,
                                    'type' => null,
                                    'version' => null,
                                ],
                                'device' => [
                                    'brand' => null,
                                    'deviceName' => $testData['model'] ?? null,
                                    'display' => [
                                        'height' => null,
                                        'size' => null,
                                        'touch' => null,
                                        'width' => null,
                                    ],
                                    'dualOrientation' => null,
                                    'ismobile' => isset($testData['isMobile']) && $testData['isMobile'],
                                    'manufacturer' => null,
                                    'marketingName' => null,
                                    'simCount' => null,
                                    'type' => null,
                                ],
                                'engine' => [
                                    'manufacturer' => null,
                                    'name' => null,
                                    'version' => null,
                                ],
                                'os' => [
                                    'bits' => null,
                                    'manufacturer' => null,
                                    'marketingName' => null,
                                    'name' => null,
                                    'version' => null,
                                ],
                            ],
                            headers: ['user-agent' => $agent],
                        ),
                        'file' => $filepath,
                        'date-first' => null,
                        'date-last' => null,
                        'raw' => $testData,
                    ];
                }
            }
        }
    }
}
