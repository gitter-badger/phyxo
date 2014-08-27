<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014 Nicolas Roudaire           http://phyxo.nikrou.net/ |
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
 * Management of elements set. Elements can belong to a category or to the
 * user caddie.
 *
 */

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);
check_input_parameter('selection', $_POST, true, PATTERN_ID);

// +-----------------------------------------------------------------------+
// | specific actions                                                      |
// +-----------------------------------------------------------------------+

if (isset($_GET['action'])) {
    if ('empty_caddie' == $_GET['action']) {
        $query = 'DELETE FROM '.CADDIE_TABLE.' WHERE user_id = '.$user['id'].';';
        pwg_query($query);

        $_SESSION['page_infos'] = array(
            l10n('Information data registered in database')
        );

        redirect(get_root_url().'admin.php?page='.$_GET['page']);
    }
}

// +-----------------------------------------------------------------------+
// |                      initialize current set                           |
// +-----------------------------------------------------------------------+

// filters from form
if (isset($_POST['submitFilter'])) {
    unset($_REQUEST['start']); // new photo set must reset the page
    $_SESSION['bulk_manager_filter'] = array();

    if (isset($_POST['filter_prefilter_use'])) {
        $_SESSION['bulk_manager_filter']['prefilter'] = $_POST['filter_prefilter'];

        if ('duplicates' == $_POST['filter_prefilter']) {
            if (isset($_POST['filter_duplicates_date'])) {
                $_SESSION['bulk_manager_filter']['duplicates_date'] = true;
            }

            if (isset($_POST['filter_duplicates_dimensions'])) {
                $_SESSION['bulk_manager_filter']['duplicates_dimensions'] = true;
            }
        }
    }

    if (isset($_POST['filter_category_use'])) {
        $_SESSION['bulk_manager_filter']['category'] = $_POST['filter_category'];

        if (isset($_POST['filter_category_recursive'])) {
            $_SESSION['bulk_manager_filter']['category_recursive'] = true;
        }
    }

    if (isset($_POST['filter_tags_use'])) {
        $_SESSION['bulk_manager_filter']['tags'] = get_tag_ids($_POST['filter_tags'], false);

        if (isset($_POST['tag_mode']) and in_array($_POST['tag_mode'], array('AND', 'OR'))) {
            $_SESSION['bulk_manager_filter']['tag_mode'] = $_POST['tag_mode'];
        }
    }

    if (isset($_POST['filter_level_use'])) {
        if (in_array($_POST['filter_level'], $conf['available_permission_levels'])) {
            $_SESSION['bulk_manager_filter']['level'] = $_POST['filter_level'];

            if (isset($_POST['filter_level_include_lower'])) {
                $_SESSION['bulk_manager_filter']['level_include_lower'] = true;
            }
        }
    }

    if (isset($_POST['filter_dimension_use'])) {
        foreach (array('min_width','max_width','min_height','max_height') as $type) {
            if (filter_var($_POST['filter_dimension_'.$type], FILTER_VALIDATE_INT) !== false) {
                $_SESSION['bulk_manager_filter']['dimension'][$type] = $_POST['filter_dimension_'. $type ];
            }
        }
        foreach (array('min_ratio','max_ratio') as $type) {
            if (filter_var($_POST['filter_dimension_'.$type], FILTER_VALIDATE_FLOAT) !== false) {
                $_SESSION['bulk_manager_filter']['dimension'][$type] = $_POST['filter_dimension_'. $type ];
            }
        }
    }

    if (isset($_POST['filter_filesize_use'])) {
        foreach (array('min','max') as $type) {
            if (filter_var($_POST['filter_filesize_'.$type], FILTER_VALIDATE_FLOAT) !== false) {
                $_SESSION['bulk_manager_filter']['filesize'][$type] = $_POST['filter_filesize_'. $type ];
            }
        }
    }

    if (isset($_POST['filter_search_use'])) {
        $_SESSION['bulk_manager_filter']['search']['q'] = $_POST['q'];
    }
} elseif (isset($_GET['filter'])) { // filters from url
    if (!is_array($_GET['filter'])) {
        $_GET['filter'] = explode(',', $_GET['filter']);
    }

    $_SESSION['bulk_manager_filter'] = array();

    foreach ($_GET['filter'] as $filter) {
        list($type, $value) = explode('-', $filter);

        switch ($type) {
        case 'prefilter':
            $_SESSION['bulk_manager_filter']['prefilter'] = $value;
            break;

        case 'album':
            if (is_numeric($value)) {
                $_SESSION['bulk_manager_filter']['category'] = $value;
            }
            break;

        case 'tag':
            if (is_numeric($value)) {
                $_SESSION['bulk_manager_filter']['tags'] = array($value);
                $_SESSION['bulk_manager_filter']['tag_mode'] = 'AND';
            }
            break;

        case 'level':
            if (is_numeric($value) && in_array($value, $conf['available_permission_levels'])) {
                $_SESSION['bulk_manager_filter']['level'] = $value;
            }
            break;
        }
    }
}

