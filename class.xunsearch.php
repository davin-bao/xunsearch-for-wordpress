<?php
require_once( XUN_SEARCH_PLUGIN_DIR . '/xunsearch-sdk/lib/XS.php');


class XunSearch extends \XS {

    private $options;

    public function __construct()
    {
        if (!is_file(XUN_SEARCH_PLUGIN_DIR.'/xunsearch-sdk/app/post.ini'))
        {
            header('HTTP/1.1 403 Forbidden');
            exit('MISS xunsearch ini File');
        }

        add_action( 'init', array( $this, 'init' ) );
        return parent::__construct('post');
    }

    public function init() {

        //judge enable this plugin or not
        $options = maybe_unserialize(get_option('xunsearch_options'));
        if(isset($options['enable']) &&  $options['enable'] == '1'){
            //Get ID list when search hook
            add_action( 'posts_where_request', array( $this, 'posts_where_request' ), 10, 2 );
            //add index when publish post hook
            add_action( 'publish_post', array( $this, 'publish_post' ), 10, 2 );
            //add index when unstrash post hook
            add_action( 'untrash_post', array( $this, 'untrash_post' ), 10, 1 );
            //delete index when trash post hook
            add_action('trash_post', array($this, 'trash_post'), 10, 2);
        }

        //add option page
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action( 'admin_init', array( $this, 'admin_init' ) );
    }

    public function admin_init() {
        $this->page_init();
    }

