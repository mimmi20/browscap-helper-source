<?php

/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2025, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use FilterIterator;
use Iterator;
use Override;
use Ramsey\Uuid\Uuid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

use function assert;
use function file_exists;
use function is_int;
use function is_string;
use function mb_str_pad;
use function mb_strlen;
use function sprintf;
use function str_replace;
use function trim;

use const STR_PAD_RIGHT;

final class MobileDetectSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const string NAME = 'mobiledetect/mobiledetectlib';

    private const string PATH = 'vendor/mobiledetect/mobiledetectlib/tests/providers/vendors';

    /** @throws void */
    #[Override]
    public function isReady(string $parentMessage): bool
    {
        if (file_exists(self::PATH)) {
            return true;
        }

        $this->writeln(
            "\r" . '<error>' . $parentMessage . sprintf('- path %s not found</error>', self::PATH),
            OutputInterface::VERBOSITY_NORMAL,
        );

        return false;
    }

    /**
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<non-empty-string, array{headers: array<non-empty-string, non-empty-string>, device: array{deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, display: array{width: int|null, height: int|null, touch: bool|null, type: string|null, size: float|int|null}, type: string|null, ismobile: bool|null}, client: array{name: string|null, modus: string|null, version: string|null, manufacturer: string|null, bits: int|null, type: string|null, isbot: bool|null}, platform: array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}, engine: array{name: string|null, version: string|null, manufacturer: string|null}}>
     *
     * @throws SourceException
     */
    #[Override]
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable
    {
        $message = $parentMessage . sprintf('- reading path %s', self::PATH);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write(
            "\r" . '<info>' . mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
            false,
            OutputInterface::VERBOSITY_VERBOSE,
        );

        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::PATH));
        } catch (UnexpectedValueException $e) {
            throw new SourceException($e->getMessage(), 0, $e);
        }

        $files = new class ($iterator, 'php') extends FilterIterator {
            /**
             * @param Iterator<SplFileInfo> $iterator
             *
             * @throws void
             */
            public function __construct(Iterator $iterator, private readonly string $extension)
            {
                parent::__construct($iterator);
            }

            /** @throws void */
            #[Override]
            public function accept(): bool
            {
                $file = $this->getInnerIterator()->current();

                assert($file instanceof SplFileInfo);

                return $file->isFile() && $file->getExtension() === $this->extension;
            }
        };

        foreach ($files as $file) {
            assert($file instanceof SplFileInfo);
            $filepath = $file->getPathname();
            $filepath = str_replace('\\', '/', $filepath);
            assert(is_string($filepath));

            $message = $parentMessage . sprintf('- reading file %s', $filepath);

            if (mb_strlen($message) > $messageLength) {
                $messageLength = mb_strlen($message);
            }

            $this->write(
                "\r" . '<info>' . mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
                false,
                OutputInterface::VERBOSITY_VERY_VERBOSE,
            );

            $provider = include $filepath;

            foreach ($provider as $vendorData) {
                foreach ($vendorData as $ua => $testData) {
                    if (is_int($ua)) {
                        continue;
                    }

                    $agent = trim((string) $ua);

                    if ($agent === '') {
                        continue;
                    }

                    $uid = Uuid::uuid4()->toString();

                    yield $uid => [
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
                                'type' => null,
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
                        'file' => $filepath,
                        'headers' => ['user-agent' => $agent],
                        'platform' => [
                            'bits' => null,
                            'manufacturer' => null,
                            'marketingName' => null,
                            'name' => null,
                            'version' => null,
                        ],
                        'raw' => $testData,
                    ];
                }
            }
        }
    }
}
