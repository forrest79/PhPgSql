<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db\Exceptions;

use Forrest79\PhPgSql\Db;

class QueryException extends Exception
{
	public const QUERY_FAILED = 1;
	public const ASYNC_QUERY_FAILED = 2;
	public const PREPARED_STATEMENT_QUERY_FAILED = 3;
	public const ASYNC_PREPARED_STATEMENT_QUERY_FAILED = 4;
	public const CANT_PASS_PARAMS = 5;
	public const MISSING_PARAM = 6;
	public const EXTRA_PARAM = 7;

	private Db\Query|NULL $query;


	public function __construct(
		string $message = '',
		int $code = 0,
		Db\Query|NULL $query = NULL,
		\Throwable|NULL $previous = NULL,
	)
	{
		parent::__construct($message, $code, $previous);
		$this->query = $query;
	}


	public function getQuery(): Db\Query|NULL
	{
		return $this->query;
	}


	public static function queryFailed(Db\Query $query, string $error): self
	{
		return new self(\sprintf('Query failed [%s]: \'%s\'.', $error, $query->sql), self::QUERY_FAILED, $query);
	}


	public static function asyncQueryFailed(Db\Query $query, string $error): self
	{
		return new self(\sprintf('Async query failed [%s]: \'%s\'.', $error, $query->sql), self::ASYNC_QUERY_FAILED, $query);
	}


	public static function preparedStatementQueryFailed(
		string $preparedStatementName,
		Db\Query $query,
		string $error,
	): self
	{
		return new self(\sprintf('Prepared statement failed [%s]: \'%s\', query: \'%s\'.', $error, $preparedStatementName, $query->sql), self::PREPARED_STATEMENT_QUERY_FAILED, $query);
	}


	public static function asyncPreparedStatementQueryFailed(
		string $preparedStatementName,
		Db\Query $query,
		string $error,
	): self
	{
		return new self(\sprintf('Async prepared statement failed [%s]: \'%s\', query \'%s\'.', $error, $preparedStatementName, $query->sql), self::ASYNC_PREPARED_STATEMENT_QUERY_FAILED, $query);
	}


	public static function cantPassParams(): self
	{
		return new self('Can\'t pass params when passing Query object.', self::CANT_PASS_PARAMS);
	}


	public static function missingParam(int $index): self
	{
		return new self(\sprintf('There is no param for index %s. Did you escape all \'?\' characters, which you want to use as \'?\' and not as parameter?', $index), self::MISSING_PARAM);
	}


	/**
	 * @param list<mixed> $extraParams
	 */
	public static function extraParam(array $extraParams): self
	{
		$count = \count($extraParams);
		return new self('The number of \'?\' don\'t match the number of parameters. ' . ($count > 1 ? \sprintf('The last %d parameters are extra.', $count) : 'The last parameter is extra.'), self::EXTRA_PARAM);
	}

}
