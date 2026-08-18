<?php

declare(strict_types=1);

namespace OCA\Abonnieren\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Create the subscription rule table. */
class Version010000Date20260812000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('abonnieren_object_rules')) {
			$table = $schema->createTable('abonnieren_object_rules');
			$table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('user_id', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('node_id', 'bigint', ['notnull' => true]);
			$table->addColumn('event_mask', 'integer', ['notnull' => true]);
			$table->addColumn('recursive', 'boolean', ['notnull' => true, 'default' => false]);
			$table->addColumn('created_at', 'bigint', ['notnull' => true]);
			$table->addColumn('updated_at', 'bigint', ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id', 'node_id'], 'abonnieren_user_node');
			$table->addIndex(['node_id'], 'abonnieren_node_idx');
			$table->addIndex(['user_id'], 'abonnieren_user_idx');
		}

		return $schema;
	}
}
