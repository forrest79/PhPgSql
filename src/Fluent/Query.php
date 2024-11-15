<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Fluent;

use Forrest79\PhPgSql\Db;

/**
 * @phpstan-import-type QueryParams from QueryBuilder
 */
class Query implements Db\Sql
{
	public const QUERY_SELECT = 'select';
	public const QUERY_INSERT = 'insert';
	public const QUERY_UPDATE = 'update';
	public const QUERY_DELETE = 'delete';
	public const QUERY_MERGE = 'merge';
	public const QUERY_TRUNCATE = 'truncate';

	public const PARAM_SELECT = 'select';
	public const PARAM_DISTINCT = 'distinct';
	public const PARAM_DISTINCTON = 'distinctOn';
	public const PARAM_TABLES = 'tables';
	public const PARAM_TABLE_TYPES = 'table-types';
	public const PARAM_ON_CONDITIONS = 'on-conditions';
	public const PARAM_LATERAL_TABLES = 'lateral-tables';
	public const PARAM_WHERE = 'where';
	public const PARAM_GROUPBY = 'groupBy';
	public const PARAM_HAVING = 'having';
	public const PARAM_ORDERBY = 'orderBy';
	public const PARAM_LIMIT = 'limit';
	public const PARAM_OFFSET = 'offset';
	public const PARAM_COMBINE_QUERIES = 'combine-queries';
	public const PARAM_INSERT_COLUMNS = 'insert-columns';
	public const PARAM_INSERT_ONCONFLICT = 'insert-onconflict';
	public const PARAM_RETURNING = 'returning';
	public const PARAM_DATA = 'data';
	public const PARAM_ROWS = 'rows';
	public const PARAM_MERGE = 'merge';
	public const PARAM_WITH = 'with';
	public const PARAM_PREFIX = 'prefix';
	public const PARAM_SUFFIX = 'suffix';

	public const TABLE_TYPE_MAIN = 'main';
	public const TABLE_TYPE_FROM = 'from';
	public const TABLE_TYPE_JOINS = 'joins';
	public const TABLE_TYPE_USING = 'using';

	private const JOIN_INNER = 'INNER JOIN';
	private const JOIN_LEFT_OUTER = 'LEFT OUTER JOIN';
	private const JOIN_RIGHT_OUTER = 'RIGHT OUTER JOIN';
	private const JOIN_FULL_OUTER = 'FULL OUTER JOIN';
	public const JOIN_CROSS = 'CROSS JOIN';

	private const COMBINE_UNION = 'UNION';
	private const COMBINE_UNION_ALL = 'UNION ALL';
	private const COMBINE_INTERSECT = 'INTERSECT';
	private const COMBINE_EXCEPT = 'EXCEPT';

	public const INSERT_ONCONFLICT_COLUMNS_OR_CONSTRAINT = 'columns-or-constraint';
	public const INSERT_ONCONFLICT_WHERE = 'where';
	public const INSERT_ONCONFLICT_DO = 'do';
	public const INSERT_ONCONFLICT_DO_WHERE = 'do-where';

	public const MERGE_WHEN_MATCHED = 'when-matched';
	public const MERGE_WHEN_NOT_MATCHED = 'when-not-matched';

	public const WITH_QUERIES = 'queries';
	public const WITH_QUERIES_SUFFIX = 'queries-suffix';
	public const WITH_QUERIES_NOT_MATERIALIZED = 'queries-not-materialized';
	public const WITH_RECURSIVE = 'recursive';

