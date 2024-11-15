<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Tests\Integration;

use Forrest79\PhPgSql\Db;
use Tester;

require_once __DIR__ . '/TestCase.php';

/**
 * @testCase
 */
final class BasicTest extends TestCase
{

	public function testGetLastErrorWithNoConnection(): void
	{
		Tester\Assert::same('unknown error', $this->connection->getLastError());
	}


	public function testSetErrorVerbosity(): void
	{
		// PGSQL_ERRORS_DEFAULT
		Tester\Assert::exception(function (): void {
			$this->connection->query('SELECT bad_column');
		}, Db\Exceptions\QueryException::class, '#ERROR:  column "bad_column" does not exist#', Db\Exceptions\QueryException::QUERY_FAILED);

		// PGSQL_ERRORS_TERSE
		$this->connection->setErrorVerbosity(\PGSQL_ERRORS_TERSE);
		Tester\Assert::exception(function (): void {
			$this->connection->query('SELECT bad_column');
		}, Db\Exceptions\QueryException::class, '#ERROR:  column "bad_column" does not exist#', Db\Exceptions\QueryException::QUERY_FAILED);

		// PGSQL_ERRORS_VERBOSE
		$this->connection->setErrorVerbosity(\PGSQL_ERRORS_VERBOSE);
		Tester\Assert::exception(function (): void {
			$this->connection->query('SELECT bad_column');
		}, Db\Exceptions\QueryException::class, '#ERROR:  42703: column "bad_column" does not exist#', Db\Exceptions\QueryException::QUERY_FAILED);
	}


	public function testSetErrorVerbosityOnConnect(): void
	{
		$connection = $this->createConnection();

		Tester\Assert::false($connection->isConnected());

		$connection->setErrorVerbosity(\PGSQL_ERRORS_VERBOSE);

		Tester\Assert::exception(static function () use ($connection): void {
			$connection->query('SELECT bad_column');
		}, Db\Exceptions\QueryException::class, '#ERROR:  42703: column "bad_column" does not exist#', Db\Exceptions\QueryException::QUERY_FAILED);

		$connection->close();
	}


	public function testPing(): void
	{
		Tester\Assert::true($this->connection->ping());
	}


	public function testConnectedResource(): void
	{
		Tester\Assert::notEqual(NULL, $this->connection->getResource());
	}


	public function testConnectionNoConfig(): void
	{
		$this->connection->setConnectionConfig('');

		Tester\Assert::exception(function (): void {
			$this->connection->connect();
		}, Db\Exceptions\ConnectionException::class, NULL, Db\Exceptions\ConnectionException::NO_CONFIG);
	}


	public function testConnectionForceNew(): void
	{
		$this->connection->setConnectForceNew(TRUE);
		Tester\Assert::true($this->connection->ping());
	}


	public function testConnectionAsync(): void
	{
		$this->connection->setConnectAsync(TRUE);
		$this->connection->setConnectAsyncWaitSeconds(10);
		Tester\Assert::true($this->connection->ping());
	}


	public function testFailedConnection(): void
	{
		$this->connection->setConnectionConfig(\str_replace('user=', 'user=non-existing-user-', $this->getConfig()));

		Tester\Assert::exception(function (): void {
			$this->connection->ping();
		}, Db\Exceptions\ConnectionException::class, NULL, Db\Exceptions\ConnectionException::CONNECTION_FAILED);
	}


	public function testChangeConnectionSettingsAfterConnected(): void
	{
		Tester\Assert::true($this->connection->ping());

		Tester\Assert::exception(function (): void {
			$this->connection->setConnectionConfig('');
		}, Db\Exceptions\ConnectionException::class, NULL, Db\Exceptions\ConnectionException::CANT_CHANGE_WHEN_CONNECTED);

		Tester\Assert::exception(function (): void {
			$this->connection->setConnectForceNew();
		}, Db\Exceptions\ConnectionException::class, NULL, Db\Exceptions\ConnectionException::CANT_CHANGE_WHEN_CONNECTED);

		Tester\Assert::exception(function (): void {
			$this->connection->setConnectAsync();
		}, Db\Exceptions\ConnectionException::class, NULL, Db\Exceptions\ConnectionException::CANT_CHANGE_WHEN_CONNECTED);

		Tester\Assert::exception(function (): void {
			$this->connection->setConnectAsyncWaitSeconds(5);
		}, Db\Exceptions\ConnectionException::class, NULL, Db\Exceptions\ConnectionException::CANT_CHANGE_WHEN_CONNECTED);

		$this->connection->close();

		$this->connection->setConnectionConfig('');
		$this->connection->setConnectForceNew();
		$this->connection->setConnectAsync();
		$this->connection->setConnectAsyncWaitSeconds(5);
	}


	public function testConnectionEvents(): void
	{
		$hasConnect = FALSE;
		$hasClose = FALSE;
		$hasQuery = FALSE;
		$hasExecute = FALSE;
		$hasQueryResult = FALSE;
		$hasExecuteResult = FALSE;
		$queryDuration = 0;

		$this->connection->addOnConnect(static function (Db\Connection $connection) use (&$hasConnect): void {
			$hasConnect = $connection->query('SELECT TRUE')->fetchSingle();
		});

		$this->connection->addOnQuery(static function (
			Db\Connection $connection,
			Db\Query $query,
			float $duration,
		) use (
			&$hasQuery,
			&$hasExecute,
			&$queryDuration,
		): void {
			if ($query->sql === 'SELECT 1') {
				$hasQuery = TRUE;
			} else if ($query->sql === 'SELECT 2') {
				$hasExecute = TRUE;
			}

			$queryDuration = $duration;
		});

		$this->connection->addOnResult(static function (
			Db\Connection $connection,
			Db\Result $result,
		) use (
			&$hasQueryResult,
			&$hasExecuteResult,
		): void {
			if ($result->getQuery()->sql === 'SELECT 1') {
				$hasQueryResult = TRUE;
			} else if ($result->getQuery()->sql === 'SELECT 2') {
				$hasExecuteResult = TRUE;
			}
		});

		$this->connection->addOnClose(static function () use (&$hasClose): void {
			$hasClose = TRUE;
		});

		Tester\Assert::same(1, $this->connection->query('SELECT 1')->fetchSingle());

		$this->connection->execute('SELECT 2');

		$this->connection->close();

		Tester\Assert::true($hasConnect);
		Tester\Assert::true($hasClose);
		Tester\Assert::true($hasQuery);
		Tester\Assert::true($hasExecute);
		Tester\Assert::true($queryDuration > 0);
		Tester\Assert::true($hasQueryResult);
		Tester\Assert::false($hasExecuteResult);
	}


