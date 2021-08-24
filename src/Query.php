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

use Dvelum\Db;
use Dvelum\Db\Select\Filter;

class Query
{
    /**
     * @var Model $model
     */
    protected $model;
    /**
     * @var Adapter $db
     */
    protected $db;

    /**
     * @var array<int|string,Filter|mixed>|null
     */
    protected $filters = null;
    /**
     * @phpstan-var array<string,string|int>|null
     * @var array{sort:string,dir:string,start:int,limit:int}|null
     */
    protected ?array $params = null;
    /**
     * @var array<int|string,string>
     */
    protected array $fields = ['*'];
    /**
     * @var array<int,array>|null
     */
    protected ?array $joins = null;
    /**
     * @var string
     */
    protected string $table;
    /**
     * @var string|null
     */
    protected ?string $tableAlias = null;
    /**
     * @var bool
     */
    protected bool $distinct = false;

    public function __construct(Model $model)
    {
        $this->table = $model->table();
        $this->model = $model;
        $this->db = $model->getDbConnection();
    }

    /**
     * Change database connection
     * @param Adapter $connection
     * @return Query
     */
    public function setDbConnection(Adapter $connection): self
    {
        $this->db = $connection;
        return $this;
    }

    /**
     * @param string $table
     * @return Query
     */
    public function table(string $table): Query
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @param string $alias
     * @return Query
     */
    public function tableAlias(?string $alias): Query
    {
        $this->tableAlias = $alias;
        return $this;
    }

    /**
     * @param array<int|string,mixed>|null $filters
     * @return Query
     */
    public function filters(?array $filters): Query
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * @param array<string,int|string>|null $params
     * @return Query
     */
    public function params(?array $params): Query
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @param mixed $fields
     * @return Query
     */
    public function fields($fields): Query
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * @param array<int,array>|null $joins
     * Config Example:
     * array(
     *        array(
     *            'joinType'=>   jonLeft/left , jonRight/right , joinInner/inner
     *            'table' => array / string
     *            'fields => array / string
     *            'condition'=> string
     *        )...
     * )
     * @return Query
     */
    public function joins(?array $joins): Query
    {
        $this->joins = $joins;
        return $this;
    }


    /**
     * Apply query filters
     * @param Db\Select $sql
     * @param array<int|string, mixed> $filters
     * @return void
     */
    public function applyFilters(Db\Select $sql, array $filters): void
    {
        foreach ($filters as $k => $v) {
            $k = (string)$k;
            if ($v instanceof Filter) {
                $v->applyTo($this->db, $sql);
            } else {
                if (is_array($v) && !empty($v)) {
                    $sql->where($this->db->quoteIdentifier($k) . ' IN(?)', $v);
                } elseif (is_bool($v)) {
                    $sql->where($this->db->quoteIdentifier($k) . ' = ' . (int)($v));
                } elseif ((is_string($v) && strlen($v)) || is_numeric($v)) {
                    $sql->where($this->db->quoteIdentifier($k) . ' =?', $v);
                } elseif (is_null($v)) {
                    $sql->where($this->db->quoteIdentifier($k) . ' IS NULL');
                }
            }
        }
    }

    /**
     * Apply query params (sorting and pagination)
     * @param Db\Select $sql
     * @phpstan-param array<string,mixed> $params
     * @param array{start:int,limit:int,sort:array|string,dir:array|string} $params
     */
    public function applyParams(Db\Select $sql, array $params): void
    {
        if (isset($params['limit'])) {
            $sql->limit((int)$params['limit']);
        }

        if (isset($params['start'])) {
            $sql->offset((int)$params['start']);
        }

        if (!empty($params['sort']) && !empty($params['dir'])) {
            if (is_array($params['sort']) && !is_array($params['dir'])) {
                $sort = [];
                foreach ($params['sort'] as $key => $field) {
                    if (!is_int($key)) {
                        $order = trim(strtolower($field));
                        if ($order == 'asc' || $order == 'desc') {
                            $sort[$key] = $order;
                        }
                    } else {
                        $sort[$field] = $params['dir'];
                    }
                }
                $sql->order($sort);
            } else {
                $sql->order([(string)$params['sort'] => $params['dir']]);
            }
        }
    }

