<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Fluent;

use Forrest79\PhPgSql\Db;

/**
 * @phpstan-type QueryParams array{
 *   select: array<int|string, string|int|\BackedEnum|Query|Db\Sql>,
 *   distinct: bool,
 *   distinctOn: list<string>,
 *   tables: array<string, array{0: string, 1: string}>,
 *   table-types: array{main: string|NULL, from: list<string>, joins: list<string>, using: string|NULL},
 *   on-conditions: array<string, Condition>,
 *   lateral-tables: array<string, string>,
 *   where: Condition|NULL,
 *   groupBy: array<string>,
 *   having: Condition|NULL,
 *   orderBy: array<string|Db\Sql|Query>,
 *   limit: int|NULL,
 *   offset: int|NULL,
 *   combine-queries: list<array{0: string|Query|Db\Sql, 1: string}>,
 *   insert-columns: array<string>,
 *   insert-onconflict: array{columns-or-constraint: string|list<string>|FALSE|NULL, where: Condition|NULL, do: array<int|string, string|Db\Sql>|FALSE|NULL, do-where: Condition|NULL},
 *   returning: array<int|string, string|int|Query|Db\Sql>,
 *   data: array<string, mixed>,
 *   rows: array<int, array<string, mixed>>,
 *   merge: list<array{0: string, 1: string|Db\Sql, 2: Condition|NULL}>,
 *   with: array{queries: array<string, string|Query|Db\Sql>, queries-suffix: array<string, string>, queries-not-materialized: array<string, string>, recursive: bool},
 *   prefix: list<array<mixed>>,
 *   suffix: list<array<mixed>>
 * }
 */
class QueryBuilder
{
	private const TABLE_NAME = 0;
	private const TABLE_TYPE = 1;


