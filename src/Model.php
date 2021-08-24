<?php

/**
 * DVelum project https://github.com/dvelum/dvelum-core , https://github.com/dvelum/dvelum
 *
 * MIT License
 *
 * Copyright (C) 2011-2020  Kirill Yegorov
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */
declare(strict_types=1);

namespace Dvelum\Db;


use Dvelum\Cache\CacheInterface;
use Dvelum\Log\LogInterface;
use Dvelum\Service;
use Psr\Log\LoggerInterface;

abstract class Model
{
    /**
     * @var string
     */
    protected $table;
    /**
     * DB connection config name
     * @var string
     */
    protected $connection;
    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $dbManager;
    /**
     * @var \Dvelum\Db\Adapter
     */
    protected $db;
    /**
     * @var CacheInterface|null
     */
    protected ?CacheInterface $cache;
    /**
     * @var LoggerInterface|null
     */
    protected ?LoggerInterface $log;


    /**
     * Model constructor.
     * @param LoggerInterface|null $log
     * @param ManagerInterface $dbManager
     * @throws \Exception
     */
    protected function __construct(ManagerInterface $dbManager, ?LoggerInterface $log = null, ?CacheInterface $cache = null)
    {
        $this->log = $log;
        $this->dbManager = $dbManager;
        $this->db = $this->dbManager->getDbConnection($this->connection);
        $this->cache = $cache;
    }

    /**
     * Log error message
     * @param string $message
     * @return bool
     */
    public function logError(string $message): bool
    {
        if (!empty($this->log)) {
            try {
                $this->log->error($message);
            } catch (\Throwable $e) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get table name
     * @return string
     */
    public function table(): string
    {
        return $this->table;
    }

    /**
     * Get cache adapter
     * @return CacheInterface|null
     */
    public function getCacheAdapter(): ?CacheInterface
    {
        if (empty($this->cache)) {
            return null;
        }
        return $this->cache;
    }

    /**
     * Get database connection adapter
     * @return Adapter
     */
    public function getDbConnection(): Adapter
    {
        return $this->db;
    }

    /**
     * Create Query
     * @return Query
     */
    public function query(): Query
    {
        return new Query($this);
    }

    /**
     * Delete record by id
     * @param int $recordId
     * @param string $keyField
     * @return bool
     */
    public function delete(int $recordId, string $keyField = 'id'): bool
    {
        try {
            $this->db->delete($this->table, $this->db->quoteIdentifier($keyField) . ' = ' . $recordId);
            return true;
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            return false;
        }
    }

    /**
     * Update record
     * @param int $recordId
     * @param array<string,mixed> $data
     * @param string $keyField
     * @return bool
     */
    public function update(int $recordId, array $data, string $keyField = 'id'): bool
    {
        try {
            $this->db->update($this->table(), $data, $this->db->quoteIdentifier($keyField) . ' = ' . $recordId);
            return true;
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
            return false;
        }
    }

    /**
     * Get record by id
     * @param int $recordId
     * @param string $keyField
     * @return array<int|string,mixed>
     * @throws \Exception
     */
    public function getItem(int $recordId, string $keyField = 'id'): array
    {
        return $this->query()->filters([$keyField => $recordId])->fetchRow();
    }

    /**
     * @return Insert
     */
    public function insert(): Insert
    {
        return new Insert($this->getDbConnection());
    }

    /**
     * @return LoggerInterface|null
     */
    public function getLogsAdapter(): ?LoggerInterface
    {
        return $this->log;
    }

    /**
     * Get cached record
     * @param int $recordId
     * @param int $lifetime
     * @param string $keyField
     * @return array<int|string,mixed>
     * @throws \Exception
     */
    public function getCachedItem(int $recordId, int $lifetime, string $keyField = 'id'): array
    {
        $cache = $this->getCacheAdapter();
        $key = $this->table . '_item_' . $recordId;

        $data = null;


        if (!empty($cache)) {
            $data = $cache->load($key);
        }

        if (!empty($data)) {
            return $data;
        }

        $data = $this->getItem($recordId, $keyField);

        if ($cache) {
            $cache->save($key, $data, $lifetime);
        }
        return $data;
    }
}