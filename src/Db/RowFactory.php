<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db;

interface RowFactory
{

	/**
	 * @param array<string, string|NULL> $rawValues
	 */
	function create(ColumnValueParser $columnValueParser, array $rawValues): Row;

}