	/**
	 * @param array<string, mixed> $queryParams
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	public function createSqlQuery(string $queryType, array $queryParams): Db\Sql\Query
	{
		$params = [];

		$sql = $this->getPrefixSuffix($queryParams, Query::PARAM_PREFIX, $params);

		if ($queryParams[Query::PARAM_WITH][Query::WITH_QUERIES] !== []) {
			$sql .= $this->createWith($queryParams, $params);
		}

		if ($queryType === Query::QUERY_SELECT) {
			$sql .= $this->createSelect($queryParams, $params) . $this->getPrefixSuffix($queryParams, Query::PARAM_SUFFIX, $params);
		} else if ($queryType === Query::QUERY_INSERT) {
			$sql .= $this->createInsert($queryParams, $params);
		} else if ($queryType === Query::QUERY_UPDATE) {
			$sql .= $this->createUpdate($queryParams, $params);
		} else if ($queryType === Query::QUERY_DELETE) {
			$sql .= $this->createDelete($queryParams, $params);
		} else if ($queryType === Query::QUERY_MERGE) {
			$sql .= $this->createMerge($queryParams, $params);
		} else if ($queryType === Query::QUERY_TRUNCATE) {
			$sql .= $this->createTruncate($queryParams) . $this->getPrefixSuffix($queryParams, Query::PARAM_SUFFIX, $params);
		} else {
			throw Exceptions\QueryBuilderException::badQueryType($queryType);
		}

		return $this->prepareSqlQuery($sql, $params);
	}


	/**
	 * @param list<mixed> $params
	 */
	protected function prepareSqlQuery(string $sql, array $params): Db\Sql\Query
	{
		return new Db\Sql\Query($sql, $params);
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @param list<string>|NULL $insertSelectColumnNames
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function createSelect(
		array $queryParams,
		array &$params,
		array|NULL &$insertSelectColumnNames = NULL,
	): string
	{
		$selectSql = 'SELECT ' .
			$this->getSelectDistinct($queryParams) .
			$this->getSelectColumns($queryParams, $params, $insertSelectColumnNames) .
			$this->getFrom($queryParams, $params, $insertSelectColumnNames === NULL) .
			$this->getJoins($queryParams, $params) .
			$this->getWhere($queryParams, $params) .
			$this->getGroupBy($queryParams) .
			$this->getHaving($queryParams, $params) .
			$this->getOrderBy($queryParams, $params) .
			$this->getLimit($queryParams, $params) .
			$this->getOffset($queryParams, $params);

		return $this->combine($selectSql, $queryParams, $params);
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function createInsert(array $queryParams, array &$params): string
	{
		['table' => $mainTable, 'alias' => $mainTableAlias] = $this->getMainTableMetadata($queryParams);

		$insert = 'INSERT INTO ' . $this->processTable(
			$mainTable,
			$mainTableAlias,
			FALSE,
			$params,
		);

		$columns = [];
		$rows = [];
		if ($queryParams[Query::PARAM_DATA] !== []) {
			$values = [];
			foreach ($queryParams[Query::PARAM_DATA] as $column => $value) {
				$columns[] = $column;
				self::prepareRow($value, $values, $params);
			}
			$rows[] = \implode(', ', $values);
		} else if ($queryParams[Query::PARAM_ROWS] !== []) {
			$columns = $queryParams[Query::PARAM_INSERT_COLUMNS];
			foreach ($queryParams[Query::PARAM_ROWS] as $row) {
				$values = [];
				$fillColumns = $columns === [];
				foreach ($row as $column => $value) {
					if ($fillColumns) {
						$columns[] = $column;
					}
					self::prepareRow($value, $values, $params);
				}
				$rows[] = \implode(', ', $values);
			}
		} else if ($queryParams[Query::PARAM_SELECT] !== []) {
			$columns = $queryParams[Query::PARAM_INSERT_COLUMNS];
		} else {
			throw Exceptions\QueryBuilderException::noDataToInsert();
		}

		if ($queryParams[Query::PARAM_SELECT] !== []) {
			$selectColumns = [];
			$data = ' ' . $this->createSelect($queryParams, $params, $selectColumns);
			if ($columns === []) {
				\assert($selectColumns !== NULL);

				if ((\count($selectColumns) > 1) && \in_array('*', $selectColumns, TRUE)) {
					throw Exceptions\QueryBuilderException::selectAllColumnsCantBeCombinedWithConcreteColumnForInsertSelectWithColumnDetection();
				}

				$columns = $selectColumns;
			}
		} else {
			$data = ' VALUES(' . \implode('), (', $rows) . ')';
		}

		$onConflict = '';
		$onConflictColumnsOrConstraint = $queryParams[Query::PARAM_INSERT_ONCONFLICT][Query::INSERT_ONCONFLICT_COLUMNS_OR_CONSTRAINT];
		$onConflictDo = $queryParams[Query::PARAM_INSERT_ONCONFLICT][Query::INSERT_ONCONFLICT_DO];

		if (($onConflictColumnsOrConstraint !== NULL) && ($onConflictDo === NULL)) {
			throw Exceptions\QueryBuilderException::onConflictNoDo();
		} else if (($onConflictColumnsOrConstraint === NULL) && ($onConflictDo !== NULL)) {
			throw Exceptions\QueryBuilderException::onConflictDoWithoutDefinition();
		} else if (($onConflictColumnsOrConstraint !== NULL) && ($onConflictDo !== NULL)) {
			$onConflict = ' ON CONFLICT';

			if (\is_array($onConflictColumnsOrConstraint)) {
				$onConflict .= ' (' . \implode(', ', $onConflictColumnsOrConstraint) . ')';
			} else if ($onConflictColumnsOrConstraint !== FALSE) {
				$onConflict .= ' ON CONSTRAINT ' . $onConflictColumnsOrConstraint;
			}

			$onConflictWhere = $queryParams[Query::PARAM_INSERT_ONCONFLICT][Query::INSERT_ONCONFLICT_WHERE];
			if ($onConflictWhere !== NULL) {
				$onConflict .= ' WHERE ' . $this->processCondition($onConflictWhere, $params);
			}

			$onConflict .= ' DO ';

			if ($onConflictDo === FALSE) {
				$onConflict .= 'NOTHING';
			} else {
				$onConflict .= 'UPDATE SET ';

				$set = [];
				foreach ($onConflictDo as $column => $value) {
					if (\is_int($column)) {
						if (!\is_string($value)) {
							throw Exceptions\QueryBuilderException::onConflictDoUpdateSetSingleColumnCanBeOnlyString();
						}

						$set[] = $value . ' = EXCLUDED.' . $value;
					} else if ($value instanceof Db\Sql) {
						$set[] = $column . ' = ?';
						$params[] = $value;
					} else {
						$set[] = $column . ' = ' . $value;
					}
				}

				$onConflict .= \implode(', ', $set);

				$onConflictDoWhere = $queryParams[Query::PARAM_INSERT_ONCONFLICT][Query::INSERT_ONCONFLICT_DO_WHERE];
				if ($onConflictDoWhere !== NULL) {
					$onConflict .= ' WHERE ' . $this->processCondition($onConflictDoWhere, $params);
				}
			}
		}

		return $insert
			. ($columns === ['*'] ? '' : ' (' . \implode(', ', $columns) . ')')
			. $data
			. $onConflict
			. $this->getPrefixSuffix($queryParams, Query::PARAM_SUFFIX, $params)
			. $this->getReturning($queryParams, $params);
	}


	/**
	 * @param list<string> $values
	 * @param list<mixed> $params
	 */
	private static function prepareRow(mixed $value, array &$values, array &$params): void
	{
		if (($value instanceof Db\Sql) && self::areParenthesisNeeded($value)) {
			$values[] = '(?)';
			$params[] = $value;
		} else if (\is_array($value)) {
			throw Exceptions\QueryBuilderException::dataCantContainArray();
		} else {
			$values[] = '?';
			$params[] = $value;
		}
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function createUpdate(array $queryParams, array &$params): string
	{
		if ($queryParams[Query::PARAM_DATA] === []) {
			throw Exceptions\QueryBuilderException::noDataToUpdate();
		}

		['table' => $mainTable, 'alias' => $mainTableAlias] = $this->getMainTableMetadata($queryParams);

		$set = [];
		foreach ($queryParams[Query::PARAM_DATA] as $column => $value) {
			if (($value instanceof Db\Sql) && self::areParenthesisNeeded($value)) {
				$set[] = $column . ' = (?)';
				$params[] = $value;
			} else if (\is_array($value)) {
				throw Exceptions\QueryBuilderException::dataCantContainArray();
			} else {
				$set[] = $column . ' = ?';
				$params[] = $value;
			}
		}

		return 'UPDATE ' . $this->processTable(
			$mainTable,
			$mainTableAlias,
			FALSE,
			$params,
		) . ' SET ' . \implode(', ', $set) .
			$this->getFrom($queryParams, $params, FALSE) .
			$this->getJoins($queryParams, $params) .
			$this->getWhere($queryParams, $params) .
			$this->getPrefixSuffix($queryParams, Query::PARAM_SUFFIX, $params) .
			$this->getReturning($queryParams, $params);
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function createDelete(array $queryParams, array &$params): string
	{
		['table' => $mainTable, 'alias' => $mainTableAlias] = $this->getMainTableMetadata($queryParams);

		return 'DELETE FROM ' . $this->processTable(
			$mainTable,
			$mainTableAlias,
			FALSE,
			$params,
		) .
			$this->getWhere($queryParams, $params) .
			$this->getPrefixSuffix($queryParams, Query::PARAM_SUFFIX, $params) .
			$this->getReturning($queryParams, $params);
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function createMerge(array $queryParams, array &$params): string
	{
		['table' => $mainTable, 'alias' => $mainTableAlias] = $this->getMainTableMetadata($queryParams);

		$usingAlias = $queryParams[Query::PARAM_TABLE_TYPES][Query::TABLE_TYPE_USING];
		if ($usingAlias === NULL) {
			throw Exceptions\QueryBuilderException::mergeNoUsing();
		}

		if (!isset($queryParams[Query::PARAM_ON_CONDITIONS][$usingAlias])) {
			throw Exceptions\QueryBuilderException::noOnCondition($usingAlias);
		}

		if ($queryParams[Query::PARAM_MERGE] === []) {
			throw Exceptions\QueryBuilderException::mergeNoWhen();
		}

		$merge = 'MERGE INTO ' . $this->processTable(
			$mainTable,
			$mainTableAlias,
			FALSE,
			$params,
		) . ' USING ' . $this->processTable(
			$queryParams[Query::PARAM_TABLES][$usingAlias][self::TABLE_NAME],
			$usingAlias,
			FALSE,
			$params,
		) . ' ON ' . $this->processCondition(
			$queryParams[Query::PARAM_ON_CONDITIONS][$usingAlias],
			$params,
		);

		foreach ($queryParams[Query::PARAM_MERGE] as $when) {
			[$type, $then, $condition] = $when;

			$merge .= ' WHEN';

			if ($type === Query::MERGE_WHEN_NOT_MATCHED) {
				$merge .= ' NOT';
			} else if ($type !== Query::MERGE_WHEN_MATCHED) {
				throw new Exceptions\ShouldNotHappenException(\sprintf('Bad WHEN type \'%s\' for MERGE.', $type));
			}

			$merge .= ' MATCHED';

			if ($condition !== NULL) {
				$merge .= ' AND ' . $this->processCondition($condition, $params);
			}

			if ($then instanceof Db\Sql) {
				$params[] = $then;
				$then = '?';
			}

			$merge .= ' THEN ' . $then;
		}

		return $merge . $this->getReturning($queryParams, $params);
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function createTruncate(array $queryParams): string
	{
		return 'TRUNCATE ' . $this->getMainTableMetadata($queryParams)['table'];
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function createWith(array $queryParams, array &$params): string
	{
		$queries = [];

		foreach ($queryParams[Query::PARAM_WITH][Query::WITH_QUERIES] as $as => $query) {
			if ($query instanceof Db\Sql) {
				$params[] = $query;
				$query = '?';
			}
			$queries[] = $as . ' AS '
				. (isset($queryParams[Query::PARAM_WITH][Query::WITH_QUERIES_NOT_MATERIALIZED][$as]) ? 'NOT MATERIALIZED ' : '')
				. '(' . $query . ')'
				. (isset($queryParams[Query::PARAM_WITH][Query::WITH_QUERIES_SUFFIX][$as]) ? (' ' . $queryParams[Query::PARAM_WITH][Query::WITH_QUERIES_SUFFIX][$as]) : '');
		}

		return 'WITH ' . ($queryParams[Query::PARAM_WITH][Query::WITH_RECURSIVE] ? 'RECURSIVE ' : '') . \implode(', ', $queries) . ' ';
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @phpstan-param QueryParams $queryParams
	 */
	private function getSelectDistinct(array $queryParams): string
	{
		if (($queryParams[Query::PARAM_DISTINCT] === TRUE) && ($queryParams[Query::PARAM_DISTINCTON] !== [])) {
			throw Exceptions\QueryBuilderException::cantCombineDistinctAndDistinctOn();
		}

		if ($queryParams[Query::PARAM_DISTINCT] === TRUE) {
			return 'DISTINCT ';
		} else if ($queryParams[Query::PARAM_DISTINCTON] !== []) {
			return 'DISTINCT ON (' . implode(', ', $queryParams[Query::PARAM_DISTINCTON]) . ') ';
		}

		return '';
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @param list<string>|NULL $columnNames
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function getSelectColumns(array $queryParams, array &$params, array|NULL &$columnNames = NULL): string
	{
		if ($queryParams[Query::PARAM_SELECT] === []) {
			throw Exceptions\QueryBuilderException::noColumnsToSelect();
		}

		$columns = [];
		foreach ($queryParams[Query::PARAM_SELECT] as $key => $value) {
			if ($columnNames !== NULL) {
				if (\is_int($key) && !\is_string($value)) {
					throw Exceptions\QueryBuilderException::missingColumnAlias();
				}

				\assert(\is_string($key) || \is_string($value));

				$columnNames[] = \is_int($key) ? $value : $key;
			}

			if ($value instanceof Db\Sql) {
				$params[] = $value;
				$value = '(?)';
			}

			$columns[] = (($value instanceof \BackedEnum) ? $value->value : $value) . (\is_int($key) ? '' : (' AS "' . $key . '"'));
		}

		return \implode(', ', $columns);
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function getFrom(array $queryParams, array &$params, bool $useMainTable = TRUE): string
	{
		$from = [];

		if ($useMainTable === TRUE) {
			$mainTableAlias = $queryParams[Query::PARAM_TABLE_TYPES][Query::TABLE_TYPE_MAIN];
			if ($mainTableAlias !== NULL) {
				$from[] = $this->processTable(
					$queryParams[Query::PARAM_TABLES][$mainTableAlias][self::TABLE_NAME],
					$mainTableAlias,
					isset($queryParams[Query::PARAM_LATERAL_TABLES][$mainTableAlias]),
					$params,
				);
			}
		}

		foreach ($queryParams[Query::PARAM_TABLE_TYPES][Query::TABLE_TYPE_FROM] as $tableAlias) {
			$from[] = $this->processTable(
				$queryParams[Query::PARAM_TABLES][$tableAlias][self::TABLE_NAME],
				$tableAlias,
				isset($queryParams[Query::PARAM_LATERAL_TABLES][$tableAlias]),
				$params,
			);
		}

		return $from !== [] ? (' FROM ' . \implode(', ', $from)) : '';
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function getJoins(array $queryParams, array &$params): string
	{
		$joins = [];

		$aliasesWithoutTables = array_diff(
			array_keys($queryParams[Query::PARAM_ON_CONDITIONS]),
			$queryParams[Query::PARAM_TABLE_TYPES][Query::TABLE_TYPE_JOINS],
		);

		if ($aliasesWithoutTables !== []) {
			throw Exceptions\QueryBuilderException::noCorrespondingTable(\array_values($aliasesWithoutTables));
		}

		foreach ($queryParams[Query::PARAM_TABLE_TYPES][Query::TABLE_TYPE_JOINS] as $tableAlias) {
			$joinType = $queryParams[Query::PARAM_TABLES][$tableAlias][self::TABLE_TYPE];

			$table = $joinType . ' ' . $this->processTable(
				$queryParams[Query::PARAM_TABLES][$tableAlias][self::TABLE_NAME],
				$tableAlias,
				isset($queryParams[Query::PARAM_LATERAL_TABLES][$tableAlias]),
				$params,
			);

			if ($joinType === Query::JOIN_CROSS) {
				$joins[] = $table;
			} else {
				if (!isset($queryParams[Query::PARAM_ON_CONDITIONS][$tableAlias])) {
					throw Exceptions\QueryBuilderException::noOnCondition($tableAlias);
				}

				$joins[] = $table . ' ON ' . $this->processCondition(
					$queryParams[Query::PARAM_ON_CONDITIONS][$tableAlias],
					$params,
				);
			}
		}

		return $joins !== [] ? (' ' . \implode(' ', $joins)) : '';
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function getWhere(array $queryParams, array &$params): string
	{
		$where = $queryParams[Query::PARAM_WHERE];

		if ($where === NULL) {
			return '';
		}

		return ' WHERE ' . $this->processCondition($where, $params);
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @phpstan-param QueryParams $queryParams
	 */
	private function getGroupBy(array $queryParams): string
	{
		return $queryParams[Query::PARAM_GROUPBY] === []
			? ''
			: ' GROUP BY ' . \implode(', ', $queryParams[Query::PARAM_GROUPBY]);
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function getHaving(array $queryParams, array &$params): string
	{
		$having = $queryParams[Query::PARAM_HAVING];

		if ($having === NULL) {
			return '';
		}

		return ' HAVING ' . $this->processCondition($having, $params);
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function getOrderBy(array $queryParams, array &$params): string
	{
		$orderBy = $queryParams[Query::PARAM_ORDERBY];

		if ($orderBy === []) {
			return '';
		}

		$columns = [];
		foreach ($orderBy as $value) {
			if ($value instanceof Db\Sql) {
				$params[] = $value;
				$value = self::areParenthesisNeeded($value) ? '(?)' : '?';
			}
			$columns[] = $value;
		}

		return ' ORDER BY ' . \implode(', ', $columns);
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @phpstan-param QueryParams $queryParams
	 */
	private function getLimit(array $queryParams, array &$params): string
	{
		$limit = $queryParams[Query::PARAM_LIMIT];

		if ($limit === NULL) {
			return '';
		}

		$params[] = $limit;

		return ' LIMIT ?';
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @phpstan-param QueryParams $queryParams
	 */
	private function getOffset(array $queryParams, array &$params): string
	{
		$offset = $queryParams[Query::PARAM_OFFSET];

		if ($offset === NULL) {
			return '';
		}

		$params[] = $offset;

		return ' OFFSET ?';
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param Query::PARAM_PREFIX|Query::PARAM_SUFFIX $type
	 * @param list<mixed> $params
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function getPrefixSuffix(array $queryParams, string $type, array &$params): string
	{
		$items = $queryParams[$type] ?? [];

		if ($items === []) {
			return '';
		}

		$processedItems = [];
		foreach ($items as $itemParams) {
			$item = \array_shift($itemParams);

			foreach ($itemParams as $param) {
				$params[] = $param;
			}

			$processedItems[] = $item;
		}

		if ($type === Query::PARAM_PREFIX) {
			return \implode(' ', $processedItems) . ' ';
		} else if ($type === Query::PARAM_SUFFIX) {
			return ' ' . \implode(' ', $processedItems);
		}

		throw new Exceptions\ShouldNotHappenException(\sprintf('Bad prefix/suffix type with value \'%s\'. Valid values are \'%s\'.', $type, \implode('\', \'', [Query::PARAM_PREFIX, Query::PARAM_SUFFIX])));
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function combine(string $sql, array $queryParams, array &$params): string
	{
		$combineQueries = $queryParams[Query::PARAM_COMBINE_QUERIES];

		if ($combineQueries === []) {
			return $sql;
		}

		$combines = [];
		foreach ($combineQueries as $combineQuery) {
			[$query, $type] = $combineQuery;

			if ($query instanceof Db\Sql) {
				$params[] = $query;
				$query = '?';
			}

			$combines[] = $type . ' (' . $query . ')';
		}

		return '(' . $sql . ') ' . \implode(' ', $combines);
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @param list<mixed> $params
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	private function getReturning(array $queryParams, array &$params): string
	{
		if ($queryParams[Query::PARAM_RETURNING] === []) {
			return '';
		}

		$columns = [];
		foreach ($queryParams[Query::PARAM_RETURNING] as $key => $value) {
			if ($value instanceof Db\Sql) {
				$params[] = $value;
				$value = '(?)';
			}

			$columns[] = $value . (\is_int($key) ? '' : (' AS "' . $key . '"'));
		}

		return ' RETURNING ' . \implode(', ', $columns);
	}


	/**
	 * @param array<string, mixed> $queryParams
	 * @return array{table: string, alias: string}
	 * @throws Exceptions\QueryBuilderException
	 * @phpstan-param QueryParams $queryParams
	 */
	final protected function getMainTableMetadata(array $queryParams): array
	{
		if ($queryParams[Query::PARAM_TABLE_TYPES][Query::TABLE_TYPE_MAIN] === NULL) {
			throw Exceptions\QueryBuilderException::noMainTable();
		}

		$alias = $queryParams[Query::PARAM_TABLE_TYPES][Query::TABLE_TYPE_MAIN];

		return ['table' => $queryParams[Query::PARAM_TABLES][$alias][self::TABLE_NAME], 'alias' => $alias];
	}


	/**
	 * @param list<mixed> $params
	 */
	private function processTable(string|Db\Sql|Query $table, string $alias, bool $isLateral, array &$params): string
	{
		if ($table instanceof Db\Sql) {
			$params[] = $table;
			if (self::areParenthesisNeeded($table)) {
				$table = '(?)';
			} else {
				$table = '?';
			}
		}

		if ($isLateral) {
			$table = 'LATERAL ' . $table;
		}

		return ($table === $alias) ? $table : ($table . ' AS ' . $alias);
	}


	/**
	 * @param list<mixed> $params
	 * @throws Exceptions\QueryBuilderException
	 */
	private function processCondition(Condition $condition, array &$params): string
	{
		$params[] = $condition;
		return '?';
	}


	private static function areParenthesisNeeded(Db\Sql $sql): bool
	{
		return $sql instanceof Db\Sql\Query || $sql instanceof Query;
	}

}
