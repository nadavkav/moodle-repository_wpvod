<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This plugin is used to access wpvod videos
 *
 * @since Moodle 2.0
 * @package    repository_wpvod
 * @copyright  2010 Dongsheng Cai {@link http://dongsheng.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot . '/repository/lib.php');
//use curl;
/**
 * repository_wpvod class
 *
 * @since Moodle 2.0
 * @package    repository_wpvod
 * @copyright  2009 Dongsheng Cai {@link http://dongsheng.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class repository_wpvod extends repository {
    /** @var int maximum number of thumbs per page */
    const wpvod_THUMBS_PER_PAGE = 15;

    /**
     * API key for using the wpvod Data API.
     * @var mixed
     */
    private $apikey;

    /**
     * Internal Wordpress VOD REST API URL.
     * @var mixed
     */
    private $wpresturl;

    /**
     * Public Wordpress VOD - KALTURA streamer NGINX plug settings URL.
     * @var mixed
     */
    private $wpvodurl;

    /**
     * Wordpress RESTFul API v2 orderby types
     * https://v2.wp-api.org/reference/media/
     * 
     */
    private $orderbytypes = array('relevance' => 'relevance', 'date' => 'date', 'title' => 'title', 'id' => 'id');

    /**
    * wpvod plugin constructor
    * @param int $repositoryid
    * @param object $context
    * @param array $options
    */
    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        parent::__construct($repositoryid, $context, $options);

        $this->apikey = $this->get_option('apikey');

        // Internal URL, not open to the world
        // needs to be translated to a public URL,
        // specially setup to use KALTURA streamer NGINX plug settings
        //
        // NOTE: If you do not seperate public and internal networks,
        //       both "wpresturl" and "wpvodurl" should be the same URL
        $this->wpresturl = get_config('wpvod', 'wpresturl');
        if (empty($this->wpresturl)) {
            $this->wpresturl = 'http://davidsonvod.weizmann.ac.il:8080/wordpress';
        }
        // KALTURA streamer NGINX plug settings URL
        // https://gist.github.com/nadavkav/56f227572afd0c82d06425a79151886c#file-usr_local_nginx_conf_nginx-conf-L145
        $this->wpvodurl = get_config('wpvod', 'wpvodurl');
        if (empty($this->wpvodurl)) {
            $this->wpvodurl = 'https://davidsonvod.weizmann.ac.il/vod';
        }
    }

    /**
     * Save apikey in config table.
     * @param array $options
     * @return boolean
     */
    public function set_option($options = array()) {
        if (!empty($options['apikey'])) {
            set_config('apikey', trim($options['apikey']), 'wpvod');
        }
        unset($options['apikey']);
        return parent::set_option($options);
    }

    /**
     * Get apikey from config table.
     *
     * @param string $config
     * @return mixed
     */
    public function get_option($config = '') {
        if ($config === 'apikey') {
            return trim(get_config('wpvod', 'apikey'));
        } else {
            $options['apikey'] = trim(get_config('wpvod', 'apikey'));
        }
        return parent::get_option($config);
    }

    public function check_login() {
        return !empty($this->keyword);
    }

    /**
     * Return search results
     * @param string $search_text
     * @param int $page
     * @return array
     */
    public function search($search_text, $page = 0) {
        global $SESSION;
        $orderby = optional_param('wpvod_orderby', '', PARAM_TEXT);
        $mode = optional_param('wpvod_mode', '', PARAM_TEXT);

        $sess_keyword = 'wpvod_'.$this->id.'_keyword';
        $sess_orderby = 'wpvod_'.$this->id.'_orderby';
        $sess_mode = 'wpvod_'.$this->id.'_mode';

        // This is the request of another page for the last search, retrieve the cached keyword and orderby
        if ($page && !$search_text && isset($SESSION->{$sess_keyword})) {
            $search_text = $SESSION->{$sess_keyword};
        }
        if ($page && !$orderby && isset($SESSION->{$sess_orderby})) {
            $orderby = $SESSION->{$sess_orderby};
        }
        if (!$orderby) {
            $orderby = 'relevance'; // default
        }
        if ($page && !$mode && isset($SESSION->{$sess_mode})) {
            $mode = $SESSION->{$sess_mode};
        }

        // Save this search in session
        $SESSION->{$sess_keyword} = $search_text;
        $SESSION->{$sess_orderby} = $orderby;
        $SESSION->{$sess_mode} = $mode;

        $this->keyword = $search_text;
        $ret  = array();
        $ret['nologin'] = true;
        $ret['page'] = (int)$page;
        if ($ret['page'] < 1) {
            $ret['page'] = 1;
        }
        $start = ($ret['page'] - 1) * self::wpvod_THUMBS_PER_PAGE;
        $max = self::wpvod_THUMBS_PER_PAGE;
        $ret['list'] = $this->_get_collection($search_text, $start, $max, $orderby, $mode);
        //$ret['norefresh'] = true;
        $ret['nosearch'] = true;
        // If the number of results is smaller than $max, it means we reached the last page.
        $ret['pages'] = (count($ret['list']) < $max) ? $ret['page'] : -1;
        return $ret;
    }

    /**
     * Private method to get wpvod search results
     * @param string $keyword
     * @param int $start
     * @param int $max max results
     * @param string $orderby
     * @param string $mode
     * @throws moodle_exception If the google API returns an error.
     * @return array
     */
    private function _get_collection($keyword, $start, $max, $orderby, $mode) {
        global $DB, $SESSION;

        $list = array();
        $error = null;

        list($context, $course, $cm) = get_context_info_array($this->context->id);
        $courseid = is_object($course) ? $course->id : SITEID;
        //$context = context_course::instance($courseid);

        // https://v2.wp-api.org/reference/media/
        //
        if (empty($keyword) && $mode == 'currentcourse') {
            $keyword = $courseid;
        }
        // filter[wp_query-arguments] - http://www.billerickson.net/code/wp_query-arguments/
        //$params = array('search' => $keyword, 'offset' => $start, 'per_page' => $max, 'orderby' => $orderby, 'mime_type' => 'video/mp4');
        $params = array('search' => $keyword, 'offset' => $start, 'per_page' => $max, 'orderby' => $orderby, 'mime_type' => 'video/mp4');
        //$params = array('search' => $keyword, 'offset' => $start);
        $wsurl = $this->wpresturl.'/wp-json/wp/v2/media';

        // TODO: Use Moodle's cURL
        $curl_vodlist= new \curl(array('proxy' => false));
        $vodlist_json = $curl_vodlist->get($wsurl, $params);
//var_dump($vodlist_json);die;
        // My cURL
        //$vodlist = $this->ws_get($wsurl);
        $vodlist = json_decode($vodlist_json, true);
//var_dump($vodlist);die;
        foreach ($vodlist as $voditem) {
            //preg_match('/unickoid=(\d{1,4})+/',$voditem['description'], $matches);
            //$unickoid = $matches[1].'<br>';

            // Get Moodle course id from WS response
            preg_match('/mcid=(\d{1,4})+/',$voditem['description'], $matches);
            $mcid = $matches[1];

            //preg_match('/mrid=(\d{1,4})+/',$voditem['description'], $matches);
            //$mrid = $matches[1].'<br>';

            // Only get relevant videos for current course. (if mode == currentcourse)
            if (($mode == 'currentcourse') && ($courseid != $mcid)) continue;

            $thiscourse = $DB->get_record('course', array('id' => $mcid));
            $coursename = $thiscourse->shortname;

            if ($voditem['mime_type'] == 'video/mp4'
                //AND mb_strpos(" ".$voditem['title']['rendered']." ", $keyword)
                ) {

                // Search $keyword in title. (if mode == searchintitle)
                if ($mode == 'title' &&
                    mb_strpos(" ".$voditem['title']['rendered']." ", $keyword) === false) continue;

                // Get video thumb link.
                // todo: combine this info into the original WS call. (save network traffic)
                $json_thimblink = $this->ws_get($voditem['_links']['wp:featuredmedia'][0]['href']);
                //$thumblink = str_replace('http://davidsonvod.weizmann.ac.il/wordpress/wp-content/uploads',
                //    'https://davidsonvod.weizmann.ac.il/vod', $json_thimblink['guid']['rendered']);
                $thumblink = str_replace('http://', 'https://', $json_thimblink['guid']['rendered']);
                // Use direct thumbnail link.
                //$thumblink = $json_thimblink['guid']['rendered'];

                $videolink = str_replace($this->wpresturl.'/wp-content/uploads',
                    $this->wpvodurl, $voditem['guid']['rendered']);

                $list[] = array(
                    //'shorttitle' => $voditem['title']['rendered'],
                    //'shorttitle' => $voditem['media_details']['source_url'],
                    'thumbnail_title' => $voditem['title']['rendered']. ' - '.$coursename,
                    //'thumbnail_title' => $voditem['media_details']['source_url'],
                    'title' => $voditem['title']['rendered']. ' - '.$coursename, // This is a hack so we accept this file by extension.
                    //'description' => $voditem['description'], // This is a hack so we accept this file by extension.
                    'thumbnail' => $thumblink,
                    'thumbnail_width' => 150, //(int)$thumb->width,
                    'thumbnail_height' => 150, //(int)$thumb->height,
                    'size' => $voditem['media_details']['filesize'],
                    'date' => strtotime(date_format(date_create($voditem['date']),"Y/m/d")),
                    'author' => 'Davidson',
                    //'source' => $voditem['media_details']['source_url'],
                    //'source' => $voditem['guid']['rendered'],
                    'source' => $videolink,
                );
            }
        }

        return $list;
    }

    private function ws_get($url) {

        $ch = curl_init(); //curl handler init

        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);// set optional params
        curl_setopt($ch,CURLOPT_HEADER, false);

        $result=curl_exec($ch);

        curl_close($ch);

        return json_decode($result, true);
    }

    /**
     * wpvod plugin doesn't support global search
     */
    public function global_search() {
        return false;
    }

    public function get_listing($path='', $page = '') {
        return array();
    }

    /**
     * Generate search form
     */
    public function print_login($ajax = true) {
        $ret = array();

        $search = new stdClass();
        $search->type = 'text';
        $search->id   = 'wpvod_search';
        $search->name = 's';
        $search->label = get_string('search', 'repository_wpvod').': ';

        $mode = new stdClass();
        $mode->type = 'select';
        $mode->options = array(
            (object)array(
                'value' => 'currentcourse',
                'label' => get_string('currentcourse', 'repository_wpvod')
            ),
            (object)array(
                'value' => 'title',
                'label' => get_string('searchintitle', 'repository_wpvod')
            ),
            (object)array(
                'value' => 'description',
                'label' => get_string('searchindescription', 'repository_wpvod')
            ),
            (object)array(
                'value' => 'tags',
                'label' => get_string('searchintags', 'repository_wpvod')
            )
        );
        $mode->id = 'wpvod_mode';
        $mode->name = 'wpvod_mode';
        $mode->label = get_string('mode', 'repository_wpvod').': ';

        $orderby = new stdClass();
        $orderby->type = 'select';
        $orderby->options = array(
            (object)array(
                'value' => 'relevance',
                'label' => get_string('orderbyrelevance', 'repository_wpvod')
            ),
            (object)array(
                'value' => 'date',
                'label' => get_string('orderbypublished', 'repository_wpvod')
            ),
            (object)array(
                'value' => 'title',
                'label' => get_string('orderbytitle', 'repository_wpvod')
            ),
            (object)array(
                'value' => 'rating',
                'label' => get_string('orderbyrating', 'repository_wpvod')
            ),
            (object)array(
                'value' => 'viewCount',
                'label' => get_string('orderbyviewcount', 'repository_wpvod')
            )
        );
        $orderby->id = 'wpvod_orderby';
        $orderby->name = 'wpvod_orderby';
        $orderby->label = get_string('orderby', 'repository_wpvod').': ';

        $ret['login'] = array($search, $mode, $orderby);
        $ret['login_btn_label'] = get_string('search');
        $ret['login_btn_action'] = 'search';
        $ret['allowcaching'] = true; // indicates that login form can be cached in filepicker.js
        return $ret;
    }

    /**
     * file types supported by wpvod plugin
     * @return array
     */
    public function supported_filetypes() {
        return array('video', 'video/mp4');
    }

    /**
     * wpvod plugin only return external links
     * @return int
     */
    public function supported_returntypes() {
        return FILE_EXTERNAL;
    }

    /**
     * Is this repository accessing private data?
     *
     * @return bool
     */
    public function contains_private_data() {
        return false;
    }

    /**
     * Add plugin settings input to Moodle form.
     * @param object $mform
     * @param string $classname
     */
    public static function type_config_form($mform, $classname = 'repository') {
        parent::type_config_form($mform, $classname);
        $apikey = get_config('wpvod', 'apikey');
        if (empty($apikey)) {
            $apikey = '';
        }

        // Internal URL, not open to the world
        // needs to be translated to a public URL,
        // specially setup to use KALTURA streamer NGINX plug settings
        //
        // NOTE: If you do not seperate public and internal networks,
        //       both "wpresturl" and "wpvodurl" should be the same URL
        $wpresturl = get_config('wpvod', 'wpresturl');
        if (empty($wpresturl)) {
            $wpresturl = 'http://davidsonvod.weizmann.ac.il:8080/wordpress';
        }
        // KALTURA streamer NGINX plug settings URL
        // https://gist.github.com/nadavkav/56f227572afd0c82d06425a79151886c#file-usr_local_nginx_conf_nginx-conf-L145
        $wpvodurl = get_config('wpvod', 'wpvodurl');
        if (empty($wpvodurl)) {
            $wpvodurl = 'https://davidsonvod.weizmann.ac.il/vod';
        }

        // TODO: use "apikey" to "authenticate" the connection between Moodle and WP-VOD (REST APIs)
        $mform->addElement('text', 'apikey', get_string('apikey', 'repository_wpvod'), array('value' => $apikey, 'size' => '40'));
        $mform->setType('apikey', PARAM_RAW_TRIMMED);
        $mform->addRule('apikey', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'wpresturl', get_string('wpresturl', 'repository_wpvod'), array('value' => $wpresturl, 'size' => '70'));
        $mform->setType('wpresturl', PARAM_RAW_TRIMMED);
        $mform->addRule('wpresturl', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'wpvodurl', get_string('wpvodurl', 'repository_wpvod'), array('value' => $wpvodurl, 'size' => '60'));
        $mform->setType('wpvodurl', PARAM_RAW_TRIMMED);
        $mform->addRule('wpvodurl', get_string('required'), 'required', null, 'client');

        $mform->addElement('static', null, '',  get_string('information', 'repository_wpvod'));
    }

    /**
     * Names of the plugin settings
     * @return array
     */
    public static function get_type_option_names() {
        return array('apikey', 'pluginname');
    }
}
