<?php
/**
 * This file is part of phpCacheAdmin.
 *
 * Copyright (c) Róbert Kelčák (https://kelcak.com/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace RobiNN\Pca\Dashboards\Memcached\Compatibility;

use RobiNN\Pca\Dashboards\Memcached\MemcachedException;

trait CommandTrait {
    /**
     * Unknown or incorrect commands can cause an infinite loop,
     * so only tested commands are allowed.
     *
     * @var array<int, string>
     */
    private array $allowed_commands = [
        'set', 'add', 'replace', 'append', 'prepend', 'cas', 'get', 'gets', 'gat', 'gats',
        'touch', 'delete', 'incr', 'decr', 'stats', 'flush_all', 'version', 'lru_crawler',
        'lru', 'slabs', 'me', 'mg', 'ms', 'md', 'ma', 'cache_memlimit', 'verbosity',
    ];

    /**
     * Commands without a specific end string.
     *
     * @var array<int, string>
     */
    private array $no_end = [
        'incr', 'decr', 'version', 'me', 'mg', 'ms', 'md', 'ma', 'cache_memlimit',
    ];

    /**
     * Loop until the server returns one of these end strings.
     *
     * @var array<int, string>
     */
    private array $with_end = [
        'ERROR', 'CLIENT_ERROR', 'SERVER_ERROR', 'STORED', 'NOT_STORED', 'EXISTS', 'NOT_FOUND', 'TOUCHED',
        'DELETED', 'OK', 'END', 'BUSY', 'BADCLASS', 'NOSPARE', 'NOTFULL', 'UNSAFE', 'SAME', 'RESET', 'EN',
    ];

    /**
     * Run command.
     *
     * These commands should work but not guaranteed to work on any server:
     *
     * set|add|replace|append|prepend <key> <flags> <ttl> <bytes>\r\n<value>
     * cas <key> <flags> <ttl> <bytes> <cas unique>\r\n<value>
     * get|gets <key|keys>
     * gat|gats <ttl> <key|keys>
     * touch <key> <ttl>
     * delete <key>
     * incr|decr <key> <value>
     * stats [items|slabs|sizes|cachedump <slab_id> <limit>|reset|conns]
     * flush_all
     * version
     * lru <tune|mode|temp_ttl> <option list>
     * lru_crawler <<enable|disable>|sleep <microseconds>|tocrawl <limit>|crawl|mgdump <...classid|all>|metadump <...classid|all|hash>>
     * slabs <reassign <source class> <dest class>|automove <0|1|2>>
     * me <key> <flag>
     * mg <key> <flags>*
     * ms <key> <datalen> <flags>*\r\n<value>
     * md <key> <flags>*
     * ma <key> <flags>*
     * cache_memlimit <megabytes>
     * verbosity <level>
     *
     * Note: \r\n is added automatically to the end
     * and \r\n (as a plain string) is converted to a real end of line.
     *
     * @link https://github.com/memcached/memcached/blob/master/doc/protocol.txt
     *
     * @return array<int, mixed>|string
     *
     * @throws MemcachedException
     */
    public function runCommand(string $command, bool $array = false) {
        if (!in_array($this->commandName($command), $this->allowed_commands, true)) {
            throw new MemcachedException('Unknown or not allowed command "'.$command.'".');
        }

        $command = strtr($command, ['\r\n' => "\r\n"])."\r\n";

        return $this->streamConnection($command, $array);
    }

    /**
     * @return array<int, mixed>|string
     *
     * @throws MemcachedException
     */
    private function streamConnection(string $command, bool $array = false) {
        $address = isset($this->server['path']) ? 'unix://'.$this->server['path'] : 'tcp://'.$this->server['host'].':'.$this->server['port'];
        $stream = @stream_socket_client($address, $error_code, $error_message, 3);

        if ($error_message !== '' || $stream === false) {
            throw new MemcachedException('Command: "'.$command.'": '.$error_code.' - '.$error_message);
        }

        fwrite($stream, $command, strlen($command));

        $buffer = '';
        $data = [];

        while (!feof($stream)) {
            $buffer .= fgets($stream, 256);

            if ($this->checkCommandEnd($command, $buffer)) {
                break;
            }

            // Bug fix for gzipped keys
            if ($array) {
                $lines = explode("\r\n", $buffer);
                $buffer = array_pop($lines);

                foreach ($lines as $line) {
                    $data[] = trim($line);
                }
            }
        }

        fclose($stream);

        return $array ? $data : rtrim($buffer, "\r\n");
    }

    private function commandName(string $command): string {
        $parts = explode(' ', $command);

        return strtolower($parts[0]);
    }

    private function checkCommandEnd(string $command, string $buffer): bool {
        return in_array($this->commandName($command), $this->no_end, true) ||
            preg_match('/^('.implode('|', $this->with_end).')/imu', $buffer);
    }
}
