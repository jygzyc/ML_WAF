<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */

/**
 * Holds the PhpMyAdmin\Controllers\Server\ServerDatabasesController
 *
 * @package PhpMyAdmin\Controllers
 */
declare(strict_types=1);

namespace PhpMyAdmin\Controllers\Server;

use PhpMyAdmin\Charsets;
use PhpMyAdmin\Controllers\Controller;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Message;
use PhpMyAdmin\Response;
use PhpMyAdmin\Url;
use PhpMyAdmin\Util;

/**
 * Handles viewing and creating and deleting databases
 *
 * @package PhpMyAdmin\Controllers
 */
class ServerDatabasesController extends Controller
{
    /**
     * @var array array of database details
     */
    private $_databases;
    /**
     * @var int number of databases
     */
    private $_database_count;
    /**
     * @var string sort by column
     */
    private $_sort_by;
    /**
     * @var string sort order of databases
     */
    private $_sort_order;
    /**
     * @var boolean whether to show database statistics
     */
    private $_dbstats;
    /**
     * @var int position in list navigation
     */
    private $_pos;

    /**
     * Index action
     *
     * @return void
     */
    public function indexAction()
    {
        include_once 'libraries/check_user_privileges.inc.php';

        $response = Response::getInstance();

        if (isset($_REQUEST['drop_selected_dbs'])
            && $response->isAjax()
            && ($this->dbi->isSuperuser() || $GLOBALS['cfg']['AllowUserDropDatabase'])
        ) {
            $this->dropDatabasesAction();
            return;
        }

        include_once 'libraries/replication.inc.php';

        if (isset($_POST['new_db'])
            && $response->isAjax()
        ) {
            $this->createDatabaseAction();
            return;
        }

        include_once 'libraries/server_common.inc.php';

        $header  = $this->response->getHeader();
        $scripts = $header->getScripts();
        $scripts->addFile('server_databases.js');

        $this->_setSortDetails();
        $this->_dbstats = empty($_REQUEST['dbstats']) ? false : true;
        $this->_pos     = empty($_REQUEST['pos']) ? 0 : (int) $_REQUEST['pos'];

        /**
         * Gets the databases list
         */
        if ($GLOBALS['server'] > 0) {
            $this->_databases = $this->dbi->getDatabasesFull(
                null,
                $this->_dbstats,
                DatabaseInterface::CONNECT_USER,
                $this->_sort_by,
                $this->_sort_order,
                $this->_pos,
                true
            );
            $this->_database_count = count($GLOBALS['dblist']->databases);
        } else {
            $this->_database_count = 0;
        }

        $_url_params = [
            'pos' => $this->_pos,
            'dbstats' => $this->_dbstats,
            'sort_by' => $this->_sort_by,
            'sort_order' => $this->_sort_order,
        ];

        $column_order = null;
        $first_database = null;

        if ($this->_database_count > 0 && ! empty($this->_databases)) {
            $first_database = reset($this->_databases);
            // table col order
            $column_order = $this->_getColumnOrder();
            $databases = $this->_getHtmlForDatabases($replication_types);
        }

        $this->response->addHTML($this->template->render('server/databases/index', [
            'show_create_db' => $GLOBALS['cfg']['ShowCreateDb'],
            'is_create_db_priv' => $GLOBALS['is_create_db_priv'],
            'dbstats' => $this->_dbstats,
            'db_to_create' => $GLOBALS['db_to_create'],
            'databases' => isset($databases) ? $databases : null,
            'dbi' => $this->dbi,
            'disable_is' => $GLOBALS['cfg']['Server']['DisableIS'],
            'database_count' => $this->_database_count,
            'pos' => $this->_pos,
            'url_params' => $_url_params,
            'max_db_list' => $GLOBALS['cfg']['MaxDbList'],
            'sort_by' => $this->_sort_by,
            'sort_order' => $this->_sort_order,
            'column_order' => $column_order,
            'first_database' => $first_database,
            'master_replication' => $GLOBALS['replication_info']['master']['status'],
            'slave_replication' => $GLOBALS['replication_info']['slave']['status'],
            'is_superuser' => $this->dbi->isSuperuser(),
            'allow_user_drop_database' => $GLOBALS['cfg']['AllowUserDropDatabase'],
        ]));
    }