    public function page_init(){
        register_setting(
            'xunsearch_option_group', // Option group
            'xunsearch_options', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            'XunSearch Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'xunsearch-options' // Page
        );

        add_settings_field(
            'enable', // ID
            'Enable', // Title
            array( $this, 'enable_callback' ), // Callback
            'xunsearch-options', // Page
            'setting_section_id' // Section
        );

        add_settings_field(
            'index_server', // ID
            'Index Server Address', // Title
            array( $this, 'index_server_callback' ), // Callback
            'xunsearch-options', // Page
            'setting_section_id' // Section
        );

        add_settings_field(
            'search_server',
            'Search Server Address',
            array( $this, 'search_server_callback' ),
            'xunsearch-options',
            'setting_section_id'
        );
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     * @return array
     */
    public function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['enable'] ) )
            $new_input['enable'] = sanitize_text_field( $input['enable'] );
        if( isset( $input['index_server'] ) )
            $new_input['index_server'] = sanitize_text_field( $input['index_server'] );
        if( isset( $input['search_server'] ) )
            $new_input['search_server'] = sanitize_text_field( $input['search_server'] );

        return $new_input;
    }

    public function admin_menu() {
        //register option page
        add_plugins_page('XunSearch Settings', 'XunSearch', 'manage_options', 'xunsearch-options', array($this, 'options_page'));
    }
    /**
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Enter your XunSearch settings:';
    }
    /**
     * Get the settings option array and print one of its values
     */
    public function enable_callback()
    {
        printf(
            '<input size="76" name="xunsearch_options[enable]" type="checkbox" id="enable" '. checked($this->options['enable'], true, false) . ' value="1" />'
        );
    }
    /**
     * Get the settings option array and print one of its values
     */
    public function index_server_callback()
    {
        printf(
            '<input type="text" id="index_server" name="xunsearch_options[index_server]" value="%s" />',
            isset( $this->options['index_server'] ) ? esc_attr( $this->options['index_server']) : ''
        );
    }
    /**
     * Get the settings option array and print one of its values
     */
    public function search_server_callback()
    {
        printf(
            '<input type="text" id="search_server" name="xunsearch_options[search_server]" value="%s" />',
            isset( $this->options['search_server'] ) ? esc_attr( $this->options['search_server']) : ''
        );
    }

    public function options_page() {
        $this->options = get_option( 'xunsearch_options' );
        ?>
        <div class="wrap">
            <h2>XunSearch Settings</h2>
            <form method="post" action="options.php">
         <?php
        settings_fields('xunsearch_option_group');
        do_settings_sections( 'xunsearch-options' );
        submit_button();
        ?>    </form>
        </div><?php
    }

    /**
     * Search posts hook
     * @param string $where
     * @param $entity
     * @return string
     */
    public function posts_where_request($where = '', &$entity){
        global $wpdb;

        try{
            if(is_admin()) return $where;

            $searchStr = $wpdb->prepare($_REQUEST['s'], null);

            $search = $this->getSearch();

            $docs = $search->search($searchStr);

            if(count($docs) <= 0) return $where;

            $postIdList = [];
            foreach($docs as $doc){
                array_push($postIdList, $doc->ID);
            }
            $postIdListStr = implode(',', $postIdList);
            return " AND {$wpdb->posts}.ID IN ($postIdListStr)";
        } catch(\Exception $e){
            header('HTTP/1.1 403 Forbidden');
            exit($e->getMessage());
        }
    }

    /**
     * Publish post hook
     * @param $ID
     * @param $post
     */
    public function publish_post($ID, $post){
        try{
            $search = $this->getSearch();
            $index = $this->getIndex();
            //has indexed
            if($search->setQuery('ID:"' . $ID . '"')->count() <= 0){
                $doc = new XSDocument;
                $doc->setFields(json_decode(json_encode($post), true));
                // add index
                $index->add($doc);
            }
        } catch(\Exception $e){
            header('HTTP/1.1 403 Forbidden');
            exit($e->getMessage());
        }
    }

    /**
     * Untrash post hook
     * @param $ID
     */
    public function untrash_post($ID) {
        if(get_post_meta($ID, '_wp_trash_meta_status', true) == 'publish'){
            $post = WP_Post::get_instance($ID);
            $this->publish_post($ID, $post);
        }
    }

    /**
     * trash post hook
     * @param $ID
     * @param $post
     */
    public function trash_post($ID, $post){
        try{
            $search = $this->getSearch();
            $index = $this->getIndex();
            //has indexed
            if($search->setQuery('ID:"' . $ID . '"')->count() > 0){
                // delete index
                $index->del($ID);
            }
        } catch(\Exception $e){
            header('HTTP/1.1 403 Forbidden');
            exit($e->getMessage());
        }
    }

    /**
     * get index server
     *
     * @return XSIndex
     */
    public function getIndex()
    {
        if ($this->_index === null) {
            $adds = array();
            $options = maybe_unserialize(get_option('xunsearch_options'));
            $conn = isset($options['index_server']) ? $options['index_server'] : 8383;

            if (($pos = strpos($conn, ';')) !== false) {
                $adds = explode(';', substr($conn, $pos + 1));
                $conn = substr($conn, 0, $pos);
            }
            $this->_index = new XSIndex($conn, $this);
            $this->_index->setTimeout(0);
            foreach ($adds as $conn) {
                $conn = trim($conn);
                if ($conn !== '') {
                    $this->_index->addServer($conn)->setTimeout(0);
                }
            }
        }
        return $this->_index;
    }

    /**
     * get search server
     *
     * @return null|XSSearch
     * @throws XSException
     */
    public function getSearch()
    {
        if ($this->_search === null) {
            $conns = array();
            $options = maybe_unserialize(get_option('xunsearch_options'));

            if (!isset($options['search_server'])) {
                $conns[] = 8384;
            } else {
                foreach (explode(';', $options['search_server']) as $conn) {
                    $conn = trim($conn);
                    if ($conn !== '') {
                        $conns[] = $conn;
                    }
                }
            }
            if (count($conns) > 1) {
                shuffle($conns);
            }
            for ($i = 0; $i < count($conns); $i++) {
                try {
                    $this->_search = new XSSearch($conns[$i], $this);
                    $this->_search->setCharset($this->getDefaultCharset());
                    return $this->_search;
                } catch (XSException $e) {
                    if (($i + 1) === count($conns)) {
                        throw $e;
                    }
                }
            }
        }
        return $this->_search;
    }
}