	private const DEFAULT_PARAMS = [
		self::PARAM_SELECT => [],
		self::PARAM_DISTINCT => FALSE,
		self::PARAM_DISTINCTON => [],
		self::PARAM_TABLES => [],
		self::PARAM_TABLE_TYPES => [
			self::TABLE_TYPE_MAIN => NULL,
			self::TABLE_TYPE_FROM => [],
			self::TABLE_TYPE_JOINS => [],
			self::TABLE_TYPE_USING => NULL,
		],
		self::PARAM_ON_CONDITIONS => [],
		self::PARAM_LATERAL_TABLES => [],
		self::PARAM_WHERE => NULL,
		self::PARAM_GROUPBY => [],
		self::PARAM_HAVING => NULL,
		self::PARAM_ORDERBY => [],
		self::PARAM_LIMIT => NULL,
		self::PARAM_OFFSET => NULL,
		self::PARAM_COMBINE_QUERIES => [],
		self::PARAM_INSERT_COLUMNS => [],
		self::PARAM_INSERT_ONCONFLICT => [
			self::INSERT_ONCONFLICT_COLUMNS_OR_CONSTRAINT => NULL,
			self::INSERT_ONCONFLICT_WHERE => NULL,
			self::INSERT_ONCONFLICT_DO => NULL,
			self::INSERT_ONCONFLICT_DO_WHERE => NULL,
		],
		self::PARAM_RETURNING => [],
		self::PARAM_DATA => [],
		self::PARAM_ROWS => [],
		self::PARAM_MERGE => [],
		self::PARAM_WITH => [
			self::WITH_QUERIES => [],
			self::WITH_QUERIES_SUFFIX => [],
			self::WITH_QUERIES_NOT_MATERIALIZED => [],
			self::WITH_RECURSIVE => FALSE,
		],
		self::PARAM_PREFIX => [],
		self::PARAM_SUFFIX => [],
	];

	private QueryBuilder $queryBuilder;

	private string $queryType = self::QUERY_SELECT;

	/** @phpstan-var QueryParams */
	private array $params = self::DEFAULT_PARAMS;

	private Db\SqlDefinition|NULL $sqlDefinition = NULL;

	private Db\Query|NULL $dbQuery = NULL;