    /**
     * Handles creating a new database
     *
     * @return void
     */
    public function createDatabaseAction()
    {
        /**
         * Builds and executes the db creation sql query
         */
        $sql_query = 'CREATE DATABASE ' . Util::backquote($_POST['new_db']);
        if (! empty($_POST['db_collation'])) {
            list($db_charset) = explode('_', $_POST['db_collation']);
            $charsets = Charsets::getMySQLCharsets(
                $this->dbi,
                $GLOBALS['cfg']['Server']['DisableIS']
            );
            $collations = Charsets::getMySQLCollations(
                $this->dbi,
                $GLOBALS['cfg']['Server']['DisableIS']
            );
            if (in_array($db_charset, $charsets)
                && in_array($_POST['db_collation'], $collations[$db_charset])
            ) {
                $sql_query .= ' DEFAULT'
                    . Util::getCharsetQueryPart($_POST['db_collation']);
            }
        }
        $sql_query .= ';';

        $result = $this->dbi->tryQuery($sql_query);

        if (! $result) {
            // avoid displaying the not-created db name in header or navi panel
            $GLOBALS['db'] = '';

            $message = Message::rawError($this->dbi->getError());
            $this->response->setRequestStatus(false);
            $this->response->addJSON('message', $message);
        } else {
            $GLOBALS['db'] = $_POST['new_db'];

            $message = Message::success(__('Database %1$s has been created.'));
            $message->addParam($_POST['new_db']);
            $this->response->addJSON('message', $message);
            $this->response->addJSON(
                'sql_query',
                Util::getMessage(null, $sql_query, 'success')
            );

            $this->response->addJSON(
                'url_query',
                Util::getScriptNameForOption(
                    $GLOBALS['cfg']['DefaultTabDatabase'],
                    'database'
                )
                . Url::getCommon(['db' => $_POST['new_db']])
            );
        }
    }

    /**
     * Handles dropping multiple databases
     *
     * @return void
     */
    public function dropDatabasesAction()
    {
        if (! isset($_REQUEST['selected_dbs'])) {
            $message = Message::error(__('No databases selected.'));
        } else {
            $action = 'server_databases.php';
            $err_url = $action . Url::getCommon();

            $GLOBALS['submit_mult'] = 'drop_db';
            $GLOBALS['mult_btn'] = __('Yes');

            include 'libraries/mult_submits.inc.php';

            if (empty($message)) { // no error message
                $number_of_databases = count($selected);
                $message = Message::success(
                    _ngettext(
                        '%1$d database has been dropped successfully.',
                        '%1$d databases have been dropped successfully.',
                        $number_of_databases
                    )
                );
                $message->addParam($number_of_databases);
            }
        }

        if ($message instanceof Message) {
            $this->response->setRequestStatus($message->isSuccess());
            $this->response->addJSON('message', $message);
        }
    }

    /**
     * Extracts parameters $sort_order and $sort_by
     *
     * @return void
     */
    private function _setSortDetails()
    {
        if (empty($_REQUEST['sort_by'])) {
            $this->_sort_by = 'SCHEMA_NAME';
        } else {
            $sort_by_whitelist = [
                'SCHEMA_NAME',
                'DEFAULT_COLLATION_NAME',
                'SCHEMA_TABLES',
                'SCHEMA_TABLE_ROWS',
                'SCHEMA_DATA_LENGTH',
                'SCHEMA_INDEX_LENGTH',
                'SCHEMA_LENGTH',
                'SCHEMA_DATA_FREE'
            ];
            if (in_array($_REQUEST['sort_by'], $sort_by_whitelist)) {
                $this->_sort_by = $_REQUEST['sort_by'];
            } else {
                $this->_sort_by = 'SCHEMA_NAME';
            }
        }

        if (isset($_REQUEST['sort_order'])
            && mb_strtolower($_REQUEST['sort_order']) == 'desc'
        ) {
            $this->_sort_order = 'desc';
        } else {
            $this->_sort_order = 'asc';
        }
    }

