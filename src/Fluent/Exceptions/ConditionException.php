<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Fluent\Exceptions;

class ConditionException extends Exception
{
	public const NO_PARENT = 1;
	public const NO_QUERY = 2;
	public const ONLY_STRING_CONDITION_CAN_HAVE_PARAMS = 3;
	public const BAD_PARAMS_COUNT = 4;


	public static function noParent(): self
	{
		return new self('This condition has no parent assigned.', self::NO_PARENT);
	}


	public static function noQuery(): self
	{
		return new self('This condition has no query assigned.', self::NO_QUERY);
	}


	public static function onlyStringConditionCanHaveParams(): self
	{
		return new self('Only string condition can be add with params.', self::ONLY_STRING_CONDITION_CAN_HAVE_PARAMS);
	}


	public static function badParamsCount(string $condition, int $expected, int $actual): self
	{
		return new self(\sprintf('In condition \'%s\' is expected %d paramerters, but %d was passed.', $condition, $expected, $actual), self::BAD_PARAMS_COUNT);
	}

}