if (empty($_SESSION['bulk_manager_filter'])) {
    $_SESSION['bulk_manager_filter'] = array(
        'prefilter' => 'caddie'
    );
}

// depending on the current filter (in session), we find the appropriate photos
$filter_sets = array();
if (isset($_SESSION['bulk_manager_filter']['prefilter'])) {
    switch ($_SESSION['bulk_manager_filter']['prefilter']) {
    case 'caddie':
        $query = 'SELECT element_id FROM '.CADDIE_TABLE.' WHERE user_id = '.$user['id'].';';
        $filter_sets[] = query2array($query, null, 'element_id');
        break;

    case 'favorites':
        $query = 'SELECT image_id FROM '.FAVORITES_TABLE.' WHERE user_id = '.$user['id'].';';
        $filter_sets[] = query2array($query, null, 'image_id');
        break;

    case 'last_import':
        $query = 'SELECT MAX(date_available) AS date FROM '.IMAGES_TABLE.';';
        $row = pwg_db_fetch_assoc(pwg_query($query));
        if (!empty($row['date'])) {
            $query = 'SELECT id FROM '.IMAGES_TABLE;
            $query .= ' WHERE date_available BETWEEN ';
            $query .= pwg_db_get_recent_period_expression(1, $row['date']).' AND \''.$row['date'].'\';';
            $filter_sets[] = query2array($query, null, 'id');
        }
        break;

    case 'no_virtual_album':
        // we are searching elements not linked to any virtual category
        $query = ' SELECT id FROM '.IMAGES_TABLE.';';
        $all_elements = query2array($query, null, 'id');

        $query = 'SELECT id FROM '.CATEGORIES_TABLE.' WHERE dir IS NULL;';
        $virtual_categories = array_from_query($query, 'id');
        if (!empty($virtual_categories)) {
            $query = 'SELECT DISTINCT(image_id) FROM '.IMAGE_CATEGORY_TABLE;
            $query .= ' WHERE category_id IN ('.implode(',', $virtual_categories).');';
            $linked_to_virtual = query2array($query, null, 'image_id');
        }
        $filter_sets[] = array_diff($all_elements, $linked_to_virtual);
    break;

    case 'no_album':
        $query = 'SELECT id FROM '.IMAGES_TABLE;
        $query .= ' LEFT JOIN '.IMAGE_CATEGORY_TABLE.' ON id = image_id';
        $query .= ' WHERE category_id is null;';
        $filter_sets[] = query2array($query, null, 'id');
        break;

    case 'no_tag':
        $query = 'SELECT id FROM '.IMAGES_TABLE;
        $query .= ' LEFT JOIN '.IMAGE_TAG_TABLE.' ON id = image_id';
        $query .= ' WHERE tag_id is null;';
        $filter_sets[] = query2array($query, null, 'id');
        break;

    case 'duplicates':
        $duplicates_on_fields = array('file');

        if (isset($_SESSION['bulk_manager_filter']['duplicates_date'])) {
            $duplicates_on_fields[] = 'date_creation';
        }

        if (isset($_SESSION['bulk_manager_filter']['duplicates_dimensions'])) {
            $duplicates_on_fields[] = 'width';
            $duplicates_on_fields[] = 'height';
        }

        $query = 'SELECT '.pwg_db_group_concat('id').' AS ids FROM '.IMAGES_TABLE;
        $query .= ' GROUP BY '.implode(',', $duplicates_on_fields).' HAVING COUNT(*) > 1;';
        $array_of_ids_string = query2array($query, null, 'ids');

        $ids = array();

        foreach ($array_of_ids_string as $ids_string) {
            $ids = array_merge($ids, explode(',', $ids_string));
        }

        $filter_sets[] = $ids;
    break;

    case 'all_photos':
        if (count($_SESSION['bulk_manager_filter']) == 1) { // make the query only if this is the only filter
            $query = 'SELECT id FROM '.IMAGES_TABLE.' '.$conf['order_by'];
            $filter_sets[] = query2array($query, null, 'id');
        }
        break;
    }

    $filter_sets = trigger_change('perform_batch_manager_prefilters', $filter_sets, $_SESSION['bulk_manager_filter']['prefilter']);
}

