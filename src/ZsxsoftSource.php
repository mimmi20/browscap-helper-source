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
use FilterIterator;
use Iterator;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use SplFileObject;
use Symfony\Component\Console\Output\OutputInterface;

use function array_unique;
use function assert;
use function count;
use function file_exists;
use function mb_strlen;
use function mb_strpos;
use function preg_match;
use function sprintf;
use function str_pad;
use function str_replace;
use function trim;
use function usort;

use const STR_PAD_RIGHT;

final class ZsxsoftSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use OutputAwareTrait;

    private const NAME = 'zsxsoft/php-useragent';
    private const PATH = 'vendor/zsxsoft/php-useragent/lib';

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
            $agent = trim($data[0][0]);

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
        $brands = $this->getBrands();

        foreach ($this->loadFromPath($message, $messageLength) as $data) {
            $agent = trim($data[0][0]);

            $ua    = UserAgent::fromUseragent($agent);
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            $model = '';

            foreach ($brands as $brand) {
                if (false !== mb_strpos($data[1][8], $brand)) {
                    $model = trim(str_replace($brand, '', $data[1][8]));

                    break;
                }

                $brand = '';
            }

            yield [
                'headers' => ['user-agent' => $agent],
                'device' => [
                    'deviceName' => $model,
                    'marketingName' => null,
                    'manufacturer' => null,
                    'brand' => $brand ?? null,
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
                    'name' => $data[1][2],
                    'modus' => null,
                    'version' => $data[1][3],
                    'manufacturer' => null,
                    'bits' => null,
                    'type' => null,
                    'isbot' => null,
                ],
                'platform' => [
                    'name' => $data[1][5],
                    'marketingName' => null,
                    'version' => $data[1][6],
                    'manufacturer' => null,
                    'bits' => null,
                ],
                'engine' => [
                    'name' => null,
                    'version' => null,
                    'manufacturer' => null,
                ],
            ];
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
        $files    = new class ($iterator, 'php') extends FilterIterator {
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

            $provider = require $filepath;

            foreach ($provider as $data) {
                yield $data;
            }
        }
    }

    /**
     * @return string[]
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    private function getBrands(): array
    {
        $brands = [];
        $file   = new SplFileObject('vendor/zsxsoft/php-useragent/lib/useragent_detect_device.php');
        $file->setFlags(SplFileObject::DROP_NEW_LINE);
        while (!$file->eof()) {
            $line = trim($file->fgets());
            preg_match('/^\$brand = (["\'])(.*)(["\']);$/', $line, $matches);

            if (0 >= count($matches)) {
                continue;
            }

            $brand = $matches[2];
            if (empty($brand)) {
                continue;
            }

            $brands[] = $brand;
        }

        $brands = array_unique($brands);

        usort($brands, static fn ($a, $b): int => mb_strlen($b) - mb_strlen($a));

        return $brands;
    }
}
