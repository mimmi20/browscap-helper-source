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
use JsonException;
use Override;
use Ramsey\Uuid\Uuid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;
use UaBrowserType\Type as ClientType;
use UaDeviceType\Type as DeviceType;
use UnexpectedValueException;

use function array_change_key_case;
use function assert;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_string;
use function json_decode;
use function mb_str_pad;
use function mb_strlen;
use function sprintf;
use function str_replace;

use const CASE_LOWER;
use const JSON_THROW_ON_ERROR;
use const PHP_EOL;
use const STR_PAD_RIGHT;

final class BrowserDetectorSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const string NAME = 'mimmi20/browser-detector';

    private const string PATH = 'vendor/mimmi20/browser-detector/tests/data';

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

        $files = new class ($iterator, 'json') extends FilterIterator {
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
            $pathName = $file->getPathname();
            $filepath = str_replace('\\', '/', $pathName);
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

            $content = file_get_contents($filepath);

            if ($content === false || $content === '' || $content === PHP_EOL) {
                continue;
            }

            try {
                $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->writeln('', OutputInterface::VERBOSITY_VERBOSE);
                $this->writeln(
                    '    <error>parsing file content [' . $filepath . '] failed</error>',
                    OutputInterface::VERBOSITY_NORMAL,
                );

                continue;
            }

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $test) {
                assert(is_array($test));

                if (!is_array($test['headers']) || !isset($test['headers']['user-agent'])) {
                    continue;
                }

                if ($test['headers']['user-agent'] === 'this is a fake ua to trigger the fallback') {
                    continue;
                }

                $uid = Uuid::uuid4()->toString();

                $deviceType = DeviceType::fromName($test['device']['type']);
                $clientType = ClientType::fromName($test['client']['type'] ?? $test['browser']['type']);

                yield $uid => [
                    'client' => [
                        'bits' => $test['client']['bits'] ?? $test['browser']['bits'] ?? null,
                        'isbot' => $clientType->isBot(),
                        'manufacturer' => $test['client']['manufacturer'] ?? $test['browser']['manufacturer'],
                        'modus' => $test['client']['modus'] ?? $test['browser']['modus'] ?? null,
                        'name' => $test['client']['name'] ?? $test['browser']['name'] ?? null,
                        'type' => $test['client']['type'] ?? $test['browser']['type'],
                        'version' => $test['client']['version'] ?? $test['browser']['version'] ?? null,
                    ],
                    'device' => [
                        'brand' => $test['device']['brand'],
                        'deviceName' => $test['device']['deviceName'],
                        'display' => [
                            'height' => $test['device']['display']['height'],
                            'size' => $test['device']['display']['size'],
                            'touch' => $test['device']['display']['touch'],
                            'type' => $test['device']['display']['type'] ?? null,
                            'width' => $test['device']['display']['width'],
                        ],
                        'dualOrientation' => $test['device']['dualOrientation'] ?? null,
                        'ismobile' => $deviceType->isMobile(),
                        'manufacturer' => $test['device']['manufacturer'],
                        'marketingName' => $test['device']['marketingName'],
                        'simCount' => $test['device']['simCount'] ?? null,
                        'type' => $test['device']['type'],
                    ],
                    'engine' => [
                        'manufacturer' => $test['engine']['manufacturer'],
                        'name' => $test['engine']['name'],
                        'version' => $test['engine']['version'],
                    ],
                    'file' => $filepath,
                    'headers' => array_change_key_case($test['headers'], CASE_LOWER),
                    'platform' => [
                        'bits' => $test['os']['bits'],
                        'manufacturer' => $test['os']['manufacturer'],
                        'marketingName' => $test['os']['marketingName'],
                        'name' => $test['os']['name'],
                        'version' => $test['os']['version'] ?? null,
                    ],
                    'raw' => $test,
                ];
            }
        }
    }
}