if (isset($_SESSION['bulk_manager_filter']['category'])) {
    $categories = array();

    if (isset($_SESSION['bulk_manager_filter']['category_recursive'])) {
        $categories = get_subcat_ids(array($_SESSION['bulk_manager_filter']['category']));
    } else {
        $categories = array($_SESSION['bulk_manager_filter']['category']);
    }

    $query = 'SELECT DISTINCT(image_id) FROM '.IMAGE_CATEGORY_TABLE;
    $query .= ' WHERE category_id IN ('.implode(',', $categories).');';
    $filter_sets[] = query2array($query, null, 'image_id');
}

if (isset($_SESSION['bulk_manager_filter']['level'])) {
    $operator = '=';
    if (isset($_SESSION['bulk_manager_filter']['level_include_lower'])) {
        $operator = '<=';
    }

    $query = 'SELECT id FROM '.IMAGES_TABLE;
    $query .= ' WHERE level '.$operator.' '.$_SESSION['bulk_manager_filter']['level'].' '.$conf['order_by'];

    $filter_sets[] = query2array($query, null, 'id');
}

if (!empty($_SESSION['bulk_manager_filter']['tags'])) {
    $filter_sets[] = get_image_ids_for_tags(
        $_SESSION['bulk_manager_filter']['tags'],
        $_SESSION['bulk_manager_filter']['tag_mode'],
        null,
        null,
        false // we don't apply permissions in administration screens
    );
}

if (isset($_SESSION['bulk_manager_filter']['dimension'])) {
    $where_clauses = array();
    if (isset($_SESSION['bulk_manager_filter']['dimension']['min_width'])) {
        $where_clause[] = 'width >= '.$_SESSION['bulk_manager_filter']['dimension']['min_width'];
    }
    if (isset($_SESSION['bulk_manager_filter']['dimension']['max_width'])) {
        $where_clause[] = 'width <= '.$_SESSION['bulk_manager_filter']['dimension']['max_width'];
    }
    if (isset($_SESSION['bulk_manager_filter']['dimension']['min_height'])) {
        $where_clause[] = 'height >= '.$_SESSION['bulk_manager_filter']['dimension']['min_height'];
    }
    if (isset($_SESSION['bulk_manager_filter']['dimension']['max_height'])) {
        $where_clause[] = 'height <= '.$_SESSION['bulk_manager_filter']['dimension']['max_height'];
    }
    if (isset($_SESSION['bulk_manager_filter']['dimension']['min_ratio'])) {
        $where_clause[] = 'width/height >= '.$_SESSION['bulk_manager_filter']['dimension']['min_ratio'];
    }
    if (isset($_SESSION['bulk_manager_filter']['dimension']['max_ratio'])) {
        // max_ratio is a floor value, so must be a bit increased
      $where_clause[] = 'width/height < '.($_SESSION['bulk_manager_filter']['dimension']['max_ratio']+0.01);
    }

    $query = 'SELECT id FROM '.IMAGES_TABLE.' WHERE '.implode(' AND ',$where_clause).' '.$conf['order_by'];

    $filter_sets[] = query2array($query, null, 'id');
}

if (isset($_SESSION['bulk_manager_filter']['search'])) {
    include_once( PHPWG_ROOT_PATH .'include/functions_search.inc.php' );
    $res = get_quick_search_results_no_cache($_SESSION['bulk_manager_filter']['search']['q'], array('permissions'=>false));
    $filter_sets[] = $res['items'];
}

$current_set = array_shift($filter_sets);
foreach ($filter_sets as $set) {
    $current_set = array_intersect($current_set, $set);
}
$page['cat_elements_id'] = $current_set;


