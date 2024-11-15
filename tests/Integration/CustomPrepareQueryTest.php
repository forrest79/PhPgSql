<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests\Integration;

use Forrest79\PhPgSql\Db;
use Forrest79\PhPgSql\Fluent;
use Tester;

require_once __DIR__ . '/TestCase.php';

/**
 * @testCase
 * @property-read Fluent\Connection $connection
 */
final class CustomPrepareQueryTest extends TestCase
{
	private Db\Query $lastQuery;


	public function testQuery(): void
	{
		$this->connection->query('SELECT 1');

		Tester\Assert::same('SELECT 1 + 1', $this->lastQuery->sql);
		Tester\Assert::same([], $this->lastQuery->params);
	}


	public function testExecute(): void
	{
		$this->connection->execute('SELECT 2');

		Tester\Assert::same('SELECT 2 + 1', $this->lastQuery->sql);
		Tester\Assert::same([], $this->lastQuery->params);
	}


	public function testPreparedStatement(): void
	{
		$this->connection->prepareStatement('SELECT 3')->execute();

		Tester\Assert::same('SELECT 3 + 1', $this->lastQuery->sql);
		Tester\Assert::same([], $this->lastQuery->params);
	}


	public function testAsyncQuery(): void
	{
		$this->connection->asyncQuery('SELECT 4');

		Tester\Assert::same('SELECT 4 + 1', $this->lastQuery->sql);
		Tester\Assert::same([], $this->lastQuery->params);
	}


	public function testAsyncExecute(): void
	{
		$this->connection->asyncExecute('SELECT 5');

		Tester\Assert::same('SELECT 5 + 1', $this->lastQuery->sql);
		Tester\Assert::same([], $this->lastQuery->params);
	}


	public function testAsyncPreparedStatement(): void
	{
		$this->connection->asyncPrepareStatement('SELECT 6')->execute();

		Tester\Assert::same('SELECT 6 + 1', $this->lastQuery->sql);
		Tester\Assert::same([], $this->lastQuery->params);
	}


	protected function createConnection(): Db\Connection
	{
		$connection = new class($this->getTestConnectionConfig()) extends Db\Connection {

			protected function prepareQuery(string|Db\Query $query): string|Db\Query
			{
				if ($query instanceof Db\Query) {
					$sql = $query->sql;
				} else {
					$sql = $query;
				}

				$sql .= ' + 1';

				return ($query instanceof Db\Query) ? new Db\Query($sql, $query->params) : $sql;
			}

		};

		$connection->addOnQuery(function (Db\Connection $connection, Db\Query $query): void {
			$this->lastQuery = $query;
		});

		return $connection;
	}

}

(new CustomPrepareQueryTest())->run();
