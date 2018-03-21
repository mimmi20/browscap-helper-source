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
namespace BrowscapHelper\Source\Reader;

use BrowscapHelper\Source\Helper\Regex;
use Psr\Log\LoggerInterface;

class LogFileReader implements ReaderInterface
{
    /**
     * @var array
     */
    private $files = [];

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $file
     */
    public function addLocalFile(string $file): void
    {
        $this->files[] = $file;
    }

    /**
     * @param \Psr\Log\LoggerInterface $logger
     *
     * @return \Generator
     */
    public function getAgents(LoggerInterface $logger): iterable
    {
        $regex = (new Regex())->getRegex();

        foreach ($this->files as $file) {
            $handle = @fopen($file, 'r');

            $i = 1;

            while (!feof($handle)) {
                $line = fgetss($handle, 65535);

                if (false === $line) {
                    $this->logger->emergency(new \RuntimeException('reading file ' . $file . ' caused an error on line ' . $i));
                    continue;
                }
                ++$i;

                if (empty($line)) {
                    continue;
                }

                $lineMatches = [];

                if (!preg_match($regex, $line, $lineMatches)) {
                    $logger->error('no useragent found in line "' . $line . '" used regex: "' . $regex . '"');

                    continue;
                }

                if (isset($lineMatches['userAgentString'])) {
                    $agentOfLine = trim($lineMatches['userAgentString']);
                } else {
                    $agentOfLine = trim($this->extractAgent($line));
                }

                if (!is_string($agentOfLine)) {
                    continue;
                }

                yield $agentOfLine;
            }

            fclose($handle);
        }
    }

    /**
     * @param string $text
     *
     * @return string
     */
    private function extractAgent(string $text): string
    {
        $parts = explode('"', $text);
        array_pop($parts);

        $userAgent = array_pop($parts);

        return $userAgent;
    }
}
