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

if(!defined("PHPWG_ROOT_PATH")) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

check_input_parameter('image_id', $_GET, false, PATTERN_ID);
check_input_parameter('cat_id', $_GET, false, PATTERN_ID);

// represent
$query = 'SELECT id FROM '.CATEGORIES_TABLE;
$query .= ' WHERE representative_picture_id = '.(int) $_GET['image_id'];
$represented_albums = $conn->query2array($query, null, 'id');

// +-----------------------------------------------------------------------+
// |                             delete photo                              |
// +-----------------------------------------------------------------------+

if (isset($_GET['delete'])) {
    check_pwg_token();

    delete_elements(array($_GET['image_id']), true);
    invalidate_user_cache();

    // where to redirect the user now?
    //
    // 1. if a category is available in the URL, use it
    // 2. else use the first reachable linked category
    // 3. redirect to gallery root

    if (!empty($_GET['cat_id'])) {
        redirect(
            make_index_url(
                array(
                    'category' => get_cat_info($_GET['cat_id'])
                )
            )
        );
    }

    $query = 'SELECT category_id FROM '.IMAGE_CATEGORY_TABLE;
    $query .= ' WHERE image_id = '.(int) $_GET['image_id'];

    $authorizeds = array_diff(
        $conn->query2array($query, null, 'category_id'),
        explode(',', $services['users']->calculatePermissions($user['id'], $user['status']))
    );

    foreach ($authorizeds as $category_id) {
        redirect(
            make_index_url(
                array(
                    'category' => get_cat_info($category_id)
                )
            )
        );
    }

    redirect(make_index_url());
}

// +-----------------------------------------------------------------------+
// |                          synchronize metadata                         |
// +-----------------------------------------------------------------------+

if (isset($_GET['sync_metadata'])) {
    sync_metadata(array( intval($_GET['image_id'])));
    $page['infos'][] = l10n('Metadata synchronized from file');
}

//--------------------------------------------------------- update informations
if (isset($_POST['submit'])) {
    $data = array();
    $data['id'] = $_GET['image_id'];
    $data['name'] = $_POST['name'];
    $data['author'] = $_POST['author'];
    $data['level'] = $_POST['level'];

    // @TODO: remove arobases
    if ($conf['allow_html_descriptions']) {
        $data['comment'] = @$_POST['description'];
    } else {
        $data['comment'] = strip_tags(@$_POST['description']);
    }

    if (!empty($_POST['date_creation'])) {
        $data['date_creation'] = $_POST['date_creation'];
    } else {
        $data['date_creation'] = null;
    }

    $data = trigger_change('picture_modify_before_update', $data);

    $conn->single_update(
        IMAGES_TABLE,
        $data,
        array('id' => $data['id'])
    );

    // time to deal with tags
    $tag_ids = array();
    if (!empty($_POST['tags'])) {
        $tag_ids = $services['tags']->getTagsIds($_POST['tags']);
    }
    $services['tags']->setTags($tag_ids, $_GET['image_id']);

    // association to albums
    if (!isset($_POST['associate'])) {
        $_POST['associate'] = array();
    }
    check_input_parameter('associate', $_POST, true, PATTERN_ID);
    move_images_to_categories(array($_GET['image_id']), $_POST['associate']);

    invalidate_user_cache();

    // thumbnail for albums
    if (!isset($_POST['represent'])) {
        $_POST['represent'] = array();
    }
    check_input_parameter('represent', $_POST, true, PATTERN_ID);

    $no_longer_thumbnail_for = array_diff($represented_albums, $_POST['represent']);
    if (count($no_longer_thumbnail_for) > 0) {
        set_random_representant($no_longer_thumbnail_for);
    }

    $new_thumbnail_for = array_diff($_POST['represent'], $represented_albums);
    if (count($new_thumbnail_for) > 0) {
        $query = 'UPDATE '.CATEGORIES_TABLE;
        $query .= ' SET representative_picture_id = '.(int) $_GET['image_id'];
        $query .= ' WHERE id '.$conn->in($new_thumbnail_for);
        $conn->db_query($query);
    }

    $represented_albums = $_POST['represent'];
    $page['infos'][] = l10n('Photo informations updated');
}

// tags
$query = 'SELECT id,name FROM '.TAGS_TABLE.' AS t';
$query .= ' LEFT JOIN '.IMAGE_TAG_TABLE.' AS it ON t.id = it.tag_id';
$query .= ' WHERE image_id = '.$conn->db_real_escape_string($_GET['image_id']);
$query .= ' AND validated = \''.$conn->boolean_to_db(true).'\'';
$tag_selection = $services['tags']->getTagsList($query);

// retrieving direct information about picture
$query = 'SELECT * FROM '.IMAGES_TABLE.' WHERE id = '.(int) $_GET['image_id'];
$row = $conn->db_fetch_assoc($conn->db_query($query));

$storage_category_id = null;
if (!empty($row['storage_category_id'])) {
    $storage_category_id = $row['storage_category_id'];
}

$image_file = $row['file'];

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(array('picture_modify' => 'picture_modify.tpl'));

$admin_url_start = $admin_photo_base_url.'-properties';
$admin_url_start .= isset($_GET['cat_id']) ? '&amp;cat_id='.$_GET['cat_id'] : '';

$src_image = new SrcImage($row);

