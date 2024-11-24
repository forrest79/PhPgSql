<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Fluent;

use Forrest79\PhPgSql\Db;

class QueryExecute extends Query implements \Countable
{
	private Db\Connection $connection;

	private Db\Result|NULL $result = NULL;

	/** @template T of Db\Row @var \Closure(T): void|NULL */
	private \Closure|NULL $rowFetchMutator = NULL;

	/** @var array<string, callable> */
	private array $columnsFetchMutator = [];


	public function __construct(QueryBuilder $queryBuilder, Db\Connection $connection)
	{
		$this->connection = $connection;
		parent::__construct($queryBuilder);
	}


	/**
	 * @template T of Db\Row
	 * @param \Closure(T): void $rowFetchMutator
	 */
	public function setRowFetchMutator(\Closure $rowFetchMutator): static
	{
		$this->rowFetchMutator = $rowFetchMutator;

		if ($this->result !== NULL) {
			$this->result->setRowFetchMutator($rowFetchMutator);
		}

		return $this;
	}


	/**
	 * @param non-empty-array<string, callable> $columnsFetchMutator
	 */
	public function setColumnsFetchMutator(array $columnsFetchMutator): static
	{
		$this->columnsFetchMutator = $columnsFetchMutator;

		if ($this->result !== NULL) {
			$this->result->setColumnsFetchMutator($columnsFetchMutator);
		}

		return $this;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	protected function resetQuery(): void
	{
		if ($this->result !== NULL) {
			throw Exceptions\QueryException::cantUpdateQueryAfterExecute();
		}

		parent::resetQuery();
	}


	/**
	 * @throws Db\Exceptions\ConnectionException
	 * @throws Db\Exceptions\QueryException
	 * @throws Exceptions\QueryException
	 * @throws Exceptions\QueryBuilderException
	 */
	public function execute(): Db\Result
	{
		if ($this->result === NULL) {
			$this->result = $this->connection->query($this);

			if ($this->rowFetchMutator !== NULL) {
				$this->result->setRowFetchMutator($this->rowFetchMutator);
			}

			if ($this->columnsFetchMutator !== []) {
				$this->result->setColumnsFetchMutator($this->columnsFetchMutator);
			}
		}

		return $this->result;
	}


	/**
	 * @throws Db\Exceptions\ConnectionException
	 * @throws Db\Exceptions\QueryException
	 * @throws Exceptions\QueryException
	 * @throws Exceptions\QueryBuilderException
	 */
	public function reexecute(): Db\Result
	{
		$this->releaseResult();
		return $this->execute();
	}


	private function releaseResult(bool $freeResult = TRUE): void
	{
		if ($freeResult && ($this->result !== NULL)) {
			$this->free();
		}

		$this->result = NULL;
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	public function free(): bool
	{
		if ($this->result === NULL) {
			throw Exceptions\QueryException::youMustExecuteQueryBeforeThat();
		}

		return $this->result->free();
	}


	/**
	 * @throws Db\Exceptions\ConnectionException
	 * @throws Db\Exceptions\QueryException
	 * @throws Exceptions\QueryException
	 * @throws Exceptions\QueryBuilderException
	 */
	public function count(): int
	{
		/** @phpstan-var int<0, max> */
		return $this->execute()->getRowCount();
	}


	/**
	 * @throws Db\Exceptions\ConnectionException
	 * @throws Db\Exceptions\QueryException
	 * @throws Exceptions\QueryException
	 * @throws Exceptions\QueryBuilderException
	 */
	public function getAffectedRows(): int
	{
		return $this->execute()->getAffectedRows();
	}


	/**
	 * @throws Db\Exceptions\ConnectionException
	 * @throws Db\Exceptions\QueryException
	 * @throws Exceptions\QueryException
	 * @throws Exceptions\QueryBuilderException
	 */
	public function fetch(): Db\Row|NULL
	{
		return $this->execute()->fetch();
	}


	/**
	 * @return mixed value on success, NULL if no next record
	 * @throws Db\Exceptions\ConnectionException
	 * @throws Db\Exceptions\QueryException
	 * @throws Exceptions\QueryException
	 * @throws Exceptions\QueryBuilderException
	 */
	public function fetchSingle(): mixed
	{
		return $this->execute()->fetchSingle();
	}


	/**
	 * @return list<Db\Row>
	 * @throws Db\Exceptions\ConnectionException
	 * @throws Db\Exceptions\QueryException
	 * @throws Exceptions\QueryException
	 * @throws Exceptions\QueryBuilderException
	 */
	public function fetchAll(int|NULL $offset = NULL, int|NULL $limit = NULL): array
	{
		return $this->execute()->fetchAll($offset, $limit);
	}


	/**
	 * @return array<int|string, mixed>
	 * @throws Db\Exceptions\ConnectionException
	 * @throws Db\Exceptions\QueryException
	 * @throws Exceptions\QueryException
	 * @throws Exceptions\QueryBuilderException
	 */
	public function fetchAssoc(string $assoc): array
	{
		return $this->execute()->fetchAssoc($assoc);
	}


	/**
	 * @return array<int|string, mixed>
	 * @throws Db\Exceptions\ConnectionException
	 * @throws Db\Exceptions\QueryException
	 * @throws Exceptions\QueryException
	 * @throws Exceptions\QueryBuilderException
	 */
	public function fetchPairs(string|NULL $key = NULL, string|NULL $value = NULL): array
	{
		return $this->execute()->fetchPairs($key, $value);
	}


	/**
	 * @return Db\RowIterator<int, Db\Row>
	 * @throws Db\Exceptions\ConnectionException
	 * @throws Db\Exceptions\QueryException
	 * @throws Exceptions\QueryException
	 * @throws Exceptions\QueryBuilderException
	 */
	public function fetchIterator(): Db\RowIterator
	{
		/**	@phpstan-var Db\RowIterator<int, Db\Row> */
		return $this->execute()->fetchIterator();
	}


	/**
	 * @throws Db\Exceptions\ConnectionException
	 * @throws Db\Exceptions\QueryException
	 * @throws Exceptions\QueryException
	 * @throws Exceptions\QueryBuilderException
	 */
	public function asyncExecute(): Db\AsyncQuery
	{
		return $this->connection->asyncQuery($this);
	}


	public function __clone()
	{
		$this->releaseResult(FALSE); // must be before parent clone - we need to allow resetQuery() first

		parent::__clone();
	}

}