    /**
     * Returns the html for Database List
     *
     * @param array $replication_types replication types
     *
     * @return string
     */
    private function _getHtmlForDatabases(array $replication_types)
    {
        $first_database = reset($this->_databases);
        // table col order
        $column_order = $this->_getColumnOrder();
        $dbColumnOrders = [];

        // calculate aggregate stats to display in footer
        foreach ($this->_databases as $current) {
            $dbColumnOrders[$current['SCHEMA_NAME']] = $this->_getColumnOrder();
            foreach ($column_order as $stat_name => $stat) {
                if (array_key_exists($stat_name, $current)
                    && is_numeric($stat['footer'])
                ) {
                    $column_order[$stat_name]['footer'] += $current[$stat_name];
                    $dbColumnOrders[$current['SCHEMA_NAME']][$stat_name]['footer'] = $current[$stat_name];
                }
            }
        }

        $values = [];
        $units = [];
        foreach ($column_order as $stat_name => $stat) {
            if (array_key_exists($stat_name, $first_database)) {
                if ($stat['format'] == 'byte') {
                    $byte_format = Util::formatByteDown($stat['footer'], 3, 1);
                    $values[$stat_name] = $byte_format[0];
                    $units[$stat_name] = $byte_format[1];
                } elseif ($stat['format'] == 'number') {
                    $values[$stat_name] = Util::formatNumber($stat['footer'], 0);
                } else {
                    $values[$stat_name] = htmlentities($stat['footer'], 0);
                }
            }
        }

        $_url_params = [
            'pos' => $this->_pos,
            'dbstats' => $this->_dbstats,
            'sort_by' => $this->_sort_by,
            'sort_order' => $this->_sort_order,
        ];

        $html = $this->_getHtmlForTableBody($dbColumnOrders, $replication_types);

        $html .= $this->template->render('server/databases/databases_footer', [
            'column_order' => $column_order,
            'first_database' => $first_database,
            'master_replication' => $GLOBALS['replication_info']['master']['status'],
            'slave_replication' => $GLOBALS['replication_info']['slave']['status'],
            'database_count' => $this->_database_count,
            'is_superuser' => $this->dbi->isSuperuser(),
            'allow_user_drop_database' => $GLOBALS['cfg']['AllowUserDropDatabase'],
            'pma_theme_image' => $GLOBALS['pmaThemeImage'],
            'text_dir' => $GLOBALS['text_dir'],
            'dbstats' => $this->_dbstats,
            'values' => $values,
            'units' => $units,
        ]);

        return $html;
    }

    /**
     * Prepares the $column_order array
     *
     * @return array
     */
    private function _getColumnOrder()
    {
        $column_order = [];
        $column_order['DEFAULT_COLLATION_NAME'] = [
            'disp_name' => __('Collation'),
            'description_function' => [Charsets::class, 'getCollationDescr'],
            'format'    => 'string',
            'footer'    => '',
        ];
        $column_order['SCHEMA_TABLES'] = [
            'disp_name' => __('Tables'),
            'format'    => 'number',
            'footer'    => 0,
        ];
        $column_order['SCHEMA_TABLE_ROWS'] = [
            'disp_name' => __('Rows'),
            'format'    => 'number',
            'footer'    => 0,
        ];
        $column_order['SCHEMA_DATA_LENGTH'] = [
            'disp_name' => __('Data'),
            'format'    => 'byte',
            'footer'    => 0,
        ];
        $column_order['SCHEMA_INDEX_LENGTH'] = [
            'disp_name' => __('Indexes'),
            'format'    => 'byte',
            'footer'    => 0,
        ];
        $column_order['SCHEMA_LENGTH'] = [
            'disp_name' => __('Total'),
            'format'    => 'byte',
            'footer'    => 0,
        ];
        $column_order['SCHEMA_DATA_FREE'] = [
            'disp_name' => __('Overhead'),
            'format'    => 'byte',
            'footer'    => 0,
        ];

        return $column_order;
    }

