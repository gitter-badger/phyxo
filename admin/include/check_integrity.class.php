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

class check_integrity
{
    private $ignore_list, $retrieve_list, $build_ignore_list;

    public function check_integrity() {
        $this->ignore_list = array();
        $this->retrieve_list = array();
        $this->build_ignore_list = array();
    }

    /**
     * Check integrities
     *
     * @param void
     * @return void
     */
    public function check() {
        global $page, $header_notes, $conf;

        // Ignore list
        $conf_c13y_ignore = unserialize($conf['c13y_ignore']);
        if (is_array($conf_c13y_ignore) && isset($conf_c13y_ignore['version'])
            && ($conf_c13y_ignore['version'] == PHPWG_VERSION) && is_array($conf_c13y_ignore['list'])) {
            $ignore_list_changed = false;
            $this->ignore_list = $conf_c13y_ignore['list'];
        } else {
            $ignore_list_changed = true;
            $this->ignore_list = array();
        }

        // Retrieve list
        $this->retrieve_list = array();
        $this->build_ignore_list = array();

        trigger_notify('list_check_integrity', $this);

        // Information
        if (count($this->retrieve_list) > 0) {
            $header_notes[] = l10n_dec(
                '%d anomaly has been detected.', '%d anomalies have been detected.',
                count($this->retrieve_list)
            );
        }

        // Treatments
        if (isset($_POST['c13y_submit_correction']) and isset($_POST['c13y_selection'])) {
            $corrected_count = 0;
            $not_corrected_count = 0;

            foreach ($this->retrieve_list as $i => $c13y) {
                if (!empty($c13y['correction_fct']) and
                $c13y['is_callable'] and
                in_array($c13y['id'], $_POST['c13y_selection'])) {
                    if (is_array($c13y['correction_fct_args'])) {
                        $args = $c13y['correction_fct_args'];
                    } elseif (!is_null($c13y['correction_fct_args'])) {
                        $args = array($c13y['correction_fct_args']);
                    } else {
                        $args = array();
                    }
                    $this->retrieve_list[$i]['corrected'] = call_user_func_array($c13y['correction_fct'], $args);

                    if ($this->retrieve_list[$i]['corrected']) {
                        $corrected_count += 1;
                    } else {
                        $not_corrected_count += 1;
                    }
                }
            }

            if ($corrected_count > 0) {
                $page['infos'][] = l10n_dec(
                    '%d anomaly has been corrected.', '%d anomalies have been detected corrected.',
                    $corrected_count
                );
            }
            if ($not_corrected_count > 0) {
                $page['errors'][] = l10n_dec(
                    '%d anomaly has not been corrected.', '%d anomalies have not been corrected.',
                    $not_corrected_count
                );
            }
        } else {
            if (isset($_POST['c13y_submit_ignore']) and isset($_POST['c13y_selection'])) {
                $ignored_count = 0;

                foreach ($this->retrieve_list as $i => $c13y) {
                    if (in_array($c13y['id'], $_POST['c13y_selection'])) {
                        $this->build_ignore_list[] = $c13y['id'];
                        $this->retrieve_list[$i]['ignored'] = true;
                        $ignored_count += 1;
                    }
                }

                if ($ignored_count > 0) {
                    $page['infos'][] = l10n_dec(
                        '%d anomaly has been ignored.', '%d anomalies have been ignored.',
                        $ignored_count
                    );
                }
            }
        }

        $ignore_list_changed = (
            ($ignore_list_changed) or
            (count(array_diff($this->ignore_list, $this->build_ignore_list)) > 0) or
            (count(array_diff($this->build_ignore_list, $this->ignore_list)) > 0)
        );

        if ($ignore_list_changed) {
            $this->update_conf($this->build_ignore_list);
        }
    }