    /**
     * Apply Join conditions
     * @param Db\Select $sql
     * @param array<int, array> $joins
     * @return void
     */
    public function applyJoins(Db\Select $sql, array $joins): void
    {
        foreach ($joins as $config) {
            switch ($config['joinType']) {
                case 'joinLeft' :
                case 'left':
                    $sql->join($config['table'], $config['condition'], $config['fields'], Db\Select::JOIN_LEFT);
                    break;
                case 'joinRight' :
                case 'right':
                    $sql->join($config['table'], $config['condition'], $config['fields'], Db\Select::JOIN_RIGHT);
                    break;
                case 'joinInner':
                case 'inner':
                    $sql->join($config['table'], $config['condition'], $config['fields'], Db\Select::JOIN_INNER);
                    break;
            }
        }
    }

    /**
     * Prepare Db\Select object
     * @return Db\Select
     */
    public function sql(): Db\Select
    {
        $sql = $this->db->select();

        if (!empty($this->tableAlias)) {
            $sql->from([$this->tableAlias => $this->table]);
        } else {
            $sql->from($this->table);
        }

        $sql->columns($this->fields);

        if (!empty($this->filters)) {
            $this->applyFilters($sql, $this->filters);
        }

        if (!empty($this->params)) {
            $this->applyParams($sql, $this->params);
        }

        if (!empty($this->joins)) {
            $this->applyJoins($sql, $this->joins);
        }

        if (!empty($this->distinct)) {
            $sql->distinct();
        }

        return $sql;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->sql()->__toString();
    }

    /**
     * Fetch all records
     * @return array<int,array>
     * @throws \Exception
     */
    public function fetchAll(): array
    {
        try {
            return $this->db->fetchAll($this->__toString());
        } catch (\Exception $e) {
            $this->model->logError($e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch one
     * @return mixed
     * @throws \Exception
     */
    public function fetchOne()
    {
        try {
            return $this->db->fetchOne($this->__toString());
        } catch (\Exception $e) {
            $this->model->logError($e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch first result row
     * @return array<int|string,mixed>
     * @throws \Exception
     */
    public function fetchRow(): array
    {
        try {
            $result = $this->db->fetchRow($this->__toString());
            if (empty($result)) {
                $result = [];
            }
            return $result;
        } catch (\Exception $e) {
            $this->model->logError($e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch column
     * @return array<int,mixed>
     * @throws \Exception
     */
    public function fetchCol(): array
    {
        try {
            return $this->db->fetchCol($this->__toString());
        } catch (\Exception $e) {
            $this->model->logError($e->getMessage());
            throw $e;
        }
    }

    /**
     * Count the number of rows that satisfy the filters
     * @return int
     * @throws \Exception
     */
    public function getCount(): int
    {
        $joins = $this->joins;
        $filters = $this->filters;
        $tableAlias = $this->tableAlias;
        $distinct = $this->distinct;

        // disable fields selection
        if (!empty($joins)) {
            foreach ($joins as & $config) {
                $config['fields'] = [];
            }
            unset($config);
        }

        $sqlQuery = new Query($this->model);
        $sqlQuery->setDbConnection($this->db);
        $sqlQuery->distinct($this->distinct);
        $sqlQuery->fields(['count' => 'COUNT(*)'])->tableAlias($tableAlias)
            ->filters($filters)
            ->joins($joins);

        if (!empty($this->tableAlias)) {
            $sqlQuery->tableAlias((string)$this->tableAlias);
        }

        $count = $sqlQuery->fetchOne();

        if (empty($count)) {
            $count = 0;
        }

        return (int)$count;
    }

    /**
     * Set DISTINCT option
     * @param bool $bool
     * @return $this
     */
    public function distinct(bool $bool = true): self
    {
        $this->distinct = $bool;
        return $this;
    }
}