    /**
     * Returns the html for Database List
     *
     * @param array $dbColumnOrders   databases column order
     * @param array $replicationTypes replication types
     *
     * @return string
     */
    private function _getHtmlForTableBody(array $dbColumnOrders, array $replicationTypes)
    {
        $html = '<tbody>' . "\n";

        foreach ($this->_databases as $current) {
            $tr_class = ' db-row';
            if ($this->dbi->isSystemSchema($current['SCHEMA_NAME'], true)) {
                $tr_class .= ' noclick';
            }

            $generated_html = $this->_buildHtmlForDb(
                $current,
                $dbColumnOrders[$current['SCHEMA_NAME']],
                $replicationTypes,
                $GLOBALS['replication_info'],
                $tr_class
            );
            $html .= $generated_html;
        } // end foreach ($this->_databases as $key => $current)
        $html .= '</tbody>';

        return $html;
    }

    /**
     * Builds the HTML for one database to display in the list
     * of databases from server_databases.php
     *
     * @param array  $current           current database
     * @param array  $column_order      column order
     * @param array  $replication_types replication types
     * @param array  $replication_info  replication info
     * @param string $tr_class          HTMl class for the row
     *
     * @return string
     */
    public function _buildHtmlForDb(
        array $current,
        array $column_order,
        array $replication_types,
        array $replication_info,
        $tr_class = ''
    ) {
        $master_replication = $slave_replication = '';
        foreach ($replication_types as $type) {
            if ($replication_info[$type]['status']) {
                $out = '';
                $key = array_search(
                    $current["SCHEMA_NAME"],
                    $replication_info[$type]['Ignore_DB']
                );
                if (strlen((string) $key) > 0) {
                    $out = Util::getIcon(
                        's_cancel',
                        __('Not replicated')
                    );
                } else {
                    $key = array_search(
                        $current["SCHEMA_NAME"],
                        $replication_info[$type]['Do_DB']
                    );

                    if (strlen((string) $key) > 0
                        || count($replication_info[$type]['Do_DB']) == 0
                    ) {
                        // if ($key != null) did not work for index "0"
                        $out = Util::getIcon(
                            's_success',
                            __('Replicated')
                        );
                    }
                }

                if ($type == 'master') {
                    $master_replication = $out;
                } elseif ($type == 'slave') {
                    $slave_replication = $out;
                }
            }
        }

        $values = [];
        $units = [];
        foreach ($column_order as $stat_name => $stat) {
            if (array_key_exists($stat_name, $current)) {
                if ($stat['format'] == 'byte') {
                    $byte_format = Util::formatByteDown($stat['footer'], 3, 1);
                    $values[$stat_name] = $byte_format[0];
                    $units[$stat_name] = $byte_format[1];
                } elseif ($stat['format'] == 'number') {
                    $values[$stat_name] = Util::formatNumber($stat['footer'], 0);
                } else {
                    $values[$stat_name] = htmlentities($stat['footer'], 0);
                }
            }
        }

        return $this->template->render('server/databases/table_row', [
            'current' => $current,
            'tr_class' => $tr_class,
            'column_order' => $column_order,
            'master_replication_status' => $GLOBALS['replication_info']['master']['status'],
            'master_replication' => $master_replication,
            'slave_replication_status' => $GLOBALS['replication_info']['slave']['status'],
            'slave_replication' => $slave_replication,
            'is_superuser' => $this->dbi->isSuperuser(),
            'allow_user_drop_database' => $GLOBALS['cfg']['AllowUserDropDatabase'],
            'is_system_schema' => $this->dbi->isSystemSchema($current['SCHEMA_NAME'], true),
            'default_tab_database' => $GLOBALS['cfg']['DefaultTabDatabase'],
            'values' => $values,
            'units' => $units,
        ]);
    }
}
