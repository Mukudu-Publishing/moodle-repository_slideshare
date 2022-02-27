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

class repository_slideshare extends repository {
    
    /* Documentation says override but in practise does not appear to be required */
    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array(), $readonly = 0) {
        /*
         The possible items in the $options array are:
         'ajax' - bool, true if the user is using the AJAX filepicker
         'mimetypes' - array of accepted mime types, or '*' for all types
         */
        parent::__construct($repositoryid, $context, $options, $readonly);
    }
    
    public function get_listing($path = '', $page = '') {
        
        $options = $this->options;
        $config = get_config($options['type']);    // BUG.
        
        $ts = time();
        $query = $options['searchterm'];
        $page = $page ? $page : 1;
        $items = $options['resultsperpage'] ?? $config->filesperpage;
        
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
        
        /* could also have used file() or file_get_contents() or even Moodle curl or PHP curl */
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
    
    public function supported_returntypes() {
        return FILE_EXTERNAL;
    }
    
    // supported_filetypes() method not defined as we accept the default '*'
    
    /* must be statically defined */
    public static function get_type_option_names() {
        $myoptions = array(
            'apikey',
            'apisecret',
            'filesperpage'
        );
        return array_merge(parent::get_type_option_names(), $myoptions);
    }
    
    /*
     To avoid PHP warnings, the parent function stipulates an additional parameter $classname,
     with a default value of 'repository', which you need to replicate.
     Note statically declared.
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
    
    /* type_form_validation() static method that allows validation of form is not defined - we are not using it */
    
    public static function get_instance_option_names() {
        return array(
            'searchterm',
            'resultsperpage'
        );
    }
    
    public static function instance_config_form($mform) {
        $mform->addElement('text', 'searchterm', get_string('searchtermprompt', 'repository_slideshare'), array('size' => '25'));
        $mform->setType('searchterm', PARAM_TEXT);
        $mform->addRule('searchterm', get_string('required'), 'required');
        
        $mform->addElement('text', 'resultsperpage', get_string('resultsperpageprompt', 'repository_slideshare'),
            array('size' => '5'));
        $mform->setDefault('resultsperpage', get_config('repository_slideshare', 'filesperpage'));
        $mform->setType('resultsperpage', PARAM_INT);
    }
    
    /* instance_form_validation() static method not used so not defined */
    
    
}
