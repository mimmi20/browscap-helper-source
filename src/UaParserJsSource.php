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

namespace BrowscapHelper\Source;

use Exception;
use FilterIterator;
use Iterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;

use function assert;
use function file_exists;
use function file_get_contents;
use function is_array;
use function json_decode;
use function mb_strlen;
use function sprintf;
use function str_pad;
use function trim;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;
use const STR_PAD_RIGHT;

final class UaParserJsSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const NAME = 'ua-parser-js';
    private const PATH = 'node_modules/ua-parser-js/test';

    /**
     * @throws void
     */
    public function isReady(string $parentMessage): bool
    {
        if (file_exists(self::PATH)) {
            return true;
        }

        $this->writeln("\r" . '<error>' . $parentMessage . sprintf('- path %s not found</error>', self::PATH), OutputInterface::VERBOSITY_NORMAL);

        return false;
    }

    /**
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<array{headers: array<non-empty-string, non-empty-string>, device: array{deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, display: array{width: int|null, height: int|null, touch: bool|null, type: string|null, size: float|int|null}, type: string|null, ismobile: bool|null}, client: array{name: string|null, modus: string|null, version: string|null, manufacturer: string|null, bits: int|null, type: string|null, isbot: bool|null}, platform: array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}, engine: array{name: string|null, version: string|null, manufacturer: string|null}}>
     *
     * @throws RuntimeException
     */
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable
    {
        $agents = [];
        $base   = [
            'headers' => ['user-agent' => null],
            'device' => [
                'deviceName' => null,
                'marketingName' => null,
                'manufacturer' => null,
                'brand' => null,
                'display' => [
                    'width' => null,
                    'height' => null,
                    'touch' => null,
                    'type' => null,
                    'size' => null,
                ],
                'dualOrientation' => null,
                'type' => null,
                'simCount' => null,
                'ismobile' => null,
            ],
            'client' => [
                'name' => null,
                'modus' => null,
                'version' => null,
                'manufacturer' => null,
                'bits' => null,
                'type' => null,
                'isbot' => null,
            ],
            'platform' => [
                'name' => null,
                'marketingName' => null,
                'version' => null,
                'manufacturer' => null,
                'bits' => null,
            ],
            'engine' => [
                'name' => null,
                'version' => null,
                'manufacturer' => null,
            ],
            'raw' => [],
            'file' => [],
        ];

        $message = $parentMessage . sprintf('- reading path %s', self::PATH);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write("\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>', false, OutputInterface::VERBOSITY_VERBOSE);

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::PATH));
        $files    = new class ($iterator, 'json') extends FilterIterator {
            private string $extension;

            /**
             * @param Iterator<SplFileInfo> $iterator
             */
            public function __construct(Iterator $iterator, string $extension)
            {
                parent::__construct($iterator);
                $this->extension = $extension;
            }

            public function accept(): bool
            {
                $file = $this->getInnerIterator()->current();

                assert($file instanceof SplFileInfo);

                return $file->isFile() && $file->getExtension() === $this->extension;
            }
        };

        foreach ($files as $file) {
            /** @var SplFileInfo $file */
            $filepath = $file->getPathname();

            $message = $parentMessage . sprintf('- reading file %s', $filepath);

            if (mb_strlen($message) > $messageLength) {
                $messageLength = mb_strlen($message);
            }

            $this->write("\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>', false, OutputInterface::VERBOSITY_VERY_VERBOSE);

            $content = file_get_contents($filepath);

            if (false === $content || '' === $content || PHP_EOL === $content) {
                continue;
            }

            try {
                $provider = json_decode(
                    $content,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException $e) {
                $this->writeln('', OutputInterface::VERBOSITY_VERBOSE);
                $this->writeln(
                    '<error>' . (new Exception(sprintf('file %s contains invalid json.', $filepath), 0, $e)) . '</error>'
                );
                continue;
            }

            if (!is_array($provider)) {
                continue;
            }

            $providerName = $file->getFilename();

            foreach ($provider as $data) {
                $agent = trim($data['ua']);

                if ('' === $agent) {
                    continue;
                }

                if (!isset($agents[$agent])) {
                    $agents[$agent] = $base;

                    $agents[$agent]['headers']['user-agent'] = $agent;
                }

                switch ($providerName) {
                    case 'browser-test.json':
                        $agents[$agent]['client']['name']    = 'undefined' === $data['expect']['name'] ? null : $data['expect']['name'];
                        $agents[$agent]['client']['version'] = 'undefined' === $data['expect']['version'] ? null : $data['expect']['version'];

                        $agents[$agent]['raw'][$providerName]  = $data['expect'];
                        $agents[$agent]['file'][$providerName] = $filepath;

                        break;
                    case 'device-test.json':
                        $agents[$agent]['device']['name']  = 'undefined' === $data['expect']['model'] ? null : $data['expect']['model'];
                        $agents[$agent]['device']['brand'] = 'undefined' === $data['expect']['vendor'] ? null : $data['expect']['vendor'];
                        $agents[$agent]['device']['type']  = 'undefined' === $data['expect']['type'] ? null : $data['expect']['type'];

                        $agents[$agent]['raw'][$providerName]  = $data['expect'];
                        $agents[$agent]['file'][$providerName] = $filepath;

                        break;
                    case 'os-test.json':
                        $agents[$agent]['platform']['name']    = 'undefined' === $data['expect']['name'] ? null : $data['expect']['name'];
                        $agents[$agent]['platform']['version'] = 'undefined' === $data['expect']['version'] ? null : $data['expect']['version'];

                        $agents[$agent]['raw'][$providerName]  = $data['expect'];
                        $agents[$agent]['file'][$providerName] = $filepath;

                        break;
                    case 'engine-test.json':
                        $agents[$agent]['engine']['name']    = !isset($data['expect']['name']) || 'undefined' === $data['expect']['name'] ? null : $data['expect']['name'];
                        $agents[$agent]['engine']['version'] = !isset($data['expect']['version']) || 'undefined' === $data['expect']['version'] ? null : $data['expect']['version'];

                        $agents[$agent]['raw'][$providerName]  = $data['expect'];
                        $agents[$agent]['file'][$providerName] = $filepath;

                        break;
                    // Skipping cpu-test.json because we don't look at CPU data, which is all that file tests against
                    // Skipping mediaplayer-test.json because it seems that this file isn't used in this project's actual tests (see test.js)
                }
            }
        }

        yield from $agents;
    }
}
