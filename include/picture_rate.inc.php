<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2015 Nicolas Roudaire         http://www.phyxo.net/ |
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

/**
 * This file is included by the picture page to manage rates
 *
 */

if ($conf['rate']) {
    $rate_summary = array('count' => 0, 'score' => $picture['current']['rating_score'], 'average' => null);
    if (NULL != $rate_summary['score']) {
        $query = 'SELECT COUNT(rate) AS count,ROUND(AVG(rate),2) AS average FROM '.RATE_TABLE;
        $query .= ' WHERE element_id = '.$conn->db_real_escape_string($picture['current']['id']);
		list($rate_summary['count'], $rate_summary['average']) = $conn->db_fetch_row($conn->db_query($query));
    }
    $template->assign('rate_summary', $rate_summary);

    $user_rate = null;
    if ($conf['rate_anonymous'] or $services['users']->isAuthorizeStatus(ACCESS_CLASSIC)) {
        if ($rate_summary['count']>0) {
            $query = 'SELECT rate FROM '.RATE_TABLE;
            $query .= ' WHERE element_id = '.$conn->db_real_escape_string($page['image_id']);
            $query .= ' AND user_id = '.$conn->db_real_escape_string($user['id']);

            if (!$services['users']->isAuthorizeStatus(ACCESS_CLASSIC)) {
                $ip_components = explode('.', $_SERVER['REMOTE_ADDR']);
                if (count($ip_components)>3) {
                    array_pop($ip_components);
                }
                $anonymous_id = implode ('.', $ip_components);
                $query .= ' AND anonymous_id = \''.$anonymous_id . '\'';
            }

            $result = $conn->db_query($query);
            if ($conn->db_num_rows($result) > 0) {
                $row = $conn->db_fetch_assoc($result);
                $user_rate = $row['rate'];
            }
        }

        $template->assign(
            'rating',
            array(
                'F_ACTION' => add_url_params(
                    $url_self,
                    array('action'=>'rate')
                ),
                'USER_RATE'=> $user_rate,
                'marks'    => $conf['rate_items']
            )
        );
    }
}
