<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db;

use PgSql;

interface ResultFactory
{

	/**
	 * @param array<int, string>|NULL $dataTypesCache
	 */
	function create(
		PgSql\Result $queryResource,
		Query $query,
		RowFactory $rowFactory,
		DataTypeParser $dataTypeParser,
		array|NULL $dataTypesCache,
	): Result;

}
