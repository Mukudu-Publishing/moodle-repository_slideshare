<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Required class file.
 *
 * @package   repository_slideshare
 * @copyright 2019 - 2021 Mukudu Ltd - Bham UK
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Our respository class.
 *
 * @package   repository_slideshare
 * @copyright 2019 - 2021 Mukudu Ltd - Bham UK
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_slideshare extends repository {

    /**
     * Tells how the file can be picked from this repository
     *
     * Maximum value is FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE
     *
     * @return int
     */
    public function supported_returntypes() {
        return FILE_EXTERNAL;
    }

    /**
     * Return names of the general options.
     * By default: no general option name
     *
     * @return array
     */
    public static function get_type_option_names() {
        $myoptions = array(
            'apikey',
            'apisecret',
            'filesperpage'
        );
        return array_merge(parent::get_type_option_names(), $myoptions);
    }

    /**
     * Edit/Create Admin Settings Moodle form
     *
     * @param moodleform $mform Moodle form (passed by reference)
     * @param string $classname repository class name
     */
    public static function type_config_form($mform, $classname = 'repository') {

        parent::type_config_form($mform);

        $currentoptions = (array) get_config('repository_slideshare');

        $mform->addElement('text', 'apikey', get_string('apikeyprompt', 'repository_slideshare'), array('size' => '25'));
        if (!empty($currentoptions['apikey'])) {
            $mform->setDefault('apikey', $currentoptions['apikey']);
        }
        $mform->addHelpButton('apikey', 'apikeyprompt', 'repository_slideshare');
        $mform->setType('apikey', PARAM_TEXT);

        $mform->addElement('text', 'apisecret', get_string('apisecretprompt', 'repository_slideshare'), array('size' => '25'));
        if (!empty($currentoptions['apisecret'])) {
            $mform->setDefault('apisecret', $currentoptions['apisecret']);
        }
        $mform->setType('apisecret', PARAM_TEXT);

        $mform->addElement('text', 'filesperpage', get_string('filesperpageprompt', 'repository_slideshare'),
                array('size' => '5'));
        if (empty($currentoptions['filesperpage'])) {
            $mform->setDefault('filesperpage', 20);
        } else {
            $mform->setDefault('filesperpage', $currentoptions['filesperpage']);
        }
        $mform->setType('filesperpage', PARAM_INT);
    }

    /**
     * Return names of the instance options.
     * By default: no instance option name
     *
     * @return array
     */
    public static function get_instance_option_names() {
        return array(
            'searchterm',
            'resultsperpage'
        );
    }

    /**
     * Edit/Create Instance Settings Moodle form
     *
     * @param moodleform $mform Moodle form (passed by reference)
     */
    public static function instance_config_form($mform) {
        $mform->addElement('text', 'searchterm', get_string('searchtermprompt', 'repository_slideshare'), array('size' => '25'));
        $mform->setType('searchterm', PARAM_TEXT);
        $mform->addRule('searchterm', get_string('required'), 'required');

        $mform->addElement('text', 'resultsperpage', get_string('resultsperpageprompt', 'repository_slideshare'),
                array('size' => '5'));
        $mform->setDefault('resultsperpage', get_config('repository_slideshare', 'filesperpage'));
        $mform->setType('resultsperpage', PARAM_INT);
    }

    /**
     * Given a path, and perhaps a search, get a list of files.
     *
     * See details on {@link http://docs.moodle.org/dev/Repository_plugins}
     *
     * @param string $path this parameter can a folder name, or a identification of folder
     * @param string $page the page number of file list
     * @return array the list of files, including meta infomation, containing the following keys
     *           manage, url to manage url
     *           client_id
     *           login, login form
     *           repo_id, active repository id
     *           login_btn_action, the login button action
     *           login_btn_label, the login button label
     *           total, number of results
     *           perpage, items per page
     *           page
     *           pages, total pages
     *           issearchresult, is it a search result?
     *           list, file list
     *           path, current path and parent path
     */
    public function get_listing($path = '', $page = '') {

        $options = $this->options;
        $config = get_config($options['type']);    // BUG.

        $ts = time();
        $query = $options['searchterm'];
        $page = $page ? $page : 1;
        $items = $options['resultsperpage'] ??
        $config->filesperpage;

        $apiurl = 'https://www.slideshare.net/api/2/search_slideshows';

        $requestparams = array(
            'api_key' => $config->apikey,
            'ts' => $ts,
            'hash' => sha1($config->apisecret.$ts),
            'q' => $query,
            'page' => $page,
            'items_per_page' => $items
        );
        $requesturl = $apiurl .'?' . http_build_query($requestparams, null, '&');

        $contents = simplexml_load_file($requesturl);

        if ($contents === false) {
            if (empty($errors = libxml_get_errors())) {
                return array('list' => array());
            } else {
                print_error('parseerror', 'repository_slideshare');
            }
        } else {
            if (empty($contents->SlideShareServiceError)) {
                $meta = (array) $contents->Meta;
                $pages = (int) $meta['TotalResults'];
                $results = array(
                    'dynload' => true,
                    'page' => $page,
                    'pages' => $pages > $items ? -1 : '',
                    'norefresh' => true,
                    'nosearch' => true,
                    'nologin' => true,
                    'list' => array()
                );

                foreach ($contents->Slideshow as $slideshow) {

                    // Turn to array for simplicity.
                    $slideshow = (array) $slideshow;
                    $sizes = preg_replace('/[\[\]]/', '', $slideshow['ThumbnailSize']);

                    list($w, $h) = explode(',', $sizes);
                    $results['list'][] = array(
                        'title' => $slideshow['Title'],
                        'datemodified' => strtotime($slideshow['Created']),
                        'datecreated' => strtotime($slideshow['Updated']),
                        'thumbnail' => $slideshow['ThumbnailURL'],
                        'thumbnail_width' => $w,
                        'thumbnail_height' => $h,
                        'url' => $slideshow['URL'],
                    );
                }
                return $results;
            } else {
                print_error('apierror', 'repository_slideshare', null, $contents->SlideShareServiceError->Message);
            }
        }
    }
}