<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire              http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

$upgrade_description = 'add "latitude" and "longitude" fields';

// add fields
$query = '
ALTER TABLE '. IMAGES_TABLE .'
  ADD `latitude` DOUBLE(8, 6) DEFAULT NULL,
  ADD `longitude` DOUBLE(9, 6) DEFAULT NULL
;';
$conn->db_query($query);

// add index
$query = '
ALTER TABLE '. IMAGES_TABLE .'
  ADD INDEX `images_i6` (`latitude`)
;';
$conn->db_query($query);

// search for old "lat" field
$query = 'SHOW COLUMNS FROM '. IMAGES_TABLE .' LIKE \'lat\'';

if ($conn->db_num_rows($conn->db_query($query))) {
    // duplicate non-null values
    $query = 'UPDATE '. IMAGES_TABLE;
    $query .= ' SET latitude = lat,longitude = lon WHERE lat IS NOT NULL AND lon IS NOT NULL';
    $conn->db_query($query);
}

echo "\n".$upgrade_description."\n";
