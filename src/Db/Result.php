<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db;

use PgSql;

/**
 * @implements \IteratorAggregate<int, Row>
 */
class Result implements ColumnValueParser, \Countable, \IteratorAggregate
{
	protected PgSql\Result $queryResource;

	private Query $query;

	private RowFactory $rowFactory;

	private DataTypeParser $dataTypeParser;

	/** @var array<int, string>|NULL */
	private array|NULL $dataTypesCache;

	/** @template T of Row @var \Closure(Row): void|NULL */
	private \Closure|NULL $rowFetchMutator = NULL;

	/** @var array<string, callable> */
	private array $columnsFetchMutator = [];

	private int|NULL $affectedRows = NULL;

	/** @var array<string, string>|NULL */
	private array|NULL $columnsDataTypes = NULL;

	/** @var array<string, bool> */
	private array $parsedColumns = [];


	/**
	 * @param array<int, string>|NULL $dataTypesCache
	 */
	public function __construct(
		PgSql\Result $queryResource,
		Query $query,
		RowFactory $rowFactory,
		DataTypeParser $dataTypeParser,
		array|NULL $dataTypesCache,
	)
	{
		$this->queryResource = $queryResource;
		$this->query = $query;
		$this->rowFactory = $rowFactory;
		$this->dataTypeParser = $dataTypeParser;
		$this->dataTypesCache = $dataTypesCache;
	}


	public function setRowFactory(RowFactory $rowFactory): static
	{
		$this->rowFactory = $rowFactory;

		return $this;
	}


	/**
	 * @template T of Row
	 * @param \Closure(T): void $rowFetchMutator
	 */
	public function setRowFetchMutator(\Closure $rowFetchMutator): static
	{
		$this->rowFetchMutator = $rowFetchMutator;

		return $this;
	}


	/**
	 * @param non-empty-array<string, callable> $columnsFetchMutator
	 */
	public function setColumnsFetchMutator(array $columnsFetchMutator): static
	{
		$this->columnsFetchMutator = $columnsFetchMutator;

		return $this;
	}


	/**
	 * @deprecated Use fetchIterator() method.
	 */
	public function getIterator(): RowIterator
	{
		\trigger_error('Use fetchIterator() method.', \E_USER_DEPRECATED);
		return new RowIterator($this);
	}


	public function free(): bool
	{
		return \pg_free_result($this->queryResource);
	}


	public function getResource(): PgSql\Result
	{
		return $this->queryResource;
	}


	public function seek(int $row): bool
	{
		return \pg_result_seek($this->queryResource, $row);
	}


	public function count(): int
	{
		/** @phpstan-var int<0, max> */
		return $this->getRowCount();
	}


	public function getRowCount(): int
	{
		return \pg_num_rows($this->queryResource);
	}


	public function hasRows(): bool
	{
		return $this->getRowCount() > 0;
	}


	public function fetch(): Row|NULL
	{
		$data = \pg_fetch_assoc($this->queryResource);
		if ($data === FALSE) {
			return NULL;
		}

		$this->detectColumnDataTypes();
		$row = $this->rowFactory->create($this, $data);

		if ($this->rowFetchMutator !== NULL) {
			call_user_func($this->rowFetchMutator, $row);
		}

		return $row;
	}


	/**
	 * Like fetch(), but returns only first field.
	 *
	 * @return mixed value on success, NULL if no next record
	 */
	public function fetchSingle(): mixed
	{
		$row = $this->fetch();
		if ($row === NULL) {
			return NULL;
		}

		$columns = $this->getColumns();
		$firstColumn = $columns[0];

		return isset($this->columnsFetchMutator[$firstColumn])
			? call_user_func($this->columnsFetchMutator[$firstColumn], $row[$firstColumn])
			: $row[$firstColumn];
	}


	/**
	 * Fetches all records from table.
	 *
	 * @return list<Row>
	 */
	public function fetchAll(int|NULL $offset = NULL, int|NULL $limit = NULL): array
	{
		$limit = $limit ?? -1;
		$this->seek($offset ?? 0);
		$row = $this->fetch();
		if ($row === NULL) {
			return []; // empty result set
		}

		$data = [];
		do {
			if ($limit === 0) {
				break;
			}
			$limit--;

			$data[] = $row;

			$row = $this->fetch();
		} while ($row !== NULL);

		return $data;
	}


