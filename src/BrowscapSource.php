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

use BrowscapHelper\Source\Ua\UserAgent;
use FilterIterator;
use Iterator;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;

use function array_key_exists;
use function assert;
use function file_exists;
use function is_array;
use function mb_strlen;
use function sprintf;
use function str_pad;
use function trim;

use const STR_PAD_RIGHT;

final class BrowscapSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const NAME = 'browscap/browscap';
    private const PATH = 'vendor/browscap/browscap/tests/issues';

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
     * @throws LogicException
     * @throws RuntimeException
     */
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable
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

            $data = include $filepath;

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $row) {
                if (!array_key_exists('ua', $row)) {
                    continue;
                }

                $agent = trim($row['ua']);

                if ('' === $agent) {
                    continue;
                }

                $pointingMethod = $row['properties']['Device_Pointing_Method'] ?? null;

                $isMobile = false;

                if (isset($row['properties']['Device_Type'])) {
                    switch ($row['properties']['Device_Type']) {
                        case 'Mobile Phone':
                        case 'Tablet':
                        case 'Console':
                        case 'Digital Camera':
                        case 'Ebook Reader':
                        case 'Mobile Device':
                            $isMobile = true;

                            break;
                    }
                }

                yield [
                    'headers' => ['user-agent' => $agent],
                    'device' => [
                        'deviceName' => $row['properties']['Device_Code_Name'] ?? null,
                        'marketingName' => $row['properties']['Device_Name'] ?? null,
                        'manufacturer' => $row['properties']['Device_Maker'] ?? null,
                        'brand' => $row['properties']['Device_Brand_Name'] ?? null,
                        'display' => [
                            'width' => null,
                            'height' => null,
                            'touch' => ('touchscreen' === $pointingMethod),
                            'type' => null,
                            'size' => null,
                        ],
                        'dualOrientation' => null,
                        'type' => $row['properties']['Device_Type'] ?? null,
                        'simCount' => null,
                        'ismobile' => $isMobile,
                    ],
                    'client' => [
                        'name' => $row['properties']['Browser'] ?? null,
                        'modus' => $row['properties']['Browser_Modus'] ?? null,
                        'version' => $row['properties']['Version'] ?? null,
                        'manufacturer' => $row['properties']['Browser_Maker'] ?? null,
                        'bits' => $row['properties']['Browser_Bits'] ?? null,
                        'type' => $row['properties']['Browser_Type'] ?? null,
                        'isbot' => array_key_exists('Crawler', $row['properties']) ? $row['properties']['Crawler'] : null,
                    ],
                    'platform' => [
                        'name' => $row['properties']['Platform'] ?? null,
                        'marketingName' => null,
                        'version' => $row['properties']['Platform_Version'] ?? null,
                        'manufacturer' => $row['properties']['Platform_Maker'] ?? null,
                        'bits' => $row['properties']['Platform_Bits'] ?? null,
                    ],
                    'engine' => [
                        'name' => $row['properties']['RenderingEngine_Name'] ?? null,
                        'version' => $row['properties']['RenderingEngine_Version'] ?? null,
                        'manufacturer' => $row['properties']['RenderingEngine_Maker'] ?? null,
                    ],
                    'raw' => $row,
                    'file' => $filepath,
                ];
            }
        }
    }
}
