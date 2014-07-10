<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire           http://phyxo.nikrou.net/ |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

if (!defined('PHPWG_ROOT_PATH')) {
    die ('This page cannot be loaded directly, load upgrade.php');
} else {
    if (!defined('PHPWG_IN_UPGRADE') or !PHPWG_IN_UPGRADE) {
        die ('Hacking attempt!');
    }
}

// +-----------------------------------------------------------------------+
// |             Fill upgrade table without applying upgrade               |
// +-----------------------------------------------------------------------+

// retrieve already applied upgrades
$query = 'SELECT id  FROM '.PREFIX_TABLE.'upgrade;';
$applied = array_from_query($query, 'id');

// retrieve existing upgrades
$existing = get_available_upgrade_ids();

// which upgrades need to be applied?
$to_apply = array_diff($existing, $applied);
$inserts = array();
foreach ($to_apply as $upgrade_id) {
    if ($upgrade_id >= 139) { // TODO change on each release  
        break;
    }
  
    array_push(
        $inserts,
        array(
            'id' => $upgrade_id,
            'applied' => CURRENT_DATE,
            'description' => '[migration from 2.5.0 to '.PHPWG_VERSION.'] not applied', // TODO change on each release
        )
    );
}

if (!empty($inserts)) {
    mass_inserts(
        '`'.UPGRADE_TABLE.'`',
        array_keys($inserts[0]),
        $inserts
    );
}

// +-----------------------------------------------------------------------+
// |                          Perform upgrades                             |
// +-----------------------------------------------------------------------+

ob_start();
echo '<pre>';

for ($upgrade_id = 139; $upgrade_id <= 142; $upgrade_id++) { // TODO change on each release
    if (!file_exists(UPGRADES_PATH.'/'.$upgrade_id.'-database.php')) {
        continue;
    }

    // maybe the upgrade task has already been applied in a previous and
    // incomplete upgrade
    if (in_array($upgrade_id, $applied)) {
        continue;
    }
  
    unset($upgrade_description);

    echo "\n\n";
    echo '=== upgrade '.$upgrade_id."\n";

    // include & execute upgrade script. Each upgrade script must contain
    // $upgrade_description variable which describe briefly what the upgrade
    // script does.
    include(UPGRADES_PATH.'/'.$upgrade_id.'-database.php');
    
    // notify upgrade (TODO change on each release)
    $query = 'INSERT INTO '.PREFIX_TABLE.'upgrade (id, applied, description)';
    $query .= ' VALUES';
    $query.= '(\''.$upgrade_id.'\', NOW(), \'[migration from 1.0.0 to '.PHPWG_VERSION.'] '.$upgrade_description.'\');';
    pwg_query($query);
}

echo '</pre>';
ob_end_clean();

