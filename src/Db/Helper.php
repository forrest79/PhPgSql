<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db;

class Helper
{

	/**
	 * @param list<mixed> $array
	 */
	public static function createStringPgArray(array $array): string
	{
		return self::doCreatePgArray($array, TRUE);
	}


	/**
	 * @param list<mixed> $array
	 */
	public static function createPgArray(array $array): string
	{
		return self::doCreatePgArray($array, FALSE);
	}


	/**
	 * @param list<mixed> $array
	 */
	private static function doCreatePgArray(array $array, bool $string): string
	{
		if ($array === []) {
			return '{}';
		}

		foreach ($array as $i => $value) {
			if ($value === NULL) {
				$array[$i] = 'NULL';
			} else if ($string) {
				if ($value instanceof \BackedEnum) {
					$value = $value->value;
				}

				\assert(\is_scalar($value));
				$array[$i] = '"' . \str_replace('"', '\"', (string) $value) . '"';
			} else if ($value instanceof \BackedEnum) {
				$array[$i] = $value->value;
			}
		}

		return '{' . \implode(',', $array) . '}';
	}


	/**
	 * @param array<string, string|int|float|NULL> $config
	 */
	public static function prepareConfig(array $config): string
	{
		$configItems = [];
		foreach ($config as $key => $value) {
			if ($value !== NULL) {
				$configItems[] = $key . '=\'' . $value . '\'';
			}
		}

		return \implode(' ', $configItems);
	}

}
