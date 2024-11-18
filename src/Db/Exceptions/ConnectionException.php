<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db\Exceptions;

class ConnectionException extends Exception
{
	public const NO_CONFIG = 1;
	public const CANT_CHANGE_WHEN_CONNECTED = 2;
	public const CONNECTION_FAILED = 3;
	public const BAD_CONNECTION = 4;
	public const CANT_GET_NOTICES = 5;
	public const ASYNC_CANCEL_FAILED = 6;
	public const ASYNC_QUERY_SENT_FAILED = 7;
	public const ASYNC_NO_QUERY_IS_SENT = 8;
	public const ASYNC_NO_EXECUTE_IS_SENT = 9;
	public const ASYNC_ANOTHER_QUERY_IS_RUNNING = 10;


	public static function noConfig(): self
	{
		return new self('No configuration was provided.', self::NO_CONFIG);
	}


	public static function cantChangeWhenConnected(string $type): self
	{
		return new self(\sprintf('You can\'t change \'%s\' when connected.', $type), self::CANT_CHANGE_WHEN_CONNECTED);
	}


	public static function connectionFailed(): self
	{
		$message = '.';
		$lastPhpError = \error_get_last();
		if ($lastPhpError !== NULL && $lastPhpError['type'] === \E_WARNING) {
			$message = ': ' . $lastPhpError['message'];
		}

		return new self('Connection failed' . $message, self::CONNECTION_FAILED);
	}


	public static function badConnection(): self
	{
		return new self('Connection failed (bad connection).', self::BAD_CONNECTION);
	}


	public static function cantGetNotices(): self
	{
		return new self('Can\'t get notices from connection. Is notice message tracking not ignored in php.ini - pgsql.ignore_notice = 0 is the right value.', self::CANT_GET_NOTICES);
	}


	public static function asyncCancelFailed(): self
	{
		return new self('Cancelation of async query failed.', self::ASYNC_CANCEL_FAILED);
	}


	public static function asyncQuerySentFailed(string $error): self
	{
		return new self(\sprintf('Sending new async query failed: \'%s\'. Did you complete previous async query?', $error), self::ASYNC_QUERY_SENT_FAILED);
	}


	public static function asyncNoQueryIsSent(): self
	{
		return new self('No async query is sent.', self::ASYNC_NO_QUERY_IS_SENT);
	}


	public static function asyncNoExecuteIsSent(): self
	{
		return new self('No async execute is sent.', self::ASYNC_NO_EXECUTE_IS_SENT);
	}


	public static function anotherAsyncQueryIsRunning(string $resultQuery, string $actualQuery): self
	{
		return new self(
			\sprintf(
				'Result async query \'%s\' is different from actual connection async query \'%s\', async query results can\'t be read.',
				$resultQuery,
				$actualQuery,
			),
			self::ASYNC_ANOTHER_QUERY_IS_RUNNING,
		);
	}

}
