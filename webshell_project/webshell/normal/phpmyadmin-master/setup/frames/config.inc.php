<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Config file view and save screen
 *
 * @package PhpMyAdmin-Setup
 */
declare(strict_types=1);

use PhpMyAdmin\Config\FormDisplayTemplate;
use PhpMyAdmin\Core;
use PhpMyAdmin\Setup\ConfigGenerator;

if (!defined('PHPMYADMIN')) {
    exit;
}

$formDisplayTemplate = new FormDisplayTemplate($GLOBALS['PMA_Config']);

echo '<h2>' , __('Configuration file') , '</h2>';

echo $formDisplayTemplate->displayFormTop('config.php');
echo '<input type="hidden" name="eol" value="'
    , htmlspecialchars(Core::ifSetOr($_GET['eol'], 'unix')) , '" />';
echo $formDisplayTemplate->displayFieldsetTop('config.inc.php', '', null, ['class' => 'simple']);
echo '<tr>';
echo '<td>';
echo '<textarea cols="50" rows="20" name="textconfig" '
    , 'id="textconfig" spellcheck="false">';
echo htmlspecialchars(ConfigGenerator::getConfigFile($GLOBALS['ConfigFile']));
echo '</textarea>';
echo '</td>';
echo '</tr>';
echo '<tr>';
echo '<td class="lastrow" style="text-align: left">';
echo '<input type="submit" name="submit_download" value="'
    , __('Download') , '" class="green" />';
echo '</td>';
echo '</tr>';

echo $formDisplayTemplate->displayFieldsetBottom(false);
echo $formDisplayTemplate->displayFormBottom();