    /**
     * Display anomalies list
     *
     * @param void
     * @return void
     */
    public function display() {
        global $template;

        $check_automatic_correction = false;
        $submit_automatic_correction = false;
        $submit_ignore = false;

        if (isset($this->retrieve_list) and count($this->retrieve_list) > 0) {
            $template->set_filenames(array('check_integrity' => 'check_integrity.tpl'));

            foreach ($this->retrieve_list as $i => $c13y) {
                $can_select = false;
                $c13y_display = array(
                    'id' => $c13y['id'],
                    'anomaly' => $c13y['anomaly'],
                    'show_ignore_msg' => false,
                    'show_correction_success_fct' => false,
                    'correction_error_fct' => '',
                    'show_correction_fct' => false,
                    'correction_error_fct' => '',
                    'show_correction_bad_fct' => false,
                    'correction_msg' => ''
                );

                if (isset($c13y['ignored'])) {
                    if ($c13y['ignored']) {
                        $c13y_display['show_ignore_msg'] = true;
                    } else {
                        die('$c13y[\'ignored\'] cannot be false');
                    }
                } else {
                    if (!empty($c13y['correction_fct'])) {
                        if (isset($c13y['corrected'])) {
                            if ($c13y['corrected']) {
                                $c13y_display['show_correction_success_fct'] = true;
                            } else {
                                $c13y_display['correction_error_fct'] = $this->get_htlm_links_more_info();
                            }
                        } elseif ($c13y['is_callable']) {
                            $c13y_display['show_correction_fct'] = true;
                            $template->append('c13y_do_check', $c13y['id']);
                            $submit_automatic_correction = true;
                            $can_select = true;
                        } else {
                            $c13y_display['show_correction_bad_fct'] = true;
                            $can_select = true;
                        }
                    } else {
                        $can_select = true;
                    }

                    if (!empty($c13y['correction_msg']) and !isset($c13y['corrected'])) {
                        $c13y_display['correction_msg'] = $c13y['correction_msg'];
                    }
                }

                $c13y_display['can_select'] = $can_select;
                if ($can_select) {
                    $submit_ignore = true;
                }

                $template->append('c13y_list', $c13y_display);
            }

            $template->assign('c13y_show_submit_automatic_correction', $submit_automatic_correction);
            $template->assign('c13y_show_submit_ignore', $submit_ignore);

            $template->concat('ADMIN_CONTENT', $template->parse('check_integrity', true));

        }
    }

    /**
     * Add anomaly data
     *
     * @param anomaly arguments
     * @return void
     */
    public function add_anomaly($anomaly, $correction_fct = null, $correction_fct_args = null, $correction_msg = null) {
        $id = md5($anomaly.$correction_fct.serialize($correction_fct_args).$correction_msg);

        if (in_array($id, $this->ignore_list)) {
            $this->build_ignore_list[] = $id;
        } else {
            $this->retrieve_list[] =
                array(
                    'id' => $id,
                    'anomaly' => $anomaly,
                    'correction_fct' => $correction_fct,
                    'correction_fct_args' => $correction_fct_args,
                    'correction_msg' => $correction_msg,
                    'is_callable' => is_callable($correction_fct));
        }
    }

    /**
     * Update table config
     *
     * @param ignore list array
     * @return void
     */
    public function update_conf($conf_ignore_list = array()) {
        global $conn;

        $conf_c13y_ignore =  array();
        $conf_c13y_ignore['version'] = PHPWG_VERSION;
        $conf_c13y_ignore['list'] = $conf_ignore_list;
        $query = 'UPDATE '.CONFIG_TABLE.' SET value =\''.serialize($conf_c13y_ignore).'\' WHERE param = \'c13y_ignore\';';
        $conn->db_query($query);
    }

    /**
     * Apply maintenance
     *
     * @param void
     * @return void
     */
    public function maintenance() {
        $this->update_conf();
    }

    /**
     * Returns links more informations
     *
     * @param void
     * @return html links
     */
    public function get_htlm_links_more_info() {
        $pwg_links = pwg_URL();
        $link_fmt = '<a href="%s" onclick="window.open(this.href, \'\'); return false;">%s</a>';
        return
            sprintf(
                l10n('Go to %s or %s for more informations'),
                sprintf($link_fmt, $pwg_links['FORUM'], l10n('the forum')),
                sprintf($link_fmt, $pwg_links['WIKI'], l10n('the wiki'))
            );
    }
}
