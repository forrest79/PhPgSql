<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db;

use PgSql;

class ResultBuilder
{
	private Connection $connection;

	private Events $events;

	private ResultFactory|NULL $resultFactory = NULL;

	private RowFactory|NULL $rowFactory = NULL;

	private DataTypeParser|NULL $dataTypeParser = NULL;

	private DataTypeCache|NULL $dataTypeCache = NULL;


	public function __construct(Connection $connection, Events $events)
	{
		$this->connection = $connection;
		$this->events = $events;
	}


	public function build(PgSql\Result $resource, Query $query): Result
	{
		$result = $this->getResultFactory()->create(
			$resource,
			$query,
			$this->getRowFactory(),
			$this->getDataTypeParser(),
			$this->getDataTypesCache(),
		);

		$this->events->onResult($result);

		return $result;
	}


	public function setResultFactory(ResultFactory $resultFactory): static
	{
		$this->resultFactory = $resultFactory;

		return $this;
	}


	private function getResultFactory(): ResultFactory
	{
		if ($this->resultFactory === NULL) {
			$this->resultFactory = new ResultFactories\Basic();
		}

		return $this->resultFactory;
	}


	public function setRowFactory(RowFactory $rowFactory): void
	{
		$this->rowFactory = $rowFactory;
	}


	private function getRowFactory(): RowFactory
	{
		if ($this->rowFactory === NULL) {
			$this->rowFactory = new RowFactories\Basic();
		}

		return $this->rowFactory;
	}


	public function setDataTypeParser(DataTypeParser $dataTypeParser): void
	{
		$this->dataTypeParser = $dataTypeParser;
	}


	private function getDataTypeParser(): DataTypeParser
	{
		if ($this->dataTypeParser === NULL) {
			$this->dataTypeParser = new DataTypeParsers\Basic();
		}

		return $this->dataTypeParser;
	}


	public function setDataTypeCache(DataTypeCache $dataTypeCache): void
	{
		$this->dataTypeCache = $dataTypeCache;
	}


	/**
	 * @return array<int, string>|NULL
	 */
	private function getDataTypesCache(): array|NULL
	{
		return $this->dataTypeCache?->load($this->connection) ?? NULL;
	}

}