$template->assign(
    array(
        'tag_selection' => $tag_selection,
        'U_SYNC' => $admin_url_start.'&amp;sync_metadata=1',
        'U_DELETE' => $admin_url_start.'&amp;delete=1&amp;pwg_token='.get_pwg_token(),
        'PATH' => $row['path'],
        'TN_SRC' => DerivativeImage::url(IMG_THUMB, $src_image),
        'FILE_SRC' => DerivativeImage::url(IMG_LARGE, $src_image),
        'NAME' => isset($_POST['name']) ? stripslashes($_POST['name']) : @$row['name'],
        'TITLE' => render_element_name($row),
        'DIMENSIONS' => @$row['width'].' * '.@$row['height'],
        'FILESIZE' => @$row['filesize'].' KB',
        'REGISTRATION_DATE' => format_date($row['date_available']),
        'AUTHOR' => htmlspecialchars(isset($_POST['author']) ? stripslashes($_POST['author']) : @$row['author']),
        'DATE_CREATION' => $row['date_creation'],
        'DESCRIPTION' => htmlspecialchars( isset($_POST['description']) ? stripslashes($_POST['description']) : @$row['comment']),
        'F_ACTION' => get_root_url().'admin.php'.get_query_string_diff(array('sync_metadata'))
    )
);

$added_by = 'N/A';
$query = 'SELECT '.$conf['user_fields']['username'].' AS username FROM '.USERS_TABLE;
$query .= ' WHERE '.$conf['user_fields']['id'].' = '.$row['added_by'].';';
$result = $conn->db_query($query);
while ($user_row = $conn->db_fetch_assoc($result)) {
    $row['added_by'] = $user_row['username'];
}

$intro_vars = array(
    'file' => l10n('Original file : %s', $row['file']),
    'add_date' => l10n('Posted %s on %s', time_since($row['date_available'], 'year'), format_date($row['date_available'], array('day', 'month', 'year'))),
    'added_by' => l10n('Added by %s', $row['added_by']),
    'size' => $row['width'].'&times;'.$row['height'].' pixels, '.sprintf('%.2f', $row['filesize']/1024).'MB',
    'stats' => l10n('Visited %d times', $row['hit']),
    'id' => l10n('Numeric identifier : %d', $row['id']),
);

if ($conf['rate'] and !empty($row['rating_score'])) {
    $query = 'SELECT COUNT(1) FROM '.RATE_TABLE;
    $query .= ' WHERE element_id = '.(int) $_GET['image_id'];
    list($row['nb_rates']) = $conn->db_fetch_row($conn->db_query($query));

    $intro_vars['stats'].= ', '.sprintf(l10n('Rated %d times, score : %.2f'), $row['nb_rates'], $row['rating_score']);
}

$template->assign('INTRO', $intro_vars);

if (in_array(get_extension($row['path']),$conf['picture_ext'])) {
    $template->assign('U_COI', get_root_url().'admin.php?page=picture_coi&amp;image_id='.$_GET['image_id']);
}

// image level options
$selected_level = isset($_POST['level']) ? $_POST['level'] : $row['level'];
$template->assign(
    array(
        'level_options'=> get_privacy_level_options(),
        'level_options_selected' => array($selected_level)
    )
);

// categories
$query = 'SELECT category_id, uppercats FROM '.CATEGORIES_TABLE.' AS c';
$query .= ' LEFT JOIN '.IMAGE_CATEGORY_TABLE.' AS ic ON c.id = ic.category_id';
$query .= ' WHERE image_id = '.(int) $_GET['image_id'];
$result = $conn->db_query($query);

while ($row = $conn->db_fetch_assoc($result)) {
    $name = get_cat_display_name_cache($row['uppercats'], get_root_url().'admin.php?page=album-');

    if ($row['category_id'] == $storage_category_id) {
        $template->assign('STORAGE_CATEGORY', $name);
    } else {
        $template->append('related_categories', $name);
    }
}

// jump to link
//
// 1. find all linked categories that are reachable for the current user.
// 2. if a category is available in the URL, use it if reachable
// 3. if URL category not available or reachable, use the first reachable
//    linked category
// 4. if no category reachable, no jumpto link

$query = 'SELECT category_id FROM '.IMAGE_CATEGORY_TABLE;
$query .= ' WHERE image_id = '.(int) $_GET['image_id'];

$authorizeds = array_diff(
    $conn->query2array($query, null, 'category_id'),
    explode(',', $services['users']->calculatePermissions($user['id'], $user['status']))
);

if (isset($_GET['cat_id']) && in_array($_GET['cat_id'], $authorizeds)) {
    $url_img = make_picture_url(
        array(
            'image_id' => $_GET['image_id'],
            'image_file' => $image_file,
            'category' => $cache['cat_names'][ $_GET['cat_id'] ],
        )
    );
} else {
    foreach ($authorizeds as $category) {
        $url_img = make_picture_url(
            array(
                'image_id' => $_GET['image_id'],
                'image_file' => $image_file,
                'category' => $cache['cat_names'][ $category ],
            )
        );
        break;
    }
}

if (isset($url_img)) {
    $template->assign( 'U_JUMPTO', $url_img );
}

// associate to albums
$query = 'SELECT id FROM '.CATEGORIES_TABLE;
$query .= ' LEFT JOIN '.IMAGE_CATEGORY_TABLE.' ON id = category_id';
$query .= ' WHERE image_id = '.(int) $_GET['image_id'];
$associated_albums = $conn->query2array($query, null, 'id');

$template->assign(array(
    'associated_albums' => $associated_albums,
    'represented_albums' => $represented_albums,
    'STORAGE_ALBUM' => $storage_category_id,
    'CACHE_KEYS' => get_admin_client_cache_keys(array('tags', 'categories')),
));

trigger_notify('loc_end_picture_modify');

//----------------------------------------------------------- sending html code

$template->assign_var_from_handle('ADMIN_CONTENT', 'picture_modify');