	/**
	 * Fetches all records from table and returns associative tree.
	 * Examples:
	 * - associative descriptor: col1[]col2
	 *   builds a tree:          $tree[$val1][$index][$val2] = Row
	 * - associative descriptor: col1|col2=col3
	 *   builds a tree:          $tree[$val1][$val2] = $val3
	 * - associative descriptor: col1|col2=[]
	 *   builds a tree:          $tree[$val1][$val2] = Row::toArray()
	 *
	 * @return array<int|string, mixed>
	 * @throws Exceptions\ResultException
	 * @credit dibi (https://dibiphp.com/) | David Grudl
	 */
	public function fetchAssoc(string $assocDesc): array
	{
		$parts = \preg_split('#(\[\]|=|\|)#', $assocDesc, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);
		if (($parts === FALSE) || ($parts === [])) {
			throw Exceptions\ResultException::fetchAssocBadDescriptor($assocDesc);
		}

		$firstPart = \reset($parts);
		$lastPart = \end($parts);
		if (($firstPart === '=') || ($firstPart === '|') || ($lastPart === '=') || ($lastPart === '|')) {
			throw Exceptions\ResultException::fetchAssocBadDescriptor($assocDesc);
		}

		$this->seek(0);

		$data = [];
		$columnsChecked = FALSE;

		// make associative tree
		while (($row = $this->fetch()) !== NULL) {
			if (!$columnsChecked) {
				foreach ($parts as $checkPart) {
					if (($checkPart !== '[]') && ($checkPart !== '=') && ($checkPart !== '|') && !$row->hasColumn($checkPart)) {
						throw Exceptions\ResultException::fetchAssocNoColumn($checkPart, $assocDesc);
					}
				}
				$columnsChecked = TRUE;
			}

			$x = &$data;

			// iterative deepening
			foreach ($parts as $i => $part) {
				if ($part === '[]') { // indexed-array node
					$x = &$x[];
				} else if ($part === '=') { // "value" node
					if ($parts[$i + 1] === '[]') { // get Row as array
						$x = $row->toArray();
					} else { // get concrete Row column
						$key = $parts[$i + 1];
						$x = isset($this->columnsFetchMutator[$key]) ? call_user_func($this->columnsFetchMutator[$key], $row->$key) : $row->$key;
					}

					continue 2;
				} else if ($part !== '|') { // associative-array node
					if (isset($this->columnsFetchMutator[$part])) {
						$val = call_user_func($this->columnsFetchMutator[$part], $row->$part);
						if (($val !== NULL) && !\is_scalar($val)) {
							throw Exceptions\ResultException::fetchMutatorBadReturnType($part, $val);
						}
					} else {
						$val = $row->$part;
						if (($val !== NULL) && !\is_scalar($val)) {
							throw Exceptions\ResultException::fetchAssocOnlyScalarAsKey($assocDesc, $part, $val);
						}
					}

					$x = &$x[(string) $val];
				}
			}

			if ($x === NULL) { // build leaf
				$x = $row;
			}
		}

		unset($x);
		return $data;
	}


