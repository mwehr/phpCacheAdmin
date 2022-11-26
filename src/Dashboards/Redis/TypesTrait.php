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

namespace RobiNN\Pca\Dashboards\Redis;

use Exception;
use RobiNN\Pca\Helpers;
use RobiNN\Pca\Http;
use RobiNN\Pca\Value;

trait TypesTrait {
    /**
     * Extra data for templates.
     *
     * @return array<string, array<string, array<int, string>|string>>
     */
    private function getTypesData(): array {
        // param is for getKeyValue() and deleteSubKey()
        return [
            'extra'  => [
                'hide_title' => ['set'],
                'hide_edit'  => ['stream'],
            ],
            'set'    => ['param' => 'member', 'title' => ''],
            'list'   => ['param' => 'index', 'title' => 'Index'],
            'zset'   => ['param' => 'range', 'title' => 'Score'],
            'hash'   => ['param' => 'hash_key', 'title' => 'Field'],
            'stream' => ['param' => 'stream_id', 'title' => 'Entry ID'],
        ];
    }

    /**
     * Get key's value.
     *
     * Used in edit form.
     *
     * @return array<int, mixed>
     * @throws Exception
     */
    private function getKeyValue(string $type, string $key): array {
        $index = null;
        $score = 0;
        $hash_key = '';

        switch ($type) {
            case 'string':
                $value = $this->redis->get($key);
                break;
            case 'set':
                $members = $this->redis->sMembers($key);
                $value = $members[Http::get('member', 'int')];
                break;
            case 'list':
                $index = Http::get('index', 'int');
                $value = $this->redis->lIndex($key, $index);
                break;
            case 'zset':
                $ranges = $this->redis->zRange($key, 0, -1);
                $range = Http::get('range', 'int');

                $value = $ranges[$range];
                $score = $this->redis->zScore($key, $value);
                break;
            case 'hash':
                $keys = [];

                foreach ($this->redis->hGetAll($key) as $k => $hash_Key_value) {
                    $keys[] = $k;
                }

                $hash_key = Http::get('hash_key', 'string', $keys[0]);
                $value = $this->redis->hGet($key, $hash_key);
                break;
            case 'stream':
                $ranges = $this->redis->xRange($key, '-', '+');
                $value = $ranges[Http::get('stream_id')];
                break;
            default:
                $value = '';
        }

        return [$value, $index, $score, $hash_key];
    }

    /**
     * Get all key's values.
     *
     * Used in view page.
     *
     * @return array<int, mixed>|string
     * @throws Exception
     */
    private function getAllKeyValues(string $type, string $key) {
        switch ($type) {
            case 'string':
                $value = $this->redis->get($key);
                break;
            case 'set':
                $value = $this->redis->sMembers($key);
                break;
            case 'list':
                $value = $this->redis->lRange($key, 0, -1);
                break;
            case 'zset':
                $value = $this->redis->zRange($key, 0, -1);
                break;
            case 'hash':
                $value = $this->redis->hGetAll($key);
                break;
            case 'stream':
                $value = $this->redis->xRange($key, '-', '+');
                break;
            default:
                $value = $this->redis->get($key);
        }

        return $value;
    }

    /**
     * @throws Exception
     */
    private function saveKey(): void {
        $key = Http::post('key');
        $value = Value::encode(Http::post('value'), Http::post('encoder'));
        $old_value = Http::post('old_value');

        switch (Http::post('redis_type')) {
            case 'string':
                $this->redis->set($key, $value);
                break;
            case 'set':
                if (Http::post('value') !== $old_value) {
                    $this->redis->sRem($key, $old_value);
                    $this->redis->sAdd($key, $value);
                }
                break;
            case 'list':
                $size = $this->redis->lLen($key);
                $index = $_POST['index'] ?? '';

                if ($index === '' || $index === (string) $size) { // append
                    $this->redis->rPush($key, $value);
                } elseif ($index === '-1') { // prepend
                    $this->redis->lPush($key, $value);
                } elseif ($index >= 0 && $index < $size) {
                    $this->redis->lSet($key, (int) $index, $value);
                } else {
                    Http::stopRedirect();
                    Helpers::alert($this->template, 'Out of bounds index.', 'bg-red-500');
                }
                break;
            case 'zset':
                $this->redis->zRem($key, $old_value);
                $this->redis->zAdd($key, Http::post('score', 'int'), $value);
                break;
            case 'hash':
                if ($this->redis->hExists($key, Http::get('hash_key'))) {
                    $this->redis->hDel($key, Http::get('hash_key'));
                }

                $this->redis->hSet($key, Http::post('hash_key'), $value);
                break;
            case 'stream':
                $this->redis->xAdd($key, Http::post('stream_id', 'string', '*'), [Http::post('field') => $value]);
                break;
            default:
        }

        $expire = Http::post('expire', 'int');

        if ($expire === -1) {
            $this->redis->persist($key);
        } else {
            $this->redis->expire($key, $expire);
        }

        $old_key = Http::post('old_key');

        if ($old_key !== '' && $old_key !== $key) {
            $this->redis->rename($old_key, $key);
        }

        Http::redirect(['db'], ['view' => 'key', 'key' => $key]);
    }

    /**
     * @throws Exception
     */
    private function deleteSubKey(string $type, string $key): void {
        switch ($type) {
            case 'set':
                $members = $this->redis->sMembers($key);
                $this->redis->sRem($key, $members[Http::get('member', 'int')]);
                break;
            case 'list':
                $this->redis->listRem($key, $this->redis->lIndex($key, Http::get('index', 'int')), -1);
                break;
            case 'zset':
                $ranges = $this->redis->zRange($key, 0, -1);
                $this->redis->zRem($key, $ranges[Http::get('range', 'int')]);
                break;
            case 'hash':
                $this->redis->hDel($key, Http::get('hash_key'));
                break;
            case 'stream':
                $this->redis->xDel($key, [Http::get('stream_id')]);
                break;
            default:
        }

        Http::redirect(['db', 'key', 'view', 'p']);
    }

    /**
     * @throws Exception
     */
    private function getCountOfItemsInKey(string $type, string $key): ?int {
        switch ($type) {
            case 'set':
                $items = $this->redis->sCard($key);
                break;
            case 'list':
                $items = $this->redis->lLen($key);
                break;
            case 'zset':
                $items = $this->redis->zCard($key);
                break;
            case 'hash':
                $items = $this->redis->hLen($key);
                break;
            case 'stream':
                $items = $this->redis->xLen($key);
                break;
            default:
                $items = null;
        }

        return $items;
    }
}
