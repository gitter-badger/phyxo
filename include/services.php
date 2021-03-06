<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2015 Nicolas Roudaire         http://www.phyxo.net/ |
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

if(!defined("PHPWG_ROOT_PATH")) {
    die('Hacking attempt!');
}

use Phyxo\Model\Repository\Tags;
use Phyxo\Model\Repository\Comments;
use Phyxo\Model\Repository\Users;

$services = array();
$services['tags'] = new Tags($conn, 'Phyxo\Model\Entity\Tag', TAGS_TABLE);
$services['comments'] = new Comments($conn, 'Phyxo\Model\Entity\Comment', COMMENTS_TABLE);
$services['users'] = new Users($conn, 'Phyxo\Model\Entity\User', USERS_TABLE);

// @TODO : find a better place
add_event_handler('user_comment_check', array($services['comments'], 'userCommentCheck'));

// temporary hack for password_*
function pwg_password_verify($password, $hash, $user_id=null) {
    global $services;

    return $services['users']->passwordVerify($password, $hash, $user_id);
}

function pwg_password_hash($password) {
    global $services;

    return $services['users']->passwordHash($password);
}
