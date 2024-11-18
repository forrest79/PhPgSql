<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests\Integration;

use Tester;

require_once __DIR__ . '/TestCase.php';

/**
 * @testCase
 */
final class QueryTest extends TestCase
{

	public function testBoolParameters(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id integer,
  				bool_column bool
			);
		');

		$this->connection->query('INSERT INTO test(id, bool_column) VALUES(?, ?)', 1, TRUE);
		$this->connection->query('INSERT INTO test(id, bool_column) VALUES(?, ?)', 2, FALSE);

		$resultTrue = $this->connection->query('SELECT id, bool_column FROM test WHERE bool_column = ?', TRUE);

		$rowTrue = $resultTrue->fetch() ?? throw new \RuntimeException('No data from database were returned');

		Tester\Assert::same(1, $rowTrue->id);
		Tester\Assert::true($rowTrue->bool_column);

		$resultTrue->free();

		// ---

		$resultFalse = $this->connection->query('SELECT id, bool_column FROM test WHERE bool_column = ?', FALSE);

		$rowFalse = $resultFalse->fetch() ?? throw new \RuntimeException('No data from database were returned');

		Tester\Assert::same(2, $rowFalse->id);
		Tester\Assert::false($rowFalse->bool_column);

		$resultFalse->free();
	}

}

(new QueryTest())->run();