// +-----------------------------------------------------------------------+
// |                       first element to display                        |
// +-----------------------------------------------------------------------+

// $page['start'] contains the number of the first element in its
// category. For exampe, $page['start'] = 12 means we must show elements #12
// and $page['nb_images'] next elements

if (!isset($_REQUEST['start']) || !is_numeric($_REQUEST['start']) || $_REQUEST['start'] < 0
|| (isset($_REQUEST['display']) && 'all' == $_REQUEST['display'])) {
    $page['start'] = 0;
} else {
    $page['start'] = $_REQUEST['start'];
}


// +-----------------------------------------------------------------------+
// |                                 Tabs                                  |
// +-----------------------------------------------------------------------+
$manager_link = get_root_url().'admin.php?page=batch_manager&amp;mode=';
if (isset($_GET['mode'])) {
    $page['tab'] = $_GET['mode'];
} else {
    $page['tab'] = 'global';
}

$tabsheet = new tabsheet();
$tabsheet->set_id('batch_manager');
$tabsheet->select($page['tab']);
$tabsheet->assign();


// +-----------------------------------------------------------------------+
// |                              dimensions                               |
// +-----------------------------------------------------------------------+

$widths = array();
$heights = array();
$ratios = array();

// get all width, height and ratios
$query = 'SELECT DISTINCT width, height FROM '.IMAGES_TABLE;
$query .= ' WHERE width IS NOT NULL AND height IS NOT NULL;';
$result = pwg_query($query);

if (pwg_db_num_rows($result)) {
    while ($row = pwg_db_fetch_assoc($result)) {
        if ($row['width']>0 && $row['height']>0) {
            $widths[] = $row['width'];
            $heights[] = $row['height'];
            $ratios[] = floor($row['width'] / $row['height'] * 100) / 100;
        }
    }
}
if (empty($widths)) { // arbitrary values, only used when no photos on the gallery
    $widths = array(600, 1920, 3500);
    $heights = array(480, 1080, 2300);
    $ratios = array(1.25, 1.52, 1.78);
}

$widths = array_unique($widths);
sort($widths);

$heights = array_unique($heights);
sort($heights);

$ratios = array_unique($ratios);
sort($ratios);

$dimensions['widths'] = implode(',', $widths);
$dimensions['heights'] = implode(',', $heights);
$dimensions['ratios'] = implode(',', $ratios);

$dimensions['bounds'] = array(
    'min_width' => $widths[0],
    'max_width' => $widths[count($widths)-1],
    'min_height' => $heights[0],
    'max_height' => $heights[count($heights)-1],
    'min_ratio' => $ratios[0],
    'max_ratio' => $ratios[count($ratios)-1],
);

// find ratio categories
$ratio_categories = array(
    'portrait' => array(),
    'square' => array(),
    'landscape' => array(),
    'panorama' => array(),
);

foreach ($ratios as $ratio) {
    if ($ratio < 0.95) {
        $ratio_categories['portrait'][] = $ratio;
    } elseif ($ratio >= 0.95 and $ratio <= 1.05) {
        $ratio_categories['square'][] = $ratio;
    } elseif ($ratio > 1.05 and $ratio < 2) {
        $ratio_categories['landscape'][] = $ratio;
    } elseif ($ratio >= 2) {
        $ratio_categories['panorama'][] = $ratio;
    }
}

foreach (array_keys($ratio_categories) as $ratio_category) {
    if (count($ratio_categories[$ratio_category]) > 0) {
        $dimensions['ratio_'.$ratio_category] = array(
            'min' => $ratio_categories[$ratio_category][0],
            'max' => array_pop($ratio_categories[$ratio_category]),
        );
    }
}

// selected=bound if nothing selected
foreach (array_keys($dimensions['bounds']) as $type) {
    $dimensions['selected'][$type] = isset($_SESSION['bulk_manager_filter']['dimension'][$type])
        ? $_SESSION['bulk_manager_filter']['dimension'][$type]
        : $dimensions['bounds'][$type];
}

$template->assign('dimensions', $dimensions);

// +-----------------------------------------------------------------------+
// |                         open specific mode                            |
// +-----------------------------------------------------------------------+

include(PHPWG_ROOT_PATH.'admin/batch_manager_'.$page['tab'].'.php');