	public function __construct(QueryBuilder $queryBuilder)
	{
		$this->queryBuilder = $queryBuilder;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function table(string|Db\Sql $table, string|NULL $alias = NULL): static
	{
		return $this->addTable(self::TABLE_TYPE_MAIN, $table, $alias);
	}


	/**
	 * @param array<int|string, string|int|bool|\BackedEnum|Db\Sql|NULL> $columns
	 * @throws Exceptions\QueryException
	 */
	public function select(array $columns): static
	{
		$this->resetQuery();

		foreach ($columns as $alias => &$column) {
			if (\is_int($alias)) {
				$alias = NULL;
			}

			if ($column === NULL) {
				$column = 'NULL';
			} else if ($column === TRUE) {
				$column = 'TRUE';
			} else if ($column === FALSE) {
				$column = 'FALSE';
			}

			$this->checkAlias($column, $alias);

			if (!($column instanceof Db\Sql) && !\is_scalar($column) && !($column instanceof \BackedEnum)) {
				throw Exceptions\QueryException::columnMustBeScalarOrEnumOrExpression();
			}
		}

		$this->params[self::PARAM_SELECT] = \array_merge($this->params[self::PARAM_SELECT], $columns);

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function distinct(): static
	{
		$this->resetQuery();

		$this->params[self::PARAM_DISTINCT] = TRUE;

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function distinctOn(string ...$on): static
	{
		$this->resetQuery();

		$this->params[self::PARAM_DISTINCTON] = \array_merge($this->params[self::PARAM_DISTINCTON], $on);

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function from(string|Db\Sql $from, string|NULL $alias = NULL): static
	{
		return $this->addTable(self::TABLE_TYPE_FROM, $from, $alias);
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function join(
		string|Db\Sql $join,
		string|NULL $alias = NULL,
		string|Db\Sql|NULL $onCondition = NULL,
	): static
	{
		return $this->innerJoin($join, $alias, $onCondition);
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function innerJoin(
		string|Db\Sql $join,
		string|NULL $alias = NULL,
		string|Db\Sql|NULL $onCondition = NULL,
	): static
	{
		return $this->addTable(self::JOIN_INNER, $join, $alias, $onCondition);
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function leftJoin(
		string|Db\Sql $join,
		string|NULL $alias = NULL,
		string|Db\Sql|NULL $onCondition = NULL,
	): static
	{
		return $this->leftOuterJoin($join, $alias, $onCondition);
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function leftOuterJoin(
		string|Db\Sql $join,
		string|NULL $alias = NULL,
		string|Db\Sql|NULL $onCondition = NULL,
	): static
	{
		return $this->addTable(self::JOIN_LEFT_OUTER, $join, $alias, $onCondition);
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function rightJoin(
		string|Db\Sql $join,
		string|NULL $alias = NULL,
		string|Db\Sql|NULL $onCondition = NULL,
	): static
	{
		return $this->rightOuterJoin($join, $alias, $onCondition);
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function rightOuterJoin(
		string|Db\Sql $join,
		string|NULL $alias = NULL,
		string|Db\Sql|NULL $onCondition = NULL,
	): static
	{
		return $this->addTable(self::JOIN_RIGHT_OUTER, $join, $alias, $onCondition);
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function fullJoin(
		string|Db\Sql $join,
		string|NULL $alias = NULL,
		string|Db\Sql|NULL $onCondition = NULL,
	): static
	{
		return $this->fullOuterJoin($join, $alias, $onCondition);
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function fullOuterJoin(
		string|Db\Sql $join,
		string|NULL $alias = NULL,
		string|Db\Sql|NULL $onCondition = NULL,
	): static
	{
		return $this->addTable(self::JOIN_FULL_OUTER, $join, $alias, $onCondition);
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function crossJoin(string|Db\Sql $join, string|NULL $alias = NULL): static
	{
		return $this->addTable(self::JOIN_CROSS, $join, $alias);
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	private function addTable(
		string $type,
		string|Db\Sql $name,
		string|NULL $alias,
		string|Db\Sql|NULL $onCondition = NULL,
	): static
	{
		$this->resetQuery();

		$this->checkAlias($name, $alias);

		if (\in_array($type, [self::TABLE_TYPE_MAIN, self::TABLE_TYPE_USING], TRUE) && ($this->params[self::PARAM_TABLE_TYPES][$type] !== NULL)) {
			throw ($type === self::TABLE_TYPE_MAIN) ? Exceptions\QueryException::onlyOneMainTable() : Exceptions\QueryException::mergeOnlyOneUsing();
		}

		if ($alias === NULL) {
			$alias = $name;
			\assert(\is_string($alias));
		}

		if (isset($this->params[self::PARAM_TABLES][$alias])) {
			throw Exceptions\QueryException::tableAliasAlreadyExists($alias);
		}

		$this->params[self::PARAM_TABLES][$alias] = [$name, $type];

		if (\in_array($type, [self::TABLE_TYPE_MAIN, self::TABLE_TYPE_USING], TRUE)) {
			$this->params[self::PARAM_TABLE_TYPES][$type] = $alias;
		} else {
			$this->params[self::PARAM_TABLE_TYPES][$type === self::TABLE_TYPE_FROM ? $type : self::TABLE_TYPE_JOINS][] = $alias;
		}

		if ($onCondition !== NULL) {
			$this->getConditionParam(self::PARAM_ON_CONDITIONS, $alias)->add($onCondition);
		}

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function on(string $alias, string|Db\Sql $condition, mixed ...$params): static
	{
		$this->resetQuery();

		$this->getConditionParam(self::PARAM_ON_CONDITIONS, $alias)->add($condition, ...$params);

		return $this;
	}


	public function lateral(string $alias): static
	{
		$this->params[self::PARAM_LATERAL_TABLES][$alias] = $alias;

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function where(string|Db\Sql $condition, mixed ...$params): static
	{
		$this->resetQuery();

		$this->getConditionParam(self::PARAM_WHERE)->add($condition, ...$params);

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function whereIf(bool $ifCondition, string|Db\Sql $condition, mixed ...$params): static
	{
		if ($ifCondition) {
			return $this->where($condition, ...$params);
		}

		return $this;
	}


	/**
	 * @param list<string|list<mixed>|Db\Sql> $conditions
	 * @throws Exceptions\QueryException
	 */
	public function whereAnd(array $conditions = []): Condition
	{
		$this->resetQuery();

		$condition = Condition::createAnd($conditions, NULL, $this);
		$this->getConditionParam(self::PARAM_WHERE)->add($condition);

		return $condition;
	}


	/**
	 * @param list<string|list<mixed>|Db\Sql> $conditions
	 * @throws Exceptions\QueryException
	 */
	public function whereOr(array $conditions = []): Condition
	{
		$this->resetQuery();

		$condition = Condition::createOr($conditions, NULL, $this);
		$this->getConditionParam(self::PARAM_WHERE)->add($condition);

		return $condition;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function groupBy(string ...$columns): static
	{
		$this->resetQuery();

		$this->params[self::PARAM_GROUPBY] = \array_merge($this->params[self::PARAM_GROUPBY], $columns);

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function having(string|Db\Sql $condition, mixed ...$params): static
	{
		$this->resetQuery();

		$this->getConditionParam(self::PARAM_HAVING)->add($condition, ...$params);

		return $this;
	}


	/**
	 * @param list<string|list<mixed>|Db\Sql> $conditions
	 * @throws Exceptions\QueryException
	 */
	public function havingAnd(array $conditions = []): Condition
	{
		$this->resetQuery();

		$condition = Condition::createAnd($conditions, NULL, $this);
		$this->getConditionParam(self::PARAM_HAVING)->add($condition);

		return $condition;
	}


	/**
	 * @param list<string|list<mixed>|Db\Sql> $conditions
	 * @throws Exceptions\QueryException
	 */
	public function havingOr(array $conditions = []): Condition
	{
		$this->resetQuery();

		$condition = Condition::createOr($conditions, NULL, $this);
		$this->getConditionParam(self::PARAM_HAVING)->add($condition);

		return $condition;
	}


	private function getConditionParam(string $param, string|NULL $alias = NULL): Condition
	{
		if ($param === self::PARAM_ON_CONDITIONS) {
			if (!isset($this->params[$param][$alias])) {
				$this->params[$param][$alias] = Condition::createAnd();
			}

			return $this->params[$param][$alias];
		} else if (($param === self::PARAM_WHERE) || ($param === self::PARAM_HAVING)) {
			if ($this->params[$param] === NULL) {
				$this->params[$param] = Condition::createAnd();
			}

			return $this->params[$param];
		}

		throw new Exceptions\ShouldNotHappenException('Invalid argument: ' . $param);
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function orderBy(string|Db\Sql ...$columns): static
	{
		$this->resetQuery();

		$this->params[self::PARAM_ORDERBY] = \array_merge($this->params[self::PARAM_ORDERBY], $columns);

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function limit(int $limit): static
	{
		$this->resetQuery();

		$this->params[self::PARAM_LIMIT] = $limit;

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function offset(int $offset): static
	{
		$this->resetQuery();

		$this->params[self::PARAM_OFFSET] = $offset;

		return $this;
	}


	public function union(string|Db\Sql $query): static
	{
		return $this->addCombine(self::COMBINE_UNION, $query);
	}


	public function unionAll(string|Db\Sql $query): static
	{
		return $this->addCombine(self::COMBINE_UNION_ALL, $query);
	}


	public function intersect(string|Db\Sql $query): static
	{
		return $this->addCombine(self::COMBINE_INTERSECT, $query);
	}


	public function except(string|Db\Sql $query): static
	{
		return $this->addCombine(self::COMBINE_EXCEPT, $query);
	}


	private function addCombine(string $type, string|Db\Sql $query): static
	{
		$this->params[self::PARAM_COMBINE_QUERIES][] = [$query, $type];
		return $this;
	}


	/**
	 * @param list<string>|NULL $columns
	 * @throws Exceptions\QueryException
	 */
	public function insert(string|NULL $into = NULL, string|NULL $alias = NULL, array|NULL $columns = []): static
	{
		$this->resetQuery();

		$this->queryType = self::QUERY_INSERT;

		if ($into !== NULL) {
			$this->table($into, $alias);
		}

		$this->params[self::PARAM_INSERT_COLUMNS] = $columns;

		return $this;
	}


	/**
	 * @param array<string, mixed> $data
	 * @throws Exceptions\QueryException
	 */
	public function values(array $data): static
	{
		$this->resetQuery();

		$this->queryType = self::QUERY_INSERT;
		$this->params[self::PARAM_DATA] = $data + $this->params[self::PARAM_DATA];

		return $this;
	}


	/**
	 * @param list<array<string, mixed>> $rows
	 * @throws Exceptions\QueryException
	 */
	public function rows(array $rows): static
	{
		$this->resetQuery();

		$this->queryType = self::QUERY_INSERT;
		$this->params[self::PARAM_ROWS] = \array_merge($this->params[self::PARAM_ROWS], $rows);

		return $this;
	}


	/**
	 * @param string|list<string>|NULL $columnsOrConstraint
	 * @throws Exceptions\QueryException
	 */
	public function onConflict(string|array|NULL $columnsOrConstraint = NULL, string|Db\Sql|NULL $where = NULL): static
	{
		$this->resetQuery();

		if (\is_string($columnsOrConstraint) && ($where !== NULL)) {
			throw Exceptions\QueryException::onConflictWhereNotForConstraint();
		}

		$this->params[self::PARAM_INSERT_ONCONFLICT][self::INSERT_ONCONFLICT_COLUMNS_OR_CONSTRAINT] = $columnsOrConstraint ?? FALSE;
		$this->params[self::PARAM_INSERT_ONCONFLICT][self::INSERT_ONCONFLICT_WHERE] = $where === NULL ? NULL : Condition::createAnd()->add($where);

		return $this;
	}


	/**
	 * @param array<int|string, string|Db\Sql> $set
	 * @throws Exceptions\QueryException
	 */
	public function doUpdate(array $set, string|Db\Sql|NULL $where = NULL): static
	{
		$this->resetQuery();

		$this->params[self::PARAM_INSERT_ONCONFLICT][self::INSERT_ONCONFLICT_DO] = $set;
		$this->params[self::PARAM_INSERT_ONCONFLICT][self::INSERT_ONCONFLICT_DO_WHERE] = $where === NULL ? NULL : Condition::createAnd()->add($where);

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function doNothing(): static
	{
		$this->resetQuery();

		$this->params[self::PARAM_INSERT_ONCONFLICT][self::INSERT_ONCONFLICT_DO] = FALSE;
		$this->params[self::PARAM_INSERT_ONCONFLICT][self::INSERT_ONCONFLICT_DO_WHERE] = NULL;

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function update(string|NULL $table = NULL, string|NULL $alias = NULL): static
	{
		$this->resetQuery();

		$this->queryType = self::QUERY_UPDATE;

		if ($table !== NULL) {
			$this->table($table, $alias);
		}

		return $this;
	}


	/**
	 * @param array<string, mixed> $data
	 * @throws Exceptions\QueryException
	 */
	public function set(array $data): static
	{
		$this->resetQuery();

		$this->queryType = self::QUERY_UPDATE;
		$this->params[self::PARAM_DATA] = $data + $this->params[self::PARAM_DATA];

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function delete(string|NULL $from = NULL, string|NULL $alias = NULL): static
	{
		$this->resetQuery();

		$this->queryType = self::QUERY_DELETE;

		if ($from !== NULL) {
			$this->table($from, $alias);
		}

		return $this;
	}


	/**
	 * @param array<int|string, string|int|Db\Sql> $returning
	 * @throws Exceptions\QueryException
	 */
	public function returning(array $returning): static
	{
		$this->resetQuery();

		$this->params[self::PARAM_RETURNING] = \array_merge($this->params[self::PARAM_RETURNING], $returning);

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function merge(string|NULL $into = NULL, string|NULL $alias = NULL): static
	{
		$this->resetQuery();

		$this->queryType = self::QUERY_MERGE;

		if ($into !== NULL) {
			$this->table($into, $alias);
		}

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function using(
		string|Db\Sql $dataSource,
		string|NULL $alias = NULL,
		string|Db\Sql|NULL $onCondition = NULL,
	): static
	{
		return $this->addTable(self::TABLE_TYPE_USING, $dataSource, $alias, $onCondition);
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function whenMatched(string|Db\Sql $then, string|Db\Sql|NULL $condition = NULL): static
	{
		$this->resetQuery();

		$this->params[self::PARAM_MERGE][] = [
			self::MERGE_WHEN_MATCHED,
			$then,
			$condition === NULL ? NULL : Condition::createAnd()->add($condition),
		];

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function whenNotMatched(string|Db\Sql $then, string|Db\Sql|NULL $condition = NULL): static
	{
		$this->resetQuery();

		$this->params[self::PARAM_MERGE][] = [
			self::MERGE_WHEN_NOT_MATCHED,
			$then,
			$condition === NULL ? NULL : Condition::createAnd()->add($condition),
		];

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function truncate(string|NULL $table = NULL): static
	{
		$this->resetQuery();

		$this->queryType = self::QUERY_TRUNCATE;

		if ($table !== NULL) {
			$this->table($table);
		}

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function with(
		string $as,
		string|Db\Sql $query,
		string|NULL $suffix = NULL,
		bool $notMaterialized = FALSE,
	): static
	{
		$this->resetQuery();

		$this->params[self::PARAM_WITH][self::WITH_QUERIES][$as] = $query;

		if ($suffix !== NULL) {
			$this->params[self::PARAM_WITH][self::WITH_QUERIES_SUFFIX][$as] = $suffix;
		}

		if ($notMaterialized) {
			$this->params[self::PARAM_WITH][self::WITH_QUERIES_NOT_MATERIALIZED][$as] = TRUE;
		}

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function recursive(): static
	{
		$this->resetQuery();

		$this->params[self::PARAM_WITH][self::WITH_RECURSIVE] = TRUE;

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function prefix(string $queryPrefix, mixed ...$params): static
	{
		$this->resetQuery();

		\array_unshift($params, $queryPrefix);
		$this->params[self::PARAM_PREFIX][] = $params;

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function suffix(string $querySuffix, mixed ...$params): static
	{
		$this->resetQuery();

		\array_unshift($params, $querySuffix);
		$this->params[self::PARAM_SUFFIX][] = $params;

		return $this;
	}


	public function has(string $param): bool
	{
		if (!\array_key_exists($param, self::DEFAULT_PARAMS)) {
			throw Exceptions\QueryException::nonExistingQueryParam($param, \array_keys(self::DEFAULT_PARAMS));
		}

		return $this->params[$param] !== self::DEFAULT_PARAMS[$param];
	}


	protected function get(string $param): mixed
	{
		if (!\array_key_exists($param, self::DEFAULT_PARAMS)) {
			throw Exceptions\QueryException::nonExistingQueryParam($param, \array_keys(self::DEFAULT_PARAMS));
		}

		return $this->params[$param];
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function reset(string $param): static
	{
		if (!\array_key_exists($param, self::DEFAULT_PARAMS)) {
			throw Exceptions\QueryException::nonExistingQueryParam($param, \array_keys(self::DEFAULT_PARAMS));
		}

		$this->resetQuery();

		$this->params[$param] = self::DEFAULT_PARAMS[$param];

		return $this;
	}


	protected function resetQuery(): void
	{
		$this->sqlDefinition = NULL;
		$this->dbQuery = NULL;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	private function checkAlias(mixed $data, string|NULL $alias): void
	{
		if ((($data instanceof Db\Sql)) && ($alias === NULL)) {
			throw Exceptions\QueryException::sqlMustHaveAlias();
		}
	}


	/**
	 * @throws Exceptions\QueryBuilderException
	 */
	public function getSqlDefinition(): Db\SqlDefinition
	{
		if ($this->sqlDefinition === NULL) {
			$this->sqlDefinition = $this->queryBuilder->createSqlDefinition($this->queryType, $this->params);
		}

		return $this->sqlDefinition;
	}


	public function toDbQuery(): Db\Query
	{
		if ($this->dbQuery === NULL) {
			$this->dbQuery = Db\SqlDefinition::createQuery($this->getSqlDefinition());
		}

		return $this->dbQuery;
	}


	public function __clone()
	{
		$this->resetQuery();

		foreach ($this->params[self::PARAM_ON_CONDITIONS] as $alias => $joinCondition) {
			$this->params[self::PARAM_ON_CONDITIONS][$alias] = clone $joinCondition;
		}

		if ($this->params[self::PARAM_WHERE] !== NULL) {
			$this->params[self::PARAM_WHERE] = clone $this->params[self::PARAM_WHERE];
		}

		if ($this->params[self::PARAM_HAVING] !== NULL) {
			$this->params[self::PARAM_HAVING] = clone $this->params[self::PARAM_HAVING];
		}
	}

}
