<?php

namespace Drupal8Rector\Rector\Deprecation;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\RectorDefinition;
/**
 * Replaces deprecated db_* procedural function calls of the Database API layer.
 * @see https://www.drupal.org/node/2993033
 */
final class DatabaseProceduralFunctionsRector extends AbstractRector
{
    /**
     * @inheritdoc
     */
    public function getNodeTypes(): array
    {
        return [
            Node\Expr\FuncCall::class,
        ];
    }
    /**
     * @inheritdoc
     * @todo Add support for replica databases, where options contains ['target' => 'replica'].
     */
    public function refactor(Node $node): ?Node
    {
        $db_procedural_functions_methods_mapping = $this->getFunctionMethodMapping();
        $needs_custom_refactoring = $this->getFunctionsWithCustomRefactoring();

        /** @var Node\Expr\FuncCall $node */
        // Ignore those complex cases when function name specified by a variable.
        if ($node->name instanceof Node\Name && in_array((string) $node->name, array_keys($db_procedural_functions_methods_mapping)) === TRUE) {

            $mapping = $db_procedural_functions_methods_mapping[(string) $node->name];

            if ($mapping['type'] == 'injected_database') {
                // Injected database type.

                if ( in_array((string) $node->name, $needs_custom_refactoring) == FALSE ) {
                    // Custom refactoring is NOT needed.
                    // Call the default database
                    $new_node = new Node\Expr\StaticCall(new Node\Name\FullyQualified('Drupal'), 'service', [new Node\Scalar\String_('database')]);
                    // Add methods.
                    for($i = 0; $i < count($mapping['methods']); $i++) {
                        if ($i == count($mapping['methods']) - 1) {
                            // If this is the last method.
                            $new_node = new Node\Expr\MethodCall($new_node, new Node\Identifier($mapping['methods'][$i]), $node->args);
                        } else {
                            // If this is NOT the last method.
                            $new_node = new Node\Expr\MethodCall($new_node, new Node\Identifier($mapping['methods'][$i]));
                        }
                    }
                    return $new_node;
                } else {
                    // Custom refactoring is needed.
                    // @todo Handle custom refactoring in injected_database type.
                }
            } elseif ($mapping['type'] == 'close_connection') {
                // Close connection type.

                // Check that the first argument ($options) is exists or not.
                if (array_key_exists(0, $node->args)) {
                    // $options argument exists.
                    $target = $this->getTargetFromOptionArgument($node->args[0]->value);
                    if ($target !== FALSE) {
                        return new Node\Expr\StaticCall(new Node\Name\FullyQualified('Drupal\Core\Database\Database'), 'closeConnection', [$target]);
                    }
                } else {
                    // $options argument doesn't exist.
                    return new Node\Expr\StaticCall(new Node\Name\FullyQualified('Drupal\Core\Database\Database'), 'closeConnection');
                }

            } elseif ($mapping['type'] == 'condition') {
                // Condition type.
                if ( !empty($mapping['parameter']) ) {
                    // Call Condition with parameter.
                    return new Node\Expr\New_(new Node\Name\FullyQualified('Drupal\Core\Database\Query\Condition'), [new Node\Scalar\String_($mapping['parameter'])]);
                } else {
                    // Call Condition with the inherited parameter.
                    return new Node\Expr\New_(new Node\Name\FullyQualified('Drupal\Core\Database\Query\Condition'), $node->args);
                }
            } elseif ($mapping['type'] == 'set_active_connection') {
                // Set active connection type.
                return new Node\Expr\StaticCall(new Node\Name\FullyQualified('Drupal\Core\Database\Database'), 'setActiveConnection', $node->args);
            }
        }

        return $node;
    }
    /**
     * @inheritdoc
     */
    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(sprintf('Fixes deprecated db_* procedural function calls of the Database API layer'));
    }

    /**
     * Get target from options argument.
     *
     * @param $arg
     *
     * @return bool|\PhpParser\Node\Expr|\PhpParser\Node\Identifier
     */
    private function getTargetFromOptionArgument($arg) {
        $target = FALSE;
        if ($arg instanceof Node\Expr\Array_) {
            // If the $option parameter is an array.
            $target = new Node\Identifier('NULL');

            foreach ($arg->items as $array_item) {
                if ( $array_item->key instanceof Node\Scalar\String_ && $array_item->key->value == 'target') {
                    $target = $array_item->value;
                }
            }
        } else  {
            // Unable to identify type of $options, because it coming from a variable or such.
            // @todo Research how this situation can be handled.
        }
        return $target;
    }

    /**
     * Get db_ procedural functions and related methods mapping.
     *
     * @return array
     */
    private function getFunctionMethodMapping(): array {
        return [
            'db_add_field' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'addField'],
            ],
            'db_add_index' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'addIndex'],
            ],
            'db_add_primary_key' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'addPrimaryKey'],
            ],
            'db_add_unique_key' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'addUniqueKey'],
            ],
            'db_and' => [
                'type' => 'condition',
                'methods' => [],
                'parameter' => 'AND',
            ],
            'db_change_field' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'changeField'],
            ],
            'db_close' => [
                'type' => 'close_connection',
                'methods' => [],
            ],
            'db_condition' => [
                'type' => 'condition',
                'methods' => [],
            ],
            'db_create_table' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'createTable'],
            ],
            // @todo This needs some extra lines:  https://api.drupal.org/api/drupal/core%21includes%21database.inc/function/db_delete/8.2.x
            'db_delete' => [
                'type' => 'injected_database',
                'methods' => ['delete'],
            ],
            'db_driver' => [
                'type' => 'injected_database',
                'methods' => ['driver'],
            ],
            'db_drop_field' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'dropField'],
            ],
            'db_drop_index' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'dropIndex'],
            ],
            'db_drop_primary_key' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'dropPrimaryKey'],
            ],
            'db_drop_table' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'dropTable'],
            ],
            'db_drop_unique_key' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'dropUniqueKey'],
            ],
            'db_escape_field' => [
                'type' => 'injected_database',
                'methods' => ['escapeField'],
            ],
            'db_escape_table' => [
                'type' => 'injected_database',
                'methods' => ['escapeTable'],
            ],
            'db_field_exists' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'fieldExists'],
            ],
            'db_field_names' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'fieldNames'],
            ],
            // @todo The recommended method was fieldSetDefault. It is also marked deprecated in Drupal 8.7.x.
            // @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Database%21Schema.php/function/Schema%3A%3AfieldSetDefault/8.7.x
            // @see https://www.drupal.org/node/2999035
            'db_field_set_default' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'changeField'],
            ],
            // @todo The recommended method was fieldSetNoDefault. It is also marked deprecated in Drupal 8.7.x.
            // @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Database%21Schema.php/function/Schema%3A%3AfieldSetNoDefault/8.7.x
            // @see https://www.drupal.org/node/2999035
            'db_field_set_no_default' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'changeField'],
            ],
            'db_find_tables' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'findTables'],
            ],
            'db_index_exists' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'indexExists'],
            ],
            // @todo This needs some extra lines: https://api.drupal.org/api/drupal/core%21includes%21database.inc/function/db_insert/8.7.x
            'db_insert' => [
                'type' => 'injected_database',
                'methods' => ['insert'],
            ],
            'db_like' => [
                'type' => 'injected_database',
                'methods' => ['escapeLike'],
            ],
            // @todo This needs some extra lines: https://api.drupal.org/api/drupal/core%21includes%21database.inc/function/db_merge/8.7.x
            // @see https://www.drupal.org/node/2947775
            'db_merge' => [
                'type' => 'injected_database',
                'methods' => ['merge'],
            ],
            'db_next_id' => [
                'type' => 'injected_database',
                'methods' => ['nextId'],
            ],
            'db_or' => [
                'type' => 'condition',
                'methods' => [],
                'parameter' => 'OR',
            ],
            // @todo This needs some extra lines: https://api.drupal.org/api/drupal/core%21includes%21database.inc/function/db_query/8.7.x
            'db_query' => [
                'type' => 'injected_database',
                'methods' => ['query'],
            ],
            // @todo This needs some extra lines: https://api.drupal.org/api/drupal/core%21includes%21database.inc/function/db_query_range/8.7.x
            'db_query_range' => [
                'type' => 'injected_database',
                'methods' => ['queryRange'],
            ],
            // @todo This needs some extra lines: https://api.drupal.org/api/drupal/core%21includes%21database.inc/function/db_query_temporary/8.7.x
            'db_query_temporary' => [
                'type' => 'injected_database',
                'methods' => ['queryTemporary'],
            ],
            'db_rename_table' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'renameTable'],
            ],
            // @todo This needs some extra lines: https://api.drupal.org/api/drupal/core%21includes%21database.inc/function/db_select/8.7.x
            'db_select' => [
                'type' => 'injected_database',
                'methods' => ['select'],
            ],
            'db_set_active' => [
                'type' => 'set_active_connection',
                'methods' => [],
            ],
            'db_table_exists' => [
                'type' => 'injected_database',
                'methods' => ['schema', 'tableExists'],
            ],
            // @todo This needs some extra lines: https://api.drupal.org/api/drupal/core%21includes%21database.inc/function/db_transaction/8.7.x
            'db_transaction' => [
                'type' => 'injected_database',
                'methods' => ['startTransaction'],
            ],
            // @todo This needs some extra lines: https://api.drupal.org/api/drupal/core%21includes%21database.inc/function/db_truncate/8.7.x
            'db_truncate' => [
                'type' => 'injected_database',
                'methods' => ['truncate'],
            ],
            // @todo This needs some extra lines: https://api.drupal.org/api/drupal/core%21includes%21database.inc/function/db_update/8.7.x
            'db_update' => [
                'type' => 'injected_database',
                'methods' => ['update'],
            ],
            'db_xor' => [
                'type' => 'condition',
                'methods' => [],
                'parameter' => 'XOR',
            ],
        ];
    }

    /**
     * Get an array of db_ procedural functions, which need custom refactoring.
     *
     * @return array
     */
    private function getFunctionsWithCustomRefactoring() {
        return [
            'db_delete',
            'db_field_set_default',
            'db_field_set_no_default',
            'db_insert',
            'db_merge',
            'db_query',
            'db_query_range',
            'db_query_temporary',
            'db_select',
            'db_transaction',
            'db_truncate',
            'db_update',
        ];
    }
}
