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

namespace RobiNN\Pca\Dashboards\OPCache;

use JsonException;
use RobiNN\Pca\Format;
use RobiNN\Pca\Helpers;
use RobiNN\Pca\Http;
use RobiNN\Pca\Paginator;

trait OPCacheTrait {
    /**
     * Delete script.
     *
     * @return string
     */
    private function deleteScript(): string {
        try {
            $files = json_decode(Http::post('delete'), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $files = [];
        }

        if (is_array($files) && count($files)) {
            foreach ($files as $key) {
                opcache_invalidate($key, true);
            }
            $message = 'Files has been deleted.';
        } elseif (is_string($files) && opcache_invalidate($files, true)) {
            $name = explode(DIRECTORY_SEPARATOR, $files);
            $message = sprintf('File "%s" was invalidated.', $name[array_key_last($name)]);
        } else {
            $message = 'No files are selected.';
        }

        return $this->template->render('components/alert', ['message' => $message]);
    }

    /**
     * Show more info.
     *
     * @param array<string, mixed> $status
     *
     * @return string
     */
    private function moreInfo(array $status): string {
        unset($status['scripts']);

        $configuration = opcache_get_configuration();
        $status['ini_config'] = $configuration['directives'];

        return $this->template->render('partials/info_table', [
            'panel_title' => 'OPCache Info',
            'array'       => Helpers::convertBoolToString($status),
        ]);
    }

    /**
     * Get cached scripts.
     *
     * @param array<string, mixed> $status
     *
     * @return array<int, array<string, string|int>>
     */
    private function getCachedScripts(array $status): array {
        static $cached_scripts = [];

        if (isset($status['scripts'])) {
            foreach ($status['scripts'] as $script) {
                $full_path = str_replace('\\', '/', $script['full_path']);
                $name = explode('/', $full_path);
                $script_name = $name[array_key_last($name)];

                $pca_root = $_SERVER['DOCUMENT_ROOT'].str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']);

                if (
                    (isset($_GET['ignore']) && $_GET['ignore'] === 'yes') &&
                    Helpers::str_starts_with(str_replace('phar://', '', $full_path), $pca_root)
                ) {
                    continue;
                }

                $cached_scripts[] = [
                    'key'   => $script['full_path'],
                    'items' => [
                        'title'     => ['title' => $script_name, 'full' => $full_path,],
                        'hits'      => Format::number($script['hits']),
                        'memory'    => Format::bytes($script['memory_consumption']),
                        'last_used' => Format::time($script['last_used_timestamp']),
                        'created'   => Format::time($script['timestamp']),
                    ],
                ];
            }
        }

        return $cached_scripts;
    }

    /**
     * Main dashboard content.
     *
     * @param array<string, mixed> $status
     *
     * @return string
     */
    private function mainDashboard(array $status): string {
        $cached_scripts = $this->getCachedScripts($status);

        $paginator = new Paginator($this->template, $cached_scripts, [['ignore', 'pp'], ['p' => '']]);

        $is_ignored = isset($_GET['ignore']) && $_GET['ignore'] === 'yes';

        return $this->template->render('dashboards/opcache', [
            'cached_scripts' => $paginator->getPaginated(),
            'all_files'      => count($cached_scripts),
            'paginator'      => $paginator->render(),
            'ignore_url'     => Http::queryString(['pp', 'p'], ['ignore' => $is_ignored ? 'no' : 'yes']),
            'is_ignored'     => $is_ignored,
        ]);
    }
}
