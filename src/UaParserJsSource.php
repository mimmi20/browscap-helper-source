<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2019, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use BrowscapHelper\Source\Ua\UserAgent;
use Exception;
use FilterIterator;
use Iterator;
use JsonException;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;

use function assert;
use function file_exists;
use function is_array;
use function json_decode;
use function mb_strlen;
use function sprintf;
use function str_pad;
use function trim;

use const JSON_THROW_ON_ERROR;
use const STR_PAD_RIGHT;

final class UaParserJsSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
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
     * @return iterable<array<string, string>>
     *
     * @throws RuntimeException
     */
    public function getHeaders(string $message, int &$messageLength = 0): iterable
    {
        foreach ($this->loadFromPath($message, $messageLength) as $data) {
            $agent = trim($data['ua']);

            $ua    = UserAgent::fromUseragent($agent);
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield $ua->getHeaders();
        }
    }

    /**
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<array{headers: array<string, string>, device: array{deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, display: array{width: int|null, height: int|null, touch: bool|null, type: string|null, size: float|int|null}, type: string|null, ismobile: bool|null}, client: array{name: string|null, modus: string|null, version: string|null, manufacturer: string|null, bits: int|null, type: string|null, isbot: bool|null}, platform: array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}, engine: array{name: string|null, version: string|null, manufacturer: string|null}}>
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function getProperties(string $message, int &$messageLength = 0): iterable
    {
        $agents = [];
        $base   = [
            'headers' => ['user-agent' => $agent],
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
                'type' => null,
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
        ];

        foreach ($this->loadFromPath($message, $messageLength) as $providerName => $data) {
            $agent = trim($data['ua']);

            if (!isset($agents[$agent])) {
                $agents[$agent] = $base;
            }

            switch ($providerName) {
                case 'browser-test.json':
                    $agents[$agent]['browser']['name']    = 'undefined' === $data['expect']['name'] ? '' : $data['expect']['name'];
                    $agents[$agent]['browser']['version'] = 'undefined' === $data['expect']['version'] ? '' : $data['expect']['version'];

                    break;
                case 'device-test.json':
                    $agents[$agent]['device']['name']  = 'undefined' === $data['expect']['model'] ? '' : $data['expect']['model'];
                    $agents[$agent]['device']['brand'] = 'undefined' === $data['expect']['vendor'] ? '' : $data['expect']['vendor'];
                    $agents[$agent]['device']['type']  = 'undefined' === $data['expect']['type'] ? '' : $data['expect']['type'];

                    break;
                case 'os-test.json':
                    $agents[$agent]['platform']['name']    = 'undefined' === $data['expect']['name'] ? '' : $data['expect']['name'];
                    $agents[$agent]['platform']['version'] = 'undefined' === $data['expect']['version'] ? '' : $data['expect']['version'];

                    break;
                // Skipping cpu-test.json because we don't look at CPU data, which is all that file tests against
                // Skipping engine-test.json because we don't look at Engine data // @todo: fix
                // Skipping mediaplayer-test.json because it seems that this file isn't used in this project's actual tests (see test.js)
            }
        }

        foreach ($agents as $agent => $test) {
            $ua    = UserAgent::fromUseragent($agent);
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield $agent => $test;
        }
    }

    /**
     * @return iterable<array<string, string>>
     *
     * @throws RuntimeException
     */
    private function loadFromPath(string $parentMessage, int &$messageLength = 0): iterable
    {
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

            try {
                $provider = json_decode(
                    $file->getContents(),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException $e) {
                $this->logger->error(
                    new Exception(sprintf('file %s contains invalid json.', $file->getPathname()), 0, $e)
                );
                continue;
            }

            if (!is_array($provider)) {
                continue;
            }

            $providerName = $file->getFilename();

            foreach ($provider as $data) {
                $agent = trim($data['ua']);

                if (empty($agent)) {
                    continue;
                }

                yield $providerName => $data;
            }
        }
    }
}