	public function testFailedQuery(): void
	{
		Tester\Assert::exception(function (): void {
			try {
				$this->connection->query('SELECT bad_column');
			} catch (Db\Exceptions\QueryException $e) {
				Tester\Assert::true($e->getQuery() instanceof Db\Query);
				throw $e;
			}
		}, Db\Exceptions\QueryException::class, NULL, Db\Exceptions\QueryException::QUERY_FAILED);
	}


	public function testPassParamToQuery(): void
	{
		Tester\Assert::exception(function (): void {
			$query = Db\Sql\Query::create('SELECT 1');
			$this->connection->query($query, 1);
		}, Db\Exceptions\QueryException::class, NULL, Db\Exceptions\QueryException::CANT_PASS_PARAMS);
	}


	public function testQueryWithParams(): void
	{
		$result = $this->connection->query(
			'SELECT generate_series FROM ?',
			Db\Sql\Expression::createArgs('generate_series(?::integer, ?::integer, ?::integer)', [2, 1, -1]),
		);

		$rows = $result->fetchAssoc('generate_series');

		Tester\Assert::same(2, \count($rows));

		Tester\Assert::same(2, $rows[2]->generate_series);
		Tester\Assert::same(1, $rows[1]->generate_series);

		$result->free();
	}


	public function testOnlyOneQueryForPreparedStatement(): void
	{
		Tester\Assert::exception(
			function (): void {
				$this->connection->query('SELECT 1 WHERE 1 = ?; SELECT 2 WHERE 2 = ?', 1, 2);
			},
			Db\Exceptions\QueryException::class,
			'#^.+\[ERROR:  cannot insert multiple commands into a prepared statement].+\.$#',
			Db\Exceptions\QueryException::QUERY_FAILED,
		);
	}


	public function testExecute(): void
	{
		Tester\Assert::noError(function (): void {
			$this->connection->execute('SELECT 1; SELECT 2');
		});
	}


	public function testFailedExecute(): void
	{
		Tester\Assert::exception(function (): void {
			$this->connection->execute('SELECT bad_column');
		}, Db\Exceptions\QueryException::class, NULL, Db\Exceptions\QueryException::QUERY_FAILED);
	}


	public function testResultGetResource(): void
	{
		$result = $this->connection->query('SELECT 1');

		Tester\Assert::same(1, \pg_num_fields($result->getResource()));
	}


	public function testResultGetQuery(): void
	{
		$result = $this->connection->query('SELECT TRUE WHERE 1 = ?', 2);

		$query = $result->getQuery();

		Tester\Assert::same('SELECT TRUE WHERE 1 = $1', $query->sql);
		Tester\Assert::same([2], $query->params);
	}


	public function testRowFrom(): void
	{
		$row = Db\Row::from(['column1' => 1, 'column2' => 'text', 'column3' => TRUE, 'column4' => NULL]);

		Tester\Assert::same(1, $row->column1);
		Tester\Assert::same('text', $row->column2);
		Tester\Assert::same(TRUE, $row->column3);
		Tester\Assert::same(NULL, $row->column4);

		Tester\Assert::true(isset($row->column3));
		Tester\Assert::false(isset($row->column4));
		Tester\Assert::true($row->hasColumn('column4'));

		$blankRow = Db\Row::from([]);
		Tester\Assert::same([], $blankRow->toArray());
	}


	public function testRowSerialize(): void
	{
		$this->connection->query('
			CREATE TABLE test(
				id serial,
  				name text
			);
		');

		$this->connection->query('INSERT INTO test(name) VALUES(?)', 'phpgsql');

		$row = $this->connection->query('SELECT id, name FROM test')->fetch();
		\assert($row !== NULL);

		Tester\Assert::same(1, $row->id);
		Tester\Assert::same('phpgsql', $row->name);

		$serializedRow = \serialize($row);

		Tester\Assert::same($row->toArray(), \unserialize($serializedRow)->toArray());
	}


	public function testGetNotifications(): void
	{
		$this->connection->execute('DO $BODY$ BEGIN RAISE NOTICE \'Test notice\'; END; $BODY$ LANGUAGE plpgsql;');
		Tester\Assert::same(['NOTICE:  Test notice'], $this->connection->getNotices());
		Tester\Assert::same([], $this->connection->getNotices());
	}


	public function testGetNotificationsWithouClearing(): void
	{
		$this->connection->execute('DO $BODY$ BEGIN RAISE NOTICE \'Test notice\'; END; $BODY$ LANGUAGE plpgsql;');
		Tester\Assert::same(['NOTICE:  Test notice'], $this->connection->getNotices(FALSE));
		Tester\Assert::same(['NOTICE:  Test notice'], $this->connection->getNotices());
	}

}

(new BasicTest())->run();
