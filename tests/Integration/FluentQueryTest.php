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
final class FluentQueryTest extends TestCase
{

	public function testSelectBoolNull(): void
	{
		$query = $this->connection->createQuery()->select(['is_true' => TRUE, 'is_false' => FALSE, 'is_null' => NULL]);

		$row = $query->fetch();
		if ($row === NULL) {
			throw new \RuntimeException('No data from database were returned');
		}

		Tester\Assert::true($row->is_true);
		Tester\Assert::false($row->is_false);
		Tester\Assert::null($row->is_null);
	}


	protected function createConnection(): Db\Connection
	{
		return new Fluent\Connection($this->getTestConnectionConfig());
	}

}

(new FluentQueryTest())->run();
