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

class Memcached extends \Memcached implements CompatibilityInterface {
    use CommandTrait;

    /**
     * @var array<string, int|string>
     */
    protected array $server;

    /**
     * @param array<string, int|string> $server
     */
    public function __construct(array $server = []) {
        parent::__construct();

        $this->server = $server;
    }

    /**
     * Check connection.
     *
     * @return bool
     */
    public function isConnected(): bool {
        return $this->getVersion() || $this->getResultCode() === self::RES_SUCCESS;
    }

    /**
     * Get server statistics.
     *
     * @return array<string, mixed>
     */
    public function getServerStats(): array {
        return array_values(@$this->getStats())[0];
    }

    /**
     * Store item.
     *
     * @param string $key
     * @param string $value
     * @param int    $expiration
     *
     * @return bool
     */
    public function store(string $key, string $value, int $expiration = 0): bool {
        return $this->set($key, $value, $expiration);
    }

    /**
     * SASL authentication.
     *
     * @return void
     */
    public function sasl(): void {
        $this->setOption(self::OPT_BINARY_PROTOCOL, true);
        $this->setSaslAuthData($this->server['sasl_username'], $this->server['sasl_password']);
    }
}