	/**
	 * Fetches all records from table like $key => $value pairs.
	 *
	 * @return array<int|string, mixed>
	 * @throws Exceptions\ResultException
	 * @credit dibi (https://dibiphp.com/) | David Grudl
	 */
	public function fetchPairs(string|NULL $key = NULL, string|NULL $value = NULL): array
	{
		$this->seek(0);
		$row = $this->fetch();
		if ($row === NULL) {
			return []; // empty result set
		}

		$data = [];

		if ($value === NULL) {
			if ($key !== NULL) {
				throw Exceptions\ResultException::fetchPairsBadColumns();
			}

			// autodetect
			$tmp = \array_keys($row->toArray());
			$key = $tmp[0];
			if (\count($row) < 2) { // indexed-array
				$fetchMutator = $this->columnsFetchMutator[$key] ?? NULL;
				do {
					$data[] = $fetchMutator !== NULL ? call_user_func($fetchMutator, $row[$key]) : $row[$key];
					$row = $this->fetch();
				} while ($row !== NULL);

				return $data;
			}

			$value = $tmp[1];
		} else {
			if ($row->hasColumn($value) === FALSE) {
				throw Exceptions\ResultException::noColumn($value);
			}

			if ($key === NULL) { // indexed-array
				$fetchMutator = $this->columnsFetchMutator[$value] ?? NULL;
				do {
					$data[] = $fetchMutator !== NULL ? call_user_func($fetchMutator, $row[$value]) : $row[$value];
					$row = $this->fetch();
				} while ($row !== NULL);

				return $data;
			}

			if ($row->hasColumn($key) === FALSE) {
				throw Exceptions\ResultException::noColumn($key);
			}
		}

		$fetchMutatorKey = $this->columnsFetchMutator[$key] ?? NULL;
		$fetchMutatorValue = $this->columnsFetchMutator[$value] ?? NULL;

		do {
			if ($fetchMutatorKey !== NULL) {
				$keyValue = call_user_func($fetchMutatorKey, $row[$key]);
				if (($keyValue !== NULL) && !\is_scalar($keyValue)) {
					throw Exceptions\ResultException::fetchMutatorBadReturnType($key, $keyValue);
				}
			} else {
				$keyValue = $row[$key];
				if (($keyValue !== NULL) && !\is_scalar($keyValue)) {
					throw Exceptions\ResultException::fetchPairsOnlyScalarAsKey($key, $keyValue);
				}
			}

			$data[$keyValue] = $fetchMutatorValue !== NULL ? call_user_func($fetchMutatorValue, $row[$value]) : $row[$value];

			$row = $this->fetch();
		} while ($row !== NULL);

		return $data;
	}


	/**
	 * @return RowIterator<int, Row>
	 */
	public function fetchIterator(): RowIterator
	{
		return new RowIterator($this);
	}


	public function getQuery(): Query
	{
		return $this->query;
	}


	public function getAffectedRows(): int
	{
		if ($this->affectedRows === NULL) {
			$this->affectedRows = \pg_affected_rows($this->queryResource);
		}

		return $this->affectedRows;
	}


	public function hasAffectedRows(): bool
	{
		return $this->getAffectedRows() > 0;
	}


	/**
	 * @throws Exceptions\ResultException
	 */
	public function getColumnType(string $column): string
	{
		$type = $this->getColumnsDataTypes()[$column] ?? NULL;
		if ($type === NULL) {
			throw Exceptions\ResultException::noColumn($column);
		}

		return $type;
	}


	/**
	 * @return list<string>
	 */
	public function getColumns(): array
	{
		return \array_keys($this->getColumnsDataTypes());
	}


	public function parseColumnValue(string $column, mixed $rawValue): mixed
	{
		\assert(($rawValue === NULL) || \is_string($rawValue)); // database result all values as string or NULL
		$value = $this->dataTypeParser->parse($this->getColumnType($column), $rawValue);

		$this->parsedColumns[$column] = TRUE;

		return $value;
	}


	/**
	 * @return array<string, string>
	 */
	private function getColumnsDataTypes(): array
	{
		$this->detectColumnDataTypes();
		\assert($this->columnsDataTypes !== NULL);

		return $this->columnsDataTypes;
	}


	private function detectColumnDataTypes(): void
	{
		if ($this->columnsDataTypes === NULL) {
			$this->columnsDataTypes = [];
			$fieldsCnt = \pg_num_fields($this->queryResource);
			for ($i = 0; $i < $fieldsCnt; $i++) {
				$name = \pg_field_name($this->queryResource, $i);

				if (isset($this->columnsDataTypes[$name])) {
					throw Exceptions\ResultException::columnNameIsAlreadyInUse($name);
				}

				if ($this->dataTypesCache === NULL) {
					$type = \pg_field_type($this->queryResource, $i);
				} else {
					$typeOid = \pg_field_type_oid($this->queryResource, $i);
					if (!isset($this->dataTypesCache[$typeOid])) {
						throw Exceptions\ResultException::noOidInDataTypeCache($typeOid);
					}

					$type = $this->dataTypesCache[$typeOid];
				}

				$this->columnsDataTypes[$name] = $type;
			}
		}
	}


	/**
	 * @return array<string, bool>|NULL NULL = no column was used
	 */
	public function getParsedColumns(): array|NULL
	{
		return $this->parsedColumns === []
			? NULL
			: $this->parsedColumns + \array_fill_keys($this->getColumns(), FALSE);
	}

}
