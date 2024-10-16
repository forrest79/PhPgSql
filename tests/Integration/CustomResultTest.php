<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests\Integration;

use Forrest79\PhPgSql\Db;
use PgSql;
use Tester;

require_once __DIR__ . '/TestCase.php';

/**
 * @testCase
 */
final class CustomResultTest extends TestCase
{

	public function testCustomResultFactory(): void
	{
		$expectedException = new \LogicException('There is no row.', -1);

		$this->connection->setResultFactory($this->createCustomResultFactory($expectedException));

		$this->connection->query('
			CREATE TABLE test(
				id serial,
  				name text
			);
		');

		$this->connection->query('INSERT INTO test(name) VALUES(?)', 'phpgsql');

		$result = $this->connection->query('SELECT id, name FROM test WHERE name = ?', 'phpgsql');

		$row = $result->fetchOrException();

		Tester\Assert::same('phpgsql', $row->name);

		Tester\Assert::exception(static function () use ($result): void {
			$result->fetchOrException();
		}, $expectedException::class, $expectedException->getMessage(), $expectedException->getCode());

		$result->free();
	}


	private function createCustomResultFactory(\LogicException $noRowException): Db\ResultFactory
	{
		return new class($noRowException) implements Db\ResultFactory {
			private \LogicException $noRowException;


			public function __construct(\LogicException $noRowException)
			{
				$this->noRowException = $noRowException;
			}


			/**
			 * @param array<int, string>|NULL $dataTypesCache
			 */
			public function create(
				PgSql\Result $queryResource,
				Db\Query $query,
				Db\RowFactory $rowFactory,
				Db\DataTypeParser $dataTypeParser,
				array|NULL $dataTypesCache,
			): Db\Result
			{
				$result = new class($queryResource, $query, $rowFactory, $dataTypeParser, $dataTypesCache) extends Db\Result {
					private \LogicException $noRowException;


					public function setNoRowException(\LogicException $noRowException): void
					{
						$this->noRowException = $noRowException;
					}


					public function fetchOrException(): Db\Row
					{
						return $this->fetch() ?? throw $this->noRowException;
					}

				};

				$result->setNoRowException($this->noRowException);

				return $result;
			}

		};
	}

}

(new CustomResultTest())->run();
