<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests\Integration;

use Forrest79\PhPgSql\Db;
use Tester;

require_once __DIR__ . '/TestCase.php';

/**
 * @testCase
 */
final class AsyncTest extends TestCase
{

	public function testFetch(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
  				name text
			);
		');

		$this->connection->query('INSERT INTO test(name) SELECT \'name\' || generate_series FROM generate_series(2, 1, -1)');

		$asyncQuery1 = $this->connection->asyncQuery('SELECT id, name FROM test WHERE id = ?', 1);
		$result1 = $asyncQuery1->getNextResult();

		$row1 = $result1->fetch();
		if ($row1 === NULL) {
			throw new \RuntimeException('No data from database were returned');
		}

		Tester\Assert::same(['id' => 1, 'name' => 'name2'], $row1->toArray());

		$asyncQuery2 = $this->connection->asyncQueryArgs('SELECT id, name FROM test WHERE id = ?', [2]);

		$result2 = $asyncQuery2->getNextResult();

		Tester\Assert::false($this->connection->isBusy());

		$row2 = $result2->fetch();
		if ($row2 === NULL) {
			throw new \RuntimeException('No data from database were returned');
		}

		Tester\Assert::same(['id' => 2, 'name' => 'name1'], $row2->toArray());

		$result1->free();
		$result2->free();
	}


	public function testQueryEvent(): void
	{
		$queryDuration = 0;

		$this->connection->addOnQuery(static function (Db\Connection $connection, Db\Query $query, float|NULL $timeNs) use (&$queryDuration): void {
			$queryDuration = $timeNs;
		});

		$this->connection->asyncQuery('SELECT 1');

		Tester\Assert::null($queryDuration);
	}


	public function testExecuteEvent(): void
	{
		$wasEvent = FALSE;

		$this->connection->addOnQuery(static function () use (&$wasEvent): void {
			$wasEvent = TRUE;
		});

		$this->connection->asyncExecute('SELECT 1')->completeAsyncExecute();

		Tester\Assert::true($wasEvent);
	}


	public function testExecute(): void
	{
		Tester\Assert::noError(function (): void {
			$this->connection->asyncExecute('SELECT 1')->completeAsyncExecute();
		});
	}


	public function testMoreExecutesWithoutCompletePrevious(): void
	{
		Tester\Assert::exception(function (): void {
			$this->connection->asyncExecute('SELECT 1')->asyncExecute('SELECT 2');
		}, Db\Exceptions\ConnectionException::class, NULL, Db\Exceptions\ConnectionException::ASYNC_QUERY_SENT_FAILED);
	}


	public function testCompleteExecuteWithQuery(): void
	{
		Tester\Assert::exception(function (): void {
			$this->connection->asyncQuery('SELECT 1');
			$this->connection->completeAsyncExecute();
		}, Db\Exceptions\ConnectionException::class, NULL, Db\Exceptions\ConnectionException::ASYNC_NO_EXECUTE_IS_SENT);
	}


	public function testGetQueryResultAfterExecute(): void
	{
		$asyncQuery = $this->connection->asyncQuery('SELECT 1');

		Tester\Assert::exception(function () use ($asyncQuery): void {
			$this->connection->asyncExecute('SELECT 2');
			$asyncQuery->getNextResult();
		}, Db\Exceptions\ConnectionException::class, NULL, Db\Exceptions\ConnectionException::ASYNC_QUERY_SENT_FAILED);
	}


	public function testCancelAsyncQuery(): void
	{
		$asyncQuery = $this->connection->asyncQuery('SELECT 1');

		$this->connection->cancelAsyncQuery();

		Tester\Assert::exception(static function () use ($asyncQuery): void {
			$asyncQuery->getNextResult();
		}, Db\Exceptions\ConnectionException::class, NULL, Db\Exceptions\ConnectionException::ASYNC_NO_QUERY_IS_SENT);
	}


	public function testMoreAsyncQueries(): void
	{
		$asyncQuery1 = $this->connection->asyncQuery('SELECT 1');
		$result1 = $asyncQuery1->getNextResult();
		Tester\Assert::same(1, $result1->fetchSingle());

		$asyncQuery2 = $this->connection->asyncQuery('SELECT 2');
		$result2 = $asyncQuery2->getNextResult();
		Tester\Assert::same(2, $result2->fetchSingle());
	}


	public function testMoreAsyncQueriesWithoutCompletePrevious(): void
	{
		$this->connection->asyncQuery('SELECT 1');

		Tester\Assert::exception(function (): void {
			$this->connection->asyncQuery('SELECT 2');
		}, Db\Exceptions\ConnectionException::class, NULL, Db\Exceptions\ConnectionException::ASYNC_QUERY_SENT_FAILED);
	}


	public function testMoreAsyncQueriesInOne(): void
	{
		$asyncQuery1 = $this->connection->asyncQuery('SELECT 1; SELECT 2');

		$result1 = $asyncQuery1->getNextResult();
		$result2 = $asyncQuery1->getNextResult();

		Tester\Assert::same(1, $result1->fetchSingle());
		Tester\Assert::same(2, $result2->fetchSingle());

		Tester\Assert::exception(static function () use ($asyncQuery1): void {
			$asyncQuery1->getNextResult();
		}, Db\Exceptions\ResultException::class, NULL, Db\Exceptions\ResultException::NO_OTHER_ASYNC_RESULT);

		$asyncQuery2 = $this->connection->asyncQuery('SELECT 3');

		Tester\Assert::exception(static function () use ($asyncQuery1): void {
			$asyncQuery1->getNextResult();
		}, Db\Exceptions\ConnectionException::class, NULL, Db\Exceptions\ConnectionException::ASYNC_ANOTHER_QUERY_IS_RUNNING);

		$result3 = $asyncQuery2->getNextResult();
		Tester\Assert::same(3, $result3->fetchSingle());

		$this->connection->asyncExecute('SELECT 4');

		Tester\Assert::exception(static function () use ($asyncQuery1): void {
			$asyncQuery1->getNextResult();
		}, Db\Exceptions\ConnectionException::class, NULL, Db\Exceptions\ConnectionException::ASYNC_ANOTHER_QUERY_IS_RUNNING);
	}


	public function testIsBusy(): void
	{
		$this->connection->asyncExecute('SELECT pg_sleep(1)');
		Tester\Assert::true($this->connection->isBusy());

		$this->connection->completeAsyncExecute();
		Tester\Assert::false($this->connection->isBusy());
	}


	public function testError(): void
	{
		$asyncQuery = $this->connection->asyncQuery('SELECT bad_column');
		Tester\Assert::exception(static function () use ($asyncQuery): void {
			$asyncQuery->getNextResult();
		}, Db\Exceptions\QueryException::class, NULL, Db\Exceptions\QueryException::ASYNC_QUERY_FAILED);

		$this->connection->asyncExecute('SELECT bad_column');
		Tester\Assert::exception(function (): void {
			$this->connection->completeAsyncExecute();
		}, Db\Exceptions\QueryException::class, NULL, Db\Exceptions\QueryException::ASYNC_QUERY_FAILED);
	}


	protected function createConnection(): Db\Connection
	{
		return new Db\Connection($this->getTestConnectionConfig(), FALSE);
	}

}

(new AsyncTest())->run();
