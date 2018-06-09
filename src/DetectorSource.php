<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2018, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace BrowscapHelper\Source;

use BrowscapHelper\Source\Ua\UserAgent;
use Psr\Log\LoggerInterface;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use Symfony\Component\Finder\Finder;

class DetectorSource implements SourceInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Seld\JsonLint\JsonParser
     */
    private $jsonParser;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

        $this->jsonParser = new JsonParser();
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'mimmi20/browser-detector-tests';
    }

    /**
     * @return iterable|string[]
     */
    public function getUserAgents(): iterable
    {
        foreach ($this->loadFromPath() as $headers => $test) {
            $headers = UserAgent::fromString($headers)->getHeader();

            if (!isset($headers['user-agent'])) {
                continue;
            }

            yield $headers['user-agent'];
        }
    }

    /**
     * @return iterable|string[]
     */
    public function getHeaders(): iterable
    {
        foreach ($this->loadFromPath() as $headers => $test) {
            yield $headers;
        }
    }

    /**
     * @return iterable|array[]
     */
    public function getProperties(): iterable
    {
        yield from $this->loadFromPath();
    }

    /**
     * @return iterable|string[]
     */
    private function loadFromPath(): iterable
    {
        $path = 'vendor/mimmi20/browser-detector-tests/tests/issues';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $finder = new Finder();
        $finder->files();
        $finder->name('*.json');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $content = $file->getContents();

            if ('' === $content || PHP_EOL === $content) {
                unlink($filepath);

                continue;
            }

            try {
                $data = $this->jsonParser->parse(
                    $content,
                    JsonParser::DETECT_KEY_CONFLICTS | JsonParser::PARSE_TO_ASSOC
                );
            } catch (ParsingException $e) {
                $this->logger->critical(new \Exception('    parsing file content [' . $filepath . '] failed', 0, $e));

                continue;
            }

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $test) {
                $agent = (string) UserAgent::fromHeaderArray($test['headers']);

                if (empty($agent)) {
                    continue;
                }

                yield $agent => [
                    'device' => [
                        'deviceName'     => $test['result']['device']['deviceName'],
                        'marketingName'   => null,
                        'manufacturer'    => null,
                        'brand'    => $test['result']['device']['brand'],
                        'pointingMethod'  => null,
                        'resolutionWidth' => null,
                        'resolutionHeight' => null,
                        'dualOrientation' => null,
                        'type'     => $test['result']['device']['type'],
                        'ismobile' => null,
                    ],
                    'browser' => [
                        'name'    => $test['result']['browser']['name'],
                        'modus' => null,
                        'version' => ($test['result']['browser']['version'] === '0.0.0' ? null : $test['result']['browser']['version']),
                        'manufacturer' => null,
                        'bits' => null,
                        'type'         => null,
                        'isbot'        => null,
                    ],
                    'platform' => [
                        'name'    => $test['result']['os']['name'],
                        'marketingName' => null,
                        'version' => ($test['result']['os']['version'] === '0.0.0' ? null : $test['result']['os']['version']),
                        'manufacturer'  => null,
                        'bits' => null,
                    ],
                    'engine' => [
                        'name'    => null,
                        'version' => null,
                        'manufacturer' => null,
                    ],
                ];
            }
        }
    }
}
