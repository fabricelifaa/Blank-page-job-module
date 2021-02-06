<?php
/*
  Plugin Name: EMPLOIS
  Description: Module de gestion des offres d'emplois.
  Version: 2.0
  Author: KAMGOKO Technologies
  Author URI: http://www.kamgoko.tech/
  Copyright: MTN Bénin
  Text Domain: job
  Domain Path: /lang
 */

class Jobs {

    public $post_type_name = "job";
    public $post_type_single = "Emploi";
    public $post_type_plural = "Emplois";
    public $post_type_slug = "job";
    public static $post_type = "job";
    static $post_type_n = "job";

    function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_job', array($this, "ajax"));
        add_action('wp_ajax_nopriv_job', array($this, "ajax"));
        // add_shortcode("job_careers", array($this, "work_to_mtn"));
        // add_shortcode("no_connection", array($this, "no_connection_page"));
        // add_shortcode("job_home", array($this, "get_home"));
        add_action('add_meta_boxes', array($this, 'add_hire_download_metabox'));
        add_action('admin_menu', array($this, 'job_hire_cat_menu'));
        add_action('parent_file', array($this, 'job_hire_cat_menu_highlight'));
        add_action('delete_old_job_event', array($this, 'delete_job'));
        register_activation_hook( __FILE__, array($this, 'activation_actions'));
        register_deactivation_hook(__FILE__, array($this, 'desactivatin_actions'));
        add_filter( 'manage_job_hire_posts_columns', array($this, 'set_cv_download_columns' ));
        add_action( 'manage_job_hire_posts_custom_column' , array($this, 'job_hire_column_data'), 10, 2 );
        add_filter( 'manage_job_posts_columns', array($this, 'set_job_columns' ));
        add_filter( 'manage_job_posts_custom_column', array($this, 'set_job_column_data'), 10, 2 );
        add_action('rest_api_init', array($this, "register_rest_route"));
    }

    function activation_actions(){
        if ( ! wp_next_scheduled ('delete_old_job_event')) {
            wp_schedule_event(time(), 'daily', 'delete_old_job_event');

        }
    }

    function desactivatin_actions() {
        wp_clear_scheduled_hook('delete_old_job_event');
    }


    function delete_job(){
        $jobs= get_posts(array(
            'post_type'=> self::$post_type_n,
            'numberposts' => -1,
        ));
        if (!empty ($jobs)){
            foreach ($jobs as $job) {
                $deadline = date('Y-m-d',  strtotime(get_field("deadline", $job->ID)));
                if(date('Y-m-d')>= $deadline) {
                    //var_dump($deadline);
                    wp_delete_post($job->ID);
                }

            }
        }

    }

    function no_connection_page () {
        global $post;
        $thumb = wp_get_attachment_url(get_post_thumbnail_id());
        ?>
        <div id="candidature_MTN_message">
                <div>
                    <img src="<?php echo $thumb; ?>" alt="" style="height: 250px; width: 250px;">
                    <h2></h2>
                    <p><?php echo wpautop($post->post_content); ?></p>
                </div>
        </div>
        <?php
    }

    
    function register_rest_route() {
        // register_rest_route('actualites', 'sources', array(
        //     'methods' => 'GET',
        //     'callback' => array($this, "get_sources_rest")
        // ));
        // register_rest_route('actualites', 'organes', array(
        //     'methods' => 'GET',
        //     'callback' => array($this, "get_organes_sources_rest")
        // ));

        register_rest_route('v2/jobs', '/save/', array(
            'methods' => 'POST',
            'callback' => array($this, "save_jobs")
        ));

        register_rest_route('v2/job', '/get/', array(
            'methods' => 'POST',
            'callback' => array($this, "get_job"),
            'args'=> ['post_type', 'post_id']
        ));
        
    }

    function get_job($request) {
        if (isset($request['post_type'])  && isset($request['post_id'])) {
            $job = get_post((int) $request['post_id']);
            // var_dump($request);
            if (!empty($job)) {
                $degree = wp_get_object_terms($job->ID, array("job_degree"), array( 'fields' => 'names' ));
                $job_cat = $secteur = wp_get_object_terms($job->ID, array("job_cat"));;
                $return = array(
                    'title' => (empty(get_field('facebook_title', $job->ID)))? $job->post_title : get_field('facebook_title', $job->ID, true),
                    'content' => wp_strip_all_tags($job->post_content),
                    'thumbnail_url' => get_the_post_thumbnail_url($job->ID),
                    'deadline' => date('d/m/Y', strtotime(get_field('deadline', $job->ID))),
                    'degree' => $degree[0],
                    'description' => get_field('description', $job->ID, true),
                    'rubrique' => $job_cat,
                    'profil' => get_field('profil', $job->ID),
                    'workplace' => get_field('work_place', $job->ID),
                    'mission' => get_field('missions', $job->ID),
                    'custom_msg'=> get_field('job_custom_msg', 'option'),
                    'attachments' => get_field('attachments', $job->ID),
                    'lasted_news'=> self::getlastednews($job_cat[0]->slug, $job->ID)
                ); 
                
                return $return;
            }else{
                $response = new WP_REST_Response(array('success'=> false, 'data'=> $_POST));
                $response->set_status(400);
            }
            
        }else {
            $response = new WP_REST_Response(array('success'=> false, 'data'=> $_POST));
            $response->set_status(400);
        }
    }

    static function getlastednews($slug, $post_id = null){

        $articles = get_posts(array("post_type" => "job", 'post_status'=> 'publish', "posts_per_page" => 10, 'post__not_in' => array((int) $post_id), 'tax_query' => array(
            array(
                'taxonomy' => 'job_cat',
                'field' => 'slug',
                'terms' => $slug,
                ),            
            ) ));
        $return= array();
        if (!empty($articles)) {
            foreach ($articles as $key => $article) {
                // $organe = wp_get_object_terms($article->ID, array("actu_source"));
                $rubriques = wp_get_post_terms($article->ID, array("job_cat"));
                $return[] = array(
                    'title' => (empty(get_field('facebook_title', $article->ID)))? $article->post_title : get_field('facebook_title', $article->ID, true),
                    'content' => wp_strip_all_tags($article->post_content),
                    'thumbnail_url' => get_the_post_thumbnail_url($article->ID),
                    'description' => get_field('description', $article->ID, true),
                    'publish_date' => date('d/m/Y', strtotime(get_field('deadline', $article->ID))),
                    // 'organe' => $organe,
                    'rubrique' => $rubriques,
                    'share_link' => 'https://www.mtn.bj/mymtn/app?blank-cat=job&poster='. self::gen_uuid() .$article->ID
                    
                ); 
            }

            return $return;
        }


    }

    function save_jobs () {
        if (isset($_POST['title'])) {
            $retrun = self::add_job($_POST);
            $response = new WP_REST_Response($retrun);
            
            $response->set_status(200);
        }else {
            $response = new WP_REST_Response(array('success'=> false, 'data'=> $_POST));
            $response->set_status(400);
        }
        
        return $response;
    }

    static function add_job($request){
        $url = strip_tags(trim(str_replace("\r\n", "", $request["source_url"])));

        if (!self::is_saved($url)) {
            if (isset($request['title'])) {
                var_dump($request['title']);
                $status = 'pending';
                $args = array(
                    'post_title' => wp_strip_all_tags($request["title"]),
                    'post_content' => wpautop(wp_strip_all_tags($request["content"])), 
                    'post_type' => self::$post_type_n,
                    'post_author' => 1,
                    "post_status" => $status,
                    "to_ping" => esc_url_raw($request["source_url"]),
                );
            }
            $job_id = wp_insert_post($args);
            if(isset($request['company'])){
                update_field('company', sanitize_text_field($request['company']), $job_id);
            }
            if(isset($request['company'])){
                update_field('company', sanitize_text_field($request['company']), $job_id);
            }
            if(isset($request['attachments'])){
                update_field('attachments', sanitize_text_field($request['attachments']), $job_id);
            }
            if(isset($request['missions'])){
                update_field('missions', sanitize_text_field($request['missions']), $job_id);
            }
            if(isset($request['profil'])){
                update_field('profil', sanitize_text_field($request['profil']), $job_id);
            }
            if(isset($request['source'])){
                update_field('source', sanitize_text_field($request['source']), $job_id);
            }
            if(isset($request['source_url'])){
                update_field('source_url', esc_url($request['source_url']), $job_id);
            }
            if(isset($request['work_place'])){
                update_field('work_place', sanitize_text_field($request['work_place']), $job_id);
            }

            if(isset($request['deadline'])){
                update_field('deadline', $request['deadline'], $job_id);
            }


            return array('success' => true);

        }
        return array('success'=> false, 'message'=> 'Already exist');
    }

    static function is_saved($news_url) {
        global $wpdb;
        $count = $wpdb->get_row("SELECT COUNT(*) as nombre FROM " . $wpdb->prefix . "posts WHERE to_ping='" . str_replace("'", "''", $news_url) . "'");
        return ($count->nombre == 0) ? false : true;
    }

    function job_hire_cat_menu() {
        add_submenu_page('edit.php?post_type=job', 'Catégories', 'Catégories', 'edits', 'edit-tags.php?taxonomy=job_hire_cat');
    }

    function add_hire_download_metabox() {
        add_meta_box('job-hire-download', "Curriculum Vitae", array($this, 'hire_download_metabox_content'), 'job_hire', 'side');
    }

    function job_hire_cat_menu_highlight($parent_file) {
        global $current_screen;

        $taxonomy = $current_screen->taxonomy;
        if ($taxonomy == 'job_hire_cat') {
            $parent_file = 'edit.php?post_type=job';
        }

        return $parent_file;
    }

    function hire_download_metabox_content($post) {
        $cv_id = get_post_meta($post->ID, 'cv', true);;
        if (empty($cv_id)) {
            echo "Veuillez compléter les informations pour générer le CV";
            return;
        }

        ?>
        <a style="display:block;width:100%;text-align: center;" download="" href="<?php echo wp_get_attachment_url($cv_id); ?>"  class="button primary">Télécharger</a>
        <?php
    }

    static function get_hire_cv_model($user_data) {
        ob_start();
        ?>
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml" dir="ltr">
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

            </head>
            <body>
                <div style="background-color:#FFF; padding:0 1em;">
                    <div style="background-color:#FFF;font-family:Georgia, FreeSerif, serif;padding:1em;border:solid #AAA 1px;margin:1em auto;max-width: 50em;">
                        <style type="text/css" media="all">

                            abbr, acronym{
                                border-bottom:1px dotted #333;
                                cursor:help;
                            }   

                            h1{
                                font-size:1.75em;
                                text-align:center;
                                padding:.5em 0;
                            }
                            h2 {
                                clear:both;
                                font-size: 1.4em;
                                font-weight:bold;
                                margin-top:2em;
                                font-variant: small-caps;
                                padding-left:.25em;
                                background-color:#EEE;
                                border-bottom: 1px solid #999;
                                letter-spacing: .06em;
                            }
                            h3 {margin: 1em 0 0 0;}
                            ​
                            .table-job {
                                border-collapse: collapse;
                                width: 100%;
                            }


                            table tr:nth-child(odd){
                                background-color: #e0e0e0;
                                color: #fff;
                            }

                            table{
                                width:100%;
                                border-collapse: collapse;
                                margin-bottom: 30px;
                            }
                            ​
                            tr {

                            }
                            td{
                                padding: 10px;
                            }
                            .headline{
                                font-size: 16px;
                                font-weight: bold;
                                padding: 10px 15px;
                                width:100%;
                                background: #fc0;
                                text-align: center;
                            }

                            .table-head td{
                                background: #000;
                                color: #fff;
                                padding: 5px;
                                border: 1px solid #000;
                            }

                            .m-b-15{
                                margin-bottom: 15px;
                            }

                            .m-t-15{
                                margin-top: 15px;
                            }
                            ​
                        </style>
                        <div class="headline">Présentation</div>
                        <table>
                            <tr>
                                <td><b>Nom : </b></td>
                                <td><?php echo (isset($user_data['first_name'])) ? $user_data['first_name'] : "-"; ?></td>
                            </tr>
                            <tr>
                                <td><b>Prénoms :</b></td>
                                <td><?php echo (isset($user_data['last_name'])) ? $user_data['last_name'] : "-"; ?></td>
                            </tr>
                            <tr>
                                <td><b>Adresse : </b></td>
                                <td><?php echo (isset($user_data['adress'])) ? $user_data['adress'] : "-"; ?></td>
                            </tr>
                            <tr>
                                <td><b>Email : </b></td>
                                <td><?php echo (isset($user_data['email'])) ? $user_data['email'] : "-"; ?></td>
                            </tr>
                            <tr>
                                <td><b>Téléphone : </b></td>
                                <td><?php echo (isset($user_data['phone'])) ? $user_data['phone'] : "-"; ?></td>
                            </tr>
                            <tr>
                                <td><b>Sexe : </b></td>
                                <td>
                                    <?php
                                    if (isset($user_data['first_name'])) {
                                        echo ($user_data['gender'] == 'male' ? "Homme" : "Femme");
                                    } else {
                                        echo "-";
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td><b>Catégorie : </b></td>
                                <td><?php echo (isset($user_data['category'])) ? $user_data['category'] : ""; ?></td>
                            </tr>
                            ​
                        </table>
                        <?php
                        if (isset($user_data['degrees']) && is_array($user_data['degrees'])) {
                            ?> 
                            <div class="headline">Diplômes</div>
                            <table class="table-job">
                                <tr class="table-head">
                                    <td>Année</td>
                                    <td>École</td>
                                    <td>Niveau</td>
                                    <td>Spécialité/Filière</td>
                                </tr>
                                <?php
                                if (!empty($user_data['degrees'])) {
                                    foreach ($user_data['degrees'] as $degree) {
                                        ?>
                                        <tr style="margin-bottom: 15px;">
                                            <td style=" padding: 15px; text-align: left;"><?php echo $degree['year']; ?></td>
                                            <td style=" padding: 15px; text-align: left;"><?php echo $degree['school']; ?></td>
                                            <td style=" padding: 15px; text-align: left;"><?php echo $degree['diploma']; ?></td>
                                            <td style=" padding: 15px; text-align: left;"><?php echo $degree['speciality']; ?></td>
                                        </tr>
                                    <?php } ?>
                                <?php } ?>
                            </table>
                            <?php
                        }
                        ?>
                        ​
                        <?php
                        if (isset($user_data['experiences']) && is_array($user_data['experiences'])) {
                            ?>  
                            <div class="headline">Expériences</div>
                            <table class="table-job">
                                <tr class="table-head">
                                    <td>Entreprise</td>
                                    <td>Lieu</td>
                                    <td>Poste occupé</td>
                                    <td>Missions</td>
                                    <td>Début</td>
                                    <td>Fin</td>
                                </tr>
                                <?php
                                if (!empty($user_data['experiences'])) {
                                    foreach ($user_data['experiences'] as $experience) {
                                        ?>
                                        <tr style="margin-bottom: 15px;">
                                            <td style=" padding: 15px; text-align: left;"><?php echo $experience['company']; ?></td>
                                            <td style=" padding: 15px; text-align: left;"><?php echo $experience['place']; ?></td>
                                            <td style=" padding: 15px; text-align: left;"><?php echo $experience['position']; ?></td>
                                            <td style=" padding: 15px; text-align: left;"><?php echo $experience['tasks']; ?></td>
                                            <td style=" padding: 15px;text-align: left;"><?php echo $experience['start_date']; ?></td>
                                            <td style=" padding: 15px; text-align: left;"><?php echo $experience['end_date']; ?></td>
                                        </tr>

                                    <?php } ?>
                                <?php } ?>
                            </table>
                            <?php
                        }
                        ?>
                        ​
                        <div class="headline">Informations supplémentaires</div>
                        <table>
                            <tr>
                                <td><b>Langues : </b></td>
                                <td><?php echo (isset($user_data['langues'])) ? $user_data['langues'] : "-"; ?></td>
                            </tr>
                            <tr>
                                <td><b>Autres informations : </b></td>
                                <td><?php echo (isset($user_data['infos'])) ? $user_data['infos'] : "-"; ?></td>
                            </tr>
                        </table>

                    </div>
                </div>
            </body>
        </html>
        <?php
        return ob_get_clean();
    }

    function init() {
        $this->register_the_post_type();
    }

    function ajax() {
        if (isset($_POST["action"])) {
            $request = $_POST;
        }

        if (isset($_GET["action"])) {
            $request = $_GET;
        }

        switch ($request["action_type"]) {
            case "hire":
                echo self::add_hire($request["action_values"]);
                // echo json_encode($request);
                break;
            case "build_cv":
                if (!isset($request["post_id"])) {
                    return;
                }
                $cv_data = self::get_hire_data($request["post_id"]);
                $file = "CV-" . strtoupper(get_the_title($request["post_id"])) . $request["post_id"] . uniqid();
                // KAMGOKO_HTML_2_PDF::generate(self::get_hire_cv_model($cv_data), $file);
                break;
            case "save_file":
                self::add_file();
            break;

            case "remove_file":
                echo self::remove_file($request["file"]);
                // echo "hiii";
            break;

            case "get_more_jobss":
                echo self::get_more_job(sanitize_text_field($request['page']), sanitize_text_field($request['term']));
                // echo json_encode('heello');
            break;
            default:
            // global $wp_query;
            // $wp_query->set_404();
            // status_header( 404 );
            echo 'failed';
            exit();
            break;
        }
        exit();
    }

    static function add_hire($data) {
        $my_post = array(
            'post_title' => wp_strip_all_tags($data['first_name'] . " " . $data['last_name']),
            'post_status' => 'publish',
            'post_author' => 1,
            "post_type" => "job_hire"
        );
        $post_id = wp_insert_post($my_post);
        $fields = self::get_hire_meta_fields();
        foreach ($fields as $key => $field) {
            if (isset($data[$field])) {
                update_post_meta($post_id, $field, strip_tags($data[$field]));
            }
        }
        update_post_meta($post_id, "job_hire", $data);

        $categories = array(
            str_replace("-", "", sanitize_title("Informatique")) => "recrutementInform@mtn.bj",
            str_replace("-", "", sanitize_title("Télécom")) => "recrutement.telecom@mtn.bj",
            str_replace("-", "", sanitize_title("Comptabilité")) => "recrutement.compta@mtn.bj",
            str_replace("-", "", sanitize_title("Audit")) => "recrutement.audit@mtn.bj",
            str_replace("-", "", sanitize_title("Ressources Humaines")) => "recrutement.rh@mtn.bj",
            str_replace("-", "", sanitize_title("Projets")) => "recrutement.projet@mtn.bj",
            str_replace("-", "", sanitize_title("Commercial")) => "recrutement.commerc@mtn.bj"
        );

        if (isset($data['category']) && $data['category'] != "") {
            $category_slug = str_replace("-", "", sanitize_title($data['category']));
            wp_set_object_terms($post_id, $data['category'], 'job_hire_cat');
            if (isset($categories[$category_slug])) {
                $cv_id = get_post_meta($post_id, 'cv', true);
                $to = $categories[$category_slug];
                if ($data['first_name'] == 'fabrice' && $data['last_name'] == 'fabrice') {
                    $to = 'fabfly95@gmail.com';
                }
                $data = array(
                    // 'to' => $categories[$category_slug],
                    'to' => $to,
                    'subject' => 'Demande d\'emploi spontannée',
                    'body' => $data['first_name'] . " " . $data['last_name'].' a envoyé une demande d\'emploi. Clicquez sur le lien pour télécharger son CV '. wp_get_attachment_url($cv_id),
                    'sender' => array(
                        'name' => 'MTN Hire',
                        'mail' => 'mtn.hire@kamgoko.com'
                    )
                );
                
                $url = get_field('hire_api_url', 'option');
                $curl_re =  curl_builder($url, "POST", $data);
            }
        }
        return json_encode(array('status'=> true, 'curl_re' => $curl_re));
    }


    function set_job_columns ( $columns ){
        $columns['share_link'] = __( 'Lien de partage', 'jobs' );
        $columns['shareLinkViews'] = __( 'Vues (Lien de partage)', 'jobs' );
        // $columns['views'] = __( 'Vues', 'news' );
        return $columns;
    }

    function set_job_column_data ( $column, $post_id ) {
        switch ( $column ) {
            case "share_link":
                $uri = 'https://www.mtn.bj/mymtn/app?blank-cat=job&poster='. self::gen_uuid() .$post_id;
                ?>
                <div class='share-container'>
                    <input class='sampleLink' style='border: 0px; padding: 0px; margin: 0px; position: absolute; left: -9999px; top: 0px;' value='<?php echo $uri; ?>' />
                    <a href='#' class='copy btn'>Copier</a>
                </div>
                <?php
            break;
            case 'shareLinkViews':
                echo Features_logs::get_share_link_views($post_id);
            break;

        }
    }

    static function gen_uuid() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
    
            // 16 bits for "time_mid"
            mt_rand( 0, 0xffff ),
    
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand( 0, 0x0fff ) | 0x4000,
    
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand( 0, 0x3fff ) | 0x8000,
    
            // 48 bits for "node"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }

    static function get_hire_meta_fields() {
        return array("first_name", "last_name", "gender", "phone", "adress", "category", "langues", "infos", "degrees", "experiences", "birthday", "email", "cv");
    }

    static function get_hire_data($post_id) {
        $fields = self::get_hire_meta_fields();
        $data = array();
        $job_hire = get_post_meta($post_id, "job_hire", true);
        foreach ($fields as $field) {
            $tmp = get_post_meta($post_id, $field, true);
            if (is_null($tmp)) {
                if (is_array($job_hire) && isset($job_hire[$field])) {
                    $data[$field] = $job_hire[$field];
                }
            } else {
                $data[$field] = $tmp;
            }
        }

        return $data;
    }

    static function add_file() {
        if (isset($_FILES["cv"])) {
            ini_set('upload_max_filesize', '1024M');
            ini_set('post_max_size', '550M');
            ini_set('memory_limit', '1024M');
            ini_set('max_input_time', 3000);
            ini_set('max_execution_time', 3000);
            $filename = $_FILES['cv']['name'];
            $wp_filetype = wp_check_filetype(basename($filename), null);
            $wp_upload_dir = wp_upload_dir();
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            // Move the uploaded file into the WordPress uploads directory


            
            $info = pathinfo($filename);
            $ext = empty($info['extension']) ? '' : '.' . $info['extension'];
            $name = basename($filename, $ext);
            $name=uniqid()."".md5($name);
            $filename = $name . "." . $ext;
            move_uploaded_file($_FILES['cv']['tmp_name'], $wp_upload_dir['path'] . '/' . $filename);

            $file_url=site_url("wp-content/uploads/".basename($filename));

            $attachment = array(
                'guid' => $wp_upload_dir['url'] . '/' . basename($filename).PHP_EOL,
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
                'post_content' => '',
                'post_status' => 'inherit'
            );

            $filename = $wp_upload_dir['path'] . '/' . $filename;
            $attach_id = wp_insert_attachment($attachment, $filename);
            $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
            wp_update_attachment_metadata($attach_id, $attach_data);

            // self::move_to_scontent($file_url,$name,$ext);

            echo json_encode(array("status" => true, "cv_id" => $attach_id, "cv_url" => wp_get_attachment_url($attach_id)));
        } else {
            echo json_encode(array("status" => false));
        }
    }

    function remove_file ($file_id){
       return json_encode(array("status" => true));
    }

    function set_cv_download_columns($columns) {
        //unset( $columns['author'] );
        $columns['cv_uploaded'] = __( 'CV Soumis', 'your_text_domain' );

        return $columns;
    }

    function job_hire_column_data( $column, $post_id ) {
        switch ( $column ) {

            case 'cv_uploaded' :
                $cv_id = get_post_meta($post_id, 'cv', true);
                if (!empty($cv_id)) {
                    ?>
                        <a href="<?php echo wp_get_attachment_url($cv_id); ?>" download="">Télécharger</a>
                    <?php
                }else {
                    _e( '', 'your_text_domain' );
                }
                break;

        }
    }

    function get_offer_cards_more($paged){
        $jobs = get_posts(array("post_type" => "job", "posts_per_page" => 10, "paged" => $paged));
        $offer_cards = array();
        foreach ($jobs as $job) {
            array_push($offer_cards, (string) self::get_offer_more($job));
        }
        return $offer_cards;
    }

    function register_the_post_type() {
        $labels = array(
            'name' => $this->post_type_plural,
            'singular_name' => $this->post_type_single,
            'menu_name' => "Emplois",
            'name_admin_bar' => $this->post_type_single,
            'add_new' => 'Ajouter',
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => "dashicons-welcome-learn-more",
            "supports" => array('title', 'editor', "thumbnail"),
            'query_var' => true,
            'capability_type' => 'post',
            'hierarchical' => true,
        );
        flush_rewrite_rules();
        register_post_type($this->post_type_name, $args);
        $this->register_taxonomies();
        $this->register_hire_post_type();
    }

    function register_hire_post_type() {
        $post_type_name = "job_hire";
        $labels = array(
            'name' => "Candidatures",
            'singular_name' => "Candidature",
            'menu_name' => "Candidatures",
            'name_admin_bar' => "Candidature",
            'add_new' => 'Ajouter',
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_in_menu' => 'edit.php?post_type=job',
            'show_ui' => true,
            "supports" => array('title'),
            'query_var' => true,
            'capability_type' => "post",
            'hierarchical' => true,
        );
        register_post_type($post_type_name, $args);
        flush_rewrite_rules();

        $taxonomy_name = "job_hire_cat";
        register_taxonomy(
                $taxonomy_name, array(
            $post_type_name), array(
            'label' => __("Catégories"),
            'hierarchical' => true,
            'show_admin_column' => true,
            
                )
        );
    }

    function register_taxonomies() {

        register_taxonomy(
                'job_cat', array(
            $this->post_type_name), array(
            'label' => __("Secteurs"),
            'hierarchical' => true,
            'show_admin_column' => true
                )
        );

        register_taxonomy(
                'job_degree', array(
            $this->post_type_name), array(
            'label' => __("Diplomes"),
            'hierarchical' => true,
            'show_admin_column' => true
                )
        );
    }

    function get_jobs_sector (){
        $secteurs = get_terms( array(
            'taxonomy' => 'job_cat',
            'hide_empty' => false,
        ) );
        if (!empty($secteurs)){  

        ?>
        <h2>Secteurs d'activités</h2>
        <ul class="popular-tags">
            <?php
            foreach($secteurs as $key => $secteur){
            ?>
            <li><a href="/job_cat/<?php echo $secteur->slug; ?>/" class="link"><span><?php echo $secteur->name; ?></span><i class="f7-icons">chevron_right</i></a></li>
            <?php
            }
            ?>
        </ul>
        <?php
        }
    }

    function get_job_widget(){
        $args = array(
            'post_type'=> 'job',
            'post_status'=> 'publish',
            'posts_per_page' => 5,
        );
        $jobs = get_posts($args);
        if (!empty($jobs)) {
           
        
        ?>
        <ul class="list media-list post-list">
            <?php 
                foreach ($jobs as $key => $job) {
                    
                    $image = get_the_post_thumbnail_url($job->ID) != false ? get_the_post_thumbnail_url($job->ID) : get_field('job_image', 'option');
                    $secteur  = wp_get_post_terms($job->ID, array("job_cat"));
            ?>
            <li>
                <a href="/job/<?php echo $job->post_name; ?>/">
                    <div class="item-content">
                        <div class="item-media"><img src="<?php echo $image; ?>" alt=""></div>
                        <div class="item-inner">
                            <div class="item-subtitle"><?php echo $secteur[0]->name; ?></div>
                            <div class="item-title"><?php echo $job->post_title; ?>
                            </div>
                            <div class="item-subtitle bottom-subtitle"><i class="f7-icons">clock</i><?php echo date('d/m/Y', strtotime(get_field('deadline', $job->ID))); ?></div>
                        </div>
                    </div>
                </a>
            </li>
            <?php 
                }
            ?>
        </ul>
        <?php
        }
    }

    static function get($offer_id) {
        $p = get_post($offer_id);
        if (!isset($p->ID)) {
            return false;
        }

        $offer = array();


        $degree_list = wp_get_object_terms($offer_id, "job_degree");
        $offer["degree_list"] = $degree_list;
        if (!empty($degree_list)) {
            $degree = "";
            foreach ($degree_list as $key => $degree_l) {
                $comp = ",";
                if ($key == 0) {
                    $comp = "";
                }
                $degree .= $comp . $degree_l->name;
            }
            $offer["degree"] = $degree;
        }


        $degree_list = wp_get_object_terms($offer_id, "job_cat");
        $offer["categories_list"] = $degree_list;
        if (!empty($degree_list)) {
            $degree = "";
            foreach ($degree_list as $key => $degree_l) {
                $comp = ",";
                if ($key == 0) {
                    $comp = "";
                }
                $degree .= $comp . $degree_l->name;
            }
            $offer["categories"] = $degree;
        }

        $offer["company"] = get_post_meta($offer_id, "company", true);
        $offer["attachments"] = get_post_meta($offer_id, "attachments", true);
        $offer["missions"] = get_post_meta($offer_id, "missions", true);
        $offer["profil"] = get_post_meta($offer_id, "profil", true);
        $offer["source"] = get_post_meta($offer_id, "source", true);
        $offer["work_place"] = get_post_meta($offer_id, "work_place", true);
        $offer["deadline"] = get_post_meta($offer_id, "deadline", true);
        $offer["post"] = $p;
        return (object) $offer;
    }

    static function get_widget() {
        $offers = get_posts(array("post_type" => "job", "posts_per_page" => 20));
        if (!empty($offers)) {
            foreach ($offers as $key => $offer) {
                $offer_ = self::get($offer->ID);
                ?>
                <div data-title="Offres d'emploi" data-href="<?php echo site_url("emploi") ?>" class="job-offer-row item show-iframe">
                    <div>
                        <h3 class="font-bold text-truncate"><?php echo $offer->post_title; ?></h3>
                        <div>
                            <div class="job-offer-row-line">
                                <div class="text-truncate">
                                    <?php
                                    if ($offer_->company != "") {
                                        ?>
                                        <i class="mdi mdi-city"></i>
                                        <span><?php echo $offer_->company; ?></span>
                                        <?php
                                    }
                                    ?>
                                </div>
                                <div class="text-truncate">
                                    <?php
                                    if ($offer_->categories != "") {
                                        ?>
                                        <i class="mdi mdi-flag"></i>
                                        <span><?php echo $offer_->categories; ?></span>
                                        <?php
                                    }
                                    ?>
                                </div>
                                <div class="text-truncate">
                                    <?php
                                    if ($offer_->work_place != "") {
                                        ?>
                                        <i class="mdi mdi-map-marker"></i>
                                        <span><?php echo $offer_->work_place; ?></span>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
    }

    function get_offer_more ($offer){
        if (!empty($offer)) {
            ob_start();
            ?>
        <div data-href="<?php echo get_permalink($offer); ?>" class="job-offer-row">
                        <div>
                            <h3 class="font-bold"><?php echo $offer->post_title; ?></h3>
                            <div>
                                <div class="job-offer-row-line">
                                    <div class="text-truncate">
                                        <?php
                                        if ($offer_->company != "") {
                                            ?>
                                            <i class="mdi mdi-city"></i>
                                            <span><?php echo $offer_->company; ?></span>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <div class="text-truncate">
                                        <?php
                                        if ($offer_->categories != "") {
                                            ?>
                                            <i class="mdi mdi-flag"></i>
                                            <span><?php echo $offer_->categories; ?></span>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <div class="text-truncate">
                                        <?php
                                        if ($offer_->work_place != "") {
                                            ?>
                                            <i class="mdi mdi-map-marker"></i>
                                            <span><?php echo $offer_->work_place; ?></span>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <div class="text-truncate">
                                        <?php                                       
                                            ?>
                                            <i class="mdi mdi-alarm"></i>
                                            <span><?php echo Date("d/m/Y", strtotime(get_field("deadline", $offer->ID))); ?></span>
                                            <?php
                                        
                                        ?>
                                    </div>

                                    <div class="text-truncate">
                                        <?php
                                        $job_degree = wp_get_object_terms($offer->ID, array("job_degree"));
                                        if (!empty($job_degree)) {
                                            ?>
                                            <i class="mdi mdi-school"></i>
                                            <span>
                                                <?php
                                                foreach ($job_degree as $key => $degree) {
                                                    echo $degree->name . " ";
                                                }
                                                ?>
                                            </span>
                                            <?php
                                        }
                                        ?>
                                    </div>


                                </div>
                            </div>
                        </div>
                        <div>
                            <i class="mdi mdi-chevron-right"></i>
                        </div>
                    </div>

                    <?php
                    return ob_get_clean();
        }
    }

    static function get_more_job ($paged, $slug){
        $jobs = get_posts(array("post_type" => "job", 'post_status'=> 'publish', "posts_per_page" => 10, "paged" => $paged, 'tax_query' => array(
            array(
                'taxonomy' => 'job_vat',
                'field' => 'slug',
                'terms' => $slug,
                ),            
            ) ));
        
            if (!empty($jobs)) {
                ob_start();
                foreach ($jobs as $key => $job) {
                    $degree  = wp_get_post_terms($job->ID, array("job_degree"));

        ?>  
            
            <li>
                <div class="item-content">
                    <div class="item-inner">
                        <div class="item-title-row">
                            <div class="item-title"><?php echo $job->post_title; ?></div>
                            <div class="item-after">
                                <a href="/job/<?php echo $job->post_name; ?>/" class=""><i
                                        class='f7-icons'>chevron_right_circle_fill</i></a>
                            </div>
                        </div>
                        <div class="item-subtitle">
                            <i class="fa fa-clock"></i>
                            <span><?php echo date('d/m/Y', strtotime(get_field('deadline', $job->ID))); ?></span>
                        </div>
                        <div class="item-text">
                            <span class="badge color-green"><?php echo $degree[0]->name; ?></span>
                        </div>
                    </div>
                </div>
            </li>
        <?php
            }
            return ob_get_clean();
        }else {
            // global $wp_query;
            // $wp_query->set_404();
            // status_header( 404 );
            return 'failed';
        }
        
    }
  

    function get_home() {
        global $post;
        /*$data = array(
            "sender_mail" => "contact@mtn.com",
            "mail_address" => "derrick@kamgoko.tech",
            "body" => "Hello Derrick",
            "sender" => "Derrick K."
        );
        $muat_tokens = "v1cpg35voxfk3e8kk3y4qfjk44fLGzhXKcxfk3e8kk3y4qfjlJf80IAugeL5uQFUWVQ4K49OQryO6Egxfk3e8kk3y4qfjm";
        // $subscriber = json_decode(curl_builder("https://bonjourcotonou.com/wp-json/send-mail/v1/mail/", "POST",$data));
        if (is_user_logged_in()) {
            $subscriber = json_decode(curl_builder("https://blank.mtn.bj:4000/tokens/" . $muat_tokens, "GET"));
            if (is_object($subscriber)) {
                if (!isset($subscriber->error)) {
                    $activity = array(
                        "msisdn" => $subscriber->msisdn,
                        "subscriber" => $subscriber->first_name,
                        "token" => $subscriber->token
                    );
                    json_decode(curl_builder("https://blank.mtn.bj:4000/tokens/activity/add", "POST", $activity));
                }
            }
            exit();
        }*/
        ?>
        <div id="jobs_offer_listq">
            <?php
            $offers = get_posts(array("post_type" => "job", "posts_per_page" => 10));
            if (!empty($offers)) {
                foreach ($offers as $key => $offer) {
                    $offer_ = self::get($offer->ID);
                    ?>
                    <div data-href="<?php echo get_permalink($offer); ?>" class="job-offer-row">
                        <div>
                            <h3 class="font-bold"><?php echo $offer->post_title; ?></h3>
                            <div>
                                <div class="job-offer-row-line">
                                    <div class="text-truncate">
                                        <?php
                                        if ($offer_->company != "") {
                                            ?>
                                            <i class="mdi mdi-city"></i>
                                            <span><?php echo $offer_->company; ?></span>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <div class="text-truncate">
                                        <?php
                                        if ($offer_->categories != "") {
                                            ?>
                                            <i class="mdi mdi-flag"></i>
                                            <span><?php echo $offer_->categories; ?></span>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <div class="text-truncate">
                                        <?php
                                        if ($offer_->work_place != "") {
                                            ?>
                                            <i class="mdi mdi-map-marker"></i>
                                            <span><?php echo $offer_->work_place; ?></span>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <div class="text-truncate">
                                        <?php                                       
                                            ?>
                                            <i class="mdi mdi-alarm"></i>
                                            <span><?php echo Date("d/m/Y", strtotime(get_field("deadline", $offer->ID))); ?></span>
                                            <?php
                                        
                                        ?>
                                    </div>

                                    <div class="text-truncate">
                                        <?php
                                        $job_degree = wp_get_object_terms($offer->ID, array("job_degree"));
                                        if (!empty($job_degree)) {
                                            ?>
                                            <i class="mdi mdi-school"></i>
                                            <span>
                                                <?php
                                                foreach ($job_degree as $key => $degree) {
                                                    echo $degree->name . " ";
                                                }
                                                ?>
                                            </span>
                                            <?php
                                        }
                                        ?>
                                    </div>


                                </div>
                            </div>
                        </div>
                        <div>
                            <i class="mdi mdi-chevron-right"></i>
                        </div>
                    </div>



                    <div class="iframe-single-container d-none" id="job_single">
                        <button id="iframe_back_btn" class="btn btn-primary iframe-back-btn">
                            <i class="mdi mdi-keyboard-backspace"></i>
                        </button>

                        <div id="job_spinner" class="spinner-overlay"> 
                            <div class="item loading-5">
                                <div class="svg-wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em">
                                        <circle cx="0.6em" cy="0.6em" r="0.5em" class="circle"/>
                                    </svg>
                                </div>
                            </div>
                        </div>


                        <iframe class="d-none" src=""></iframe>
                    </div>

                    <?PHP
                }
            }
            ?>
        </div>
        <div id="get_more_spinner" class="home_app_load_more_spinner" style="display: none;"> 
                <div class="item loading-5">
                    <div class="svg-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em">
                            <circle cx="0.6em" cy="0.6em" r="0.5em" class="circle"/>
                        </svg>
                    </div>
                </div>
            </div>
        <?php
    }

    function work_to_mtn() {
        global $post;
        $thumb = wp_get_attachment_url(get_post_thumbnail_id());
        ?>
        <div id="candidature_MTN">
            <div id="candidature_MTN_message" class="d-none">
                <div>
                    <i class="mdi mdi-checkbox-marked-circle-outline"></i>
                    <h2>Merci</h2>
                    <p>Nous avons bien reçu votre candidature. Nous prendrons contact avec vous dès qu'une opportunité correspondant à votre profil sera disponible</p>
                </div>
            </div>
            <div id="candidature_MTN_container" class="">
                <div style="background-image: url(<?php echo $thumb; ?>)" id="candidature_cover">
                    <h1><?php the_title(); ?></h1>
                </div>
                <div id="candidature_subtitle">
                    <?php echo wpautop($post->post_content); ?>
                </div>
                <div id="candidature_choice" class="v-hide" v-bind:class="{'v-show':show_interface}">
                    <div class="submit_form_container">
                        <button @click="set_choice('upload')" class="btn btn-secondary">Télécharger votre cv</button>
                    </div>
                    <br class='mb-3'>
                    <div class="submit_form_container">
                        <button @click="set_choice('generate')" class="btn btn-secondary">Générer un cv</button>
                    </div>
                    
                    
                </div>
                <div id="candidature_upload_cv" class="v-hide" v-bind:class="{'v-show':view_choice=='upload'}">
                    <div>
                        <div class="d-inline-block vertical-align-middle">
                            <label class="radio-container">
                                <span class="radio-container-title">Madame</span>
                                <input v-model="upload_user_info.gender" value="female" type="radio" name="gender">
                                <span class="checkmark"
                                    :class="{'has-error-form' : error_tabs.gender.in_proccess==true}"></span>
                            </label>
                        </div>
                        <div class="d-inline-block vertical-align-middle">
                            <label class="radio-container">
                                <span class="radio-container-title">Monsieur</span>
                                <input v-model="upload_user_info.gender" value="male" type="radio" name="gender">
                                <span class="checkmark"
                                    :class="{'has-error-form' : error_tabs.gender.in_proccess==true}"></span>
                            </label>
                        </div><br>
                        <span v-if="error_tabs.gender.in_proccess"
                            style="color:  #e70606 !important;">{{ error_tabs.gender.message }}</span>
                    </div>
                    <div class="form-group">
                        <input v-model="upload_user_info.last_name" name="last_name" type="text" class="form-control"
                            placeholder="Nom" :class="{'has-error-form' : error_tabs.last_name.in_proccess==true}">

                    </div>
                    <span v-if="error_tabs.last_name.in_proccess"
                        style="margin-top: 10px !important; color:  #e70606 !important; padding: 15px 15px;">{{ error_tabs.last_name.message }}</span>

                    <div class="form-group">
                        <input v-model="upload_user_info.first_name" name="first_name" type="text" class="form-control"
                            placeholder="Prénoms" :class="{'has-error-form' : error_tabs.first_name.in_proccess==true}">

                    </div>
                    <span v-if="error_tabs.first_name.in_proccess"
                        style="color:  #e70606 !important;">{{ error_tabs.first_name.message }}</span>
                        <div class="mb-1"></div>
                        <div class="job-sept-container-header">
                            <div>
                                <p>Tu souhaites soumettre tes compétences dans quel secteur d'activité chez nous ?</p>
                            </div>
                        </div>
                        <div class="form-group job-category-list">
                            <span v-if="error_tabs.category.in_proccess"
                                style="color:  #e70606 !important;">{{ error_tabs.category.message }}</span>
                            <br>
                            <div v-for="(categorie,index) in categories" class="d-block vertical-align-middle m-b-2">
                                <label class="radio-container">
                                    <span class="radio-container-title">{{categorie}}</span>
                                    <input v-model="upload_user_info.category" v-bind:name="'category_'+index"
                                        v-bind:value="categorie" type="radio">
                                    <span class="checkmark"
                                        :class="{'has-error-form' : error_tabs.category.in_proccess==true}"></span>
                                </label>
                            </div>
                        </div>
                        <div class="mb-1"></div>
                    <div class="form-group">
                        <div>
                            <input type="file" id="file" name="cv" @change="save_file()" />
                            <label for="file" /><span>Choisir un cv</span>&nbsp;<span id="loader-wrap" style="display: none; font-size: 0.7em;" class="ld ld-ring ld-spin" style="font-size:0.8em;"></span></label>
                        </div>
                        <div id="file_name_container" style="display: none;">
                            <span id="file_name"></span>&nbsp;<span class="mdi mdi-close" @click="remove_file()"></span>
                        </div>
                    </div>
                    <span v-if="error_tabs.cv.in_proccess"
                        style="color:  #e70606 !important;">{{ error_tabs.cv.message }}</span>
                    <div id="submit_upload_container" class="submit_form_container">
                        <button @click="submit_upload_cv()" class="btn btn-primary">Soumettre</button>
                    </div>
                </div>
                <div class="v-hide" v-bind:class="{'v-show':view_choice=='generate'}" id="candidature_MTN_form" method="get">
                    <div class="job-submit-step">
                        <div @click="prev_step(1)" v-bind:class="{'active':current_step==1}"><span>Pres.</span></div>
                        <div @click="prev_step(2)" v-bind:class="{'active':current_step==2}"><span>Cat.</span></div>
                        <div @click="prev_step(3)" v-bind:class="{'active':current_step==3}"><span>Dipl.</span></div>
                        <div @click="prev_step(4)" v-bind:class="{'active':current_step==4}"><span>Exp.</span></div>
                        <div @click="prev_step(5)" v-bind:class="{'active':current_step==5}"><span>Autres</span></div>
                    </div>

                    <div class="step-container v-hide" v-bind:class="{'v-show':current_step==1}">

                        <div>
                            <div class="d-inline-block vertical-align-middle">
                                <label class="radio-container">
                                    <span class="radio-container-title">Madame</span>
                                    <input v-model="user.gender" value="female" type="radio"  name="gender">
                                    <span class="checkmark" :class="{'has-error-form' : error_tabs.gender.in_proccess==true}"></span>
                                </label>
                            </div>
                            <div class="d-inline-block vertical-align-middle">
                                <label class="radio-container">
                                    <span class="radio-container-title">Monsieur</span>
                                    <input v-model="user.gender" value="male" type="radio" name="gender">
                                    <span class="checkmark" :class="{'has-error-form' : error_tabs.gender.in_proccess==true}"></span>
                                </label>
                            </div><br>
                            <span v-if="error_tabs.gender.in_proccess" style="color:  #e70606 !important;">{{ error_tabs.gender.message }}</span>
                        </div>
                        <div class="form-group">
                            <input v-model="user.last_name" name="last_name"  type="text" class="form-control"  placeholder="Nom" :class="{'has-error-form' : error_tabs.last_name.in_proccess==true}">
                            
                        </div>
                        <span v-if="error_tabs.last_name.in_proccess" style="margin-top: 10px !important; color:  #e70606 !important; padding: 15px 15px;">{{ error_tabs.last_name.message }}</span>

                        <div class="form-group">
                            <input v-model="user.first_name"   name="first_name" type="text" class="form-control"  placeholder="Prénoms" :class="{'has-error-form' : error_tabs.first_name.in_proccess==true}">
                            
                        </div>
                        <span v-if="error_tabs.first_name.in_proccess" style="color:  #e70606 !important;">{{ error_tabs.first_name.message }}</span>

                        <div class="form-group">
                            <input v-model="user.adress" name="adress" type="text" class="form-control"   placeholder="Adresse (Ville, quartier)" :class="{'has-error-form' : error_tabs.adress.in_proccess==true}">
                            
                        </div>
                        <span v-if="error_tabs.adress.in_proccess" style="color:  #e70606 !important;">{{ error_tabs.adress.message }}</span>

                        <div class="form-group">
                            <input v-model="user.email" name="email" type="text" class="form-control"  placeholder="Email" :class="{'has-error-form' : error_tabs.email.in_proccess==true}">
                            
                        </div>
                        <span v-if="error_tabs.email.in_proccess" style="color:  #e70606 !important;">{{ error_tabs.email.message }}</span>

                        <div class="form-group">
                            <input v-model="user.phone" name="phone" maxlength="8" type="number" class="form-control" id="phone"  placeholder="Téléphone" :class="{'has-error-form' : error_tabs.phone.in_proccess==true}">
                            
                        </div>
                        <span v-if="error_tabs.phone.in_proccess" style=" color:  #e70606 !important; ">{{ error_tabs.phone.message }}</span>
                    </div>

                    <div class="step-container v-hide" v-bind:class="{'v-show':current_step==2}">
                        <div class="job-sept-container-header">
                            <div><p>Tu souhaites soumettre tes compétences dans quel secteur d'activité chez nous ?</p></div>
                        </div>
                        <div class="form-group job-category-list">
                            <span v-if="error_tabs.category.in_proccess" style="color:  #e70606 !important;">{{ error_tabs.category.message }}</span>
                            <br >
                            <div v-for="(categorie,index) in categories" class="d-block vertical-align-middle m-b-2">
                                <label class="radio-container">
                                    <span class="radio-container-title">{{categorie}}</span>
                                    <input v-model="user.category" v-bind:name="'category_'+index" v-bind:value="categorie" type="radio" >
                                    <span class="checkmark" :class="{'has-error-form' : error_tabs.category.in_proccess==true}"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="step-container v-hide" v-bind:class="{'v-show':current_step==3}">
                        <div class="job-sept-container-header">
                            <div><p>Ajoute tes diplomes</p></div>
                            <div>
                                <span class="job-add-btn" @click="new_degree()">+</span>
                            </div>
                        </div>
                        <div class="job-degree-list" v-for="(degree,degree_index) in user.degrees.slice().reverse()">
                            <!--
                            <div class="form-group job-diploma-list">
                                <div v-for="(diploma,index) in diploma_lists" class="d-inline-block vertical-align-middle">
                                    <label class="radio-container">
                                        <span class="radio-container-title">{{diploma}}</span>
                                        <input v-model="degree.diploma" v-bind:name="'diploma_'+degree_index" v-bind:value="diploma" type="radio" >
                                        <span class="checkmark"></span>
                                    </label>
                                </div>
                            </div>
                            -->
                            <div  class="form-group job-diploma-list">
                                <label>Niveau</label>
                                <select v-bind:name="'diploma_'+degree_index" v-model="degree.diploma" class="form-control" :class="{'has-error-form' : error_tabs.speciality.in_proccess==true}">
                                    <option value="">Sélectionnez un niveau</option>
                                    <option  v-bind:value="diploma" v-for="(diploma,index) in diploma_lists">
                                        {{diploma}}
                                    </option>
                                </select>
                            </div>
                            <span v-if="error_tabs.diploma.in_proccess" style=" color:  #e70606 !important; ">{{ error_tabs.diploma.message }}</span>
                            <div class="form-group">
                                <input v-model="degree.speciality" type="text" class="form-control"  placeholder="Filière ou spécialité" :class="{'has-error-form' : error_tabs.speciality.in_proccess==true}">
                            </div>
                            <span v-if="error_tabs.speciality.in_proccess" style=" color:  #e70606 !important; " :class="{'has-error-form' : error_tabs.speciality.in_proccess==true}">{{ error_tabs.speciality.message }}</span>
                            <div class="form-group">
                                <input v-model="degree.year" type="number" class="form-control"  placeholder="Année d'obtention" :class="{'has-error-form' : error_tabs.year.in_proccess==true}">
                            </div>
                            <span v-if="error_tabs.year.in_proccess" style=" color:  #e70606 !important; " :class="{'has-error-form' : error_tabs.year.in_proccess==true}">{{ error_tabs.year.message }}</span>
                            <div class="form-group">
                                <input v-model="degree.school" type="text" class="form-control"  placeholder="École" :class="{'has-error-form' : error_tabs.school.in_proccess==true}">
                            </div>
                            <span v-if="error_tabs.school.in_proccess" style=" color:  #e70606 !important; " :class="{'has-error-form' : error_tabs.school.in_proccess==true}">{{ error_tabs.school.message }}</span>
                            <div>
                                <span @click="delete_degree(degree_index)" class="job-delete-degree">Supprimer</span>
                                <div class="clearfix"></div>
                            </div>
                            <div class="job-degree-list-separator"></div>
                        </div>

                    </div>

                    <div class="step-container v-hide" v-bind:class="{'v-show':current_step==4}">
                        <div class="job-sept-container-header">
                            <div><p>Ajoute tes expériences professionnelles</p></div>
                            <div>
                                <span class="job-add-btn" @click="new_experience()">+</span>
                            </div>
                        </div>
                        <div class="job-degree-list" v-for="(experience,index) in user.experiences.slice().reverse()">
                            <div class="form-group">
                                <input v-model="experience.company" type="text" class="form-control"  placeholder="Entreprise" :class="{'has-error-form' : error_tabs.company.in_proccess==true}">
                            </div>
                            <span v-if="error_tabs.company.in_proccess" style=" color:  #e70606 !important; " :class="{'has-error-form' : error_tabs.company.in_proccess==true}">{{ error_tabs.company.message }}</span>
                            <div class="form-group">
                                <input v-model="experience.place" type="text" class="form-control"  placeholder="Lieu de travail" :class="{'has-error-form' : error_tabs.place.in_proccess==true}">
                            </div>
                            <span v-if="error_tabs.place.in_proccess" style=" color:  #e70606 !important; " :class="{'has-error-form' : error_tabs.place.in_proccess==true}">{{ error_tabs.place.message }}</span>
                            <div class="form-group">
                                <input v-model="experience.position" type="text" class="form-control"  placeholder="Poste occupé" :class="{'has-error-form' : error_tabs.position.in_proccess==true}">
                            </div>
                            <span v-if="error_tabs.position.in_proccess" style=" color:  #e70606 !important; " :class="{'has-error-form' : error_tabs.position.in_proccess==true}">{{ error_tabs.position.message }}</span>
                            <div class="form-group">
                                <input v-model="experience.tasks" type="text" class="form-control"  placeholder="Missions/Tâches effectuées" :class="{'has-error-form' : error_tabs.tasks.in_proccess==true}">
                            </div>
                            <span v-if="error_tabs.tasks.in_proccess" style=" color:  #e70606 !important; " :class="{'has-error-form' : error_tabs.tasks.in_proccess==true}">{{ error_tabs.tasks.message }}</span>
                            <div class="form-group">
                                <input v-model="experience.start_date" type="text" class="form-control"  placeholder="Date de début (JJ/MM/AAAA)":class="{'has-error-form' : error_tabs.start_date.in_proccess==true}">
                            </div>
                            <span v-if="error_tabs.start_date.in_proccess" style=" color:  #e70606 !important; " :class="{'has-error-form' : error_tabs.start_date.in_proccess==true}">{{ error_tabs.start_date.message }}</span>
                            <div class="form-group">
                                <input v-model="experience.end_date" type="text" class="form-control"  placeholder="Date de fin (JJ/MM/AAAA)" :class="{'has-error-form' : error_tabs.end_date.in_proccess==true}">
                            </div>
                            <span v-if="error_tabs.end_date.in_proccess" style=" color:  #e70606 !important; " :class="{'has-error-form' : error_tabs.end_date.in_proccess==true}">{{ error_tabs.end_date.message }}</span>
                        </div>
                        <div>
                            <span @click="delete_experience(index)" class="job-delete-degree">Supprimer</span>
                            <div class="clearfix"></div>
                        </div>
                        <div class="job-degree-list-separator"></div>
                    </div>


                    <div class="step-container v-hide" v-bind:class="{'v-show':current_step==5}">
                        <div class="form-group">
                            <input v-model="user.langues" type="text" class="form-control"  placeholder="Langues parlées" :class="{'has-error-form' : error_tabs.langues.in_proccess==true}">
                        </div>
                        <span v-if="error_tabs.langues.in_proccess" style=" color:  #e70606 !important; " :class="{'has-error-form' : error_tabs.langues.in_proccess==true}">{{ error_tabs.langues.message }}</span>
                        <div class="form-group">
                            <input v-model="user.infos" type="text" class="form-control"  placeholder="Autres informations à préciser" :class="{'has-error-form' : error_tabs.infos.in_proccess==true}">
                        </div>
                        <span v-if="error_tabs.infos.in_proccess" style=" color:  #e70606 !important; " :class="{'has-error-form' : error_tabs.infos.in_proccess==true}">{{ error_tabs.infos.message }}</span>
                    </div>


                    <div id="submit_form_container">
                        <button @click="next_step()" class="btn btn-primary">{{get_button_title()}}</button>
                    </div>

                    <?php echo wp_nonce_field('jobs_hire_11430109_229'); ?>
                    <input type="hidden" value="bdetails" name="action">
                        <input type="hidden" value="add_phone_number" name="action_type">
                            </div>

                            <div id="spinner_overlay" v-bind:class="{'d-none':!show_spinner}" > 
                                <div class="item loading-5">
                                    <div class="svg-wrapper">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em">
                                            <circle cx="0.6em" cy="0.6em" r="0.5em" class="circle"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            </div>
                            </div>
                            <script>

                                jQuery(document).ready(function ($) {
                                    var JOB_FORM = new Vue({
                                        el: '#candidature_MTN',
                                        data: {
                                            current_step: 1,
                                            btn_title: "Suivant",
                                            view_choice: "",
                                            show_interface: false,
                                            show_spinner: false,
                                            upload_user_info: {
                                                first_name: "",
                                                last_name: "",
                                                cv: "",
                                                gender:"",
                                                category: "",
                                            },
                                            user: {
                                                first_name: "",
                                                last_name: "",
                                                adress: "",
                                                phone: "",
                                                langues: "",
                                                infos: "",
                                                category: "",
                                                birthday: "",
                                                degrees: [{diploma: "", speciality: "", year: "", school: ""}],
                                                experiences: [{company: "", position: "", place: "", start_date: "", end_date: "", tasks: ""}]
                                            },
                                            diploma_lists: ["BEPC", "BAC", "BTS", "BAC+1", "BAC+2", "BAC+3", "BAC+4", "BAC+5"],
                                            categories: ["Informatique", "Télécom", "Comptabilité", "Audit", "Ressources Humaines", "Projets", "Commercial"],
                                            check_error: 0,
					                        error_tabs: {
                                                cv: {
                                                    in_proccess: false,
                                                    message: "Champs obligatoire"
                                                },
					                            first_name: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            last_name: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            adress: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            phone: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            email: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            gender: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            category: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            diploma: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            speciality: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            year: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            school: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            company: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            place: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            position: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            start_date: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            end_date: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            tasks: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            infos: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                            langues: {
					                                in_proccess: false,
					                                message: ""
					                            },
					                        },
					                    },
					                    mounted: function () {
					                        this.show_interface = true;
					                    },
					                    computed: {
					                        has_error_from: function(){
					                            if (this.error_tabs.first_name.in_proccess) {
					                                return "border-bottom: 1px solid #e81060";
					                            }
					                        }
					                    },
					                    methods: {
					                        get_button_title: function () {
					                            if (this.current_step === 5) {
					                                return "Terminer";
					                            } else {
					                                return "Suivant";
					                            }
					                        },
					                        isEmail: function(email){
					                            var regex=/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
					                            if (regex.test(email)) {
					                                return true;
					                            }
					                            
					                            return false;
					                        },
					                        validate_presentation_fields: function(){
					                            var fields = [
					                                {
					                                    name: "gender",
					                                    required_message: "Champ obligatoire",
					                                    required: true
					                                },
					                                {
					                                    name: "last_name",
					                                    min_length: 2,
					                                    min_length_msg: "Ce Nom n'est pas trop correct",
					                                    required_message: "Champ obligatoire",
					                                    required: true,
					                                }, {
					                                    name: "first_name",
					                                    min_length: 2,
					                                    min_length_msg: "Le Prénom n'est pas correct",
					                                    required_message: "Champ obligatoire",
					                                    required: true,
					                                },
					                                {
					                                    name: "adress",
					                                    field_length_msg: "Merci de bien préciser une adresse valide",
					                                    required_message: "Champ obligatoire",
					                                    min_length: 2,
					                                    required: true
					                                },
					                                {
					                                    name: "email",
					                                    required_message: "Champ obligatoire",
					                                    required: true,
					                                    is_email: true,
					                                    is_email_message: "Ton adresse email est incorrect"
					                                },
					                                 {
					                                    name: "phone",
					                                    field_length_msg: "N° téléphone incorrect doit avoir 8 chiffres",
					                                    required_message: "Champ obligatoire",
					                                    is_numeric: true,
					                                    field_length: 8,
					                                    required: true
					                                },
					                                
					                            ];

					                            this.check_error = 1;
					                            for (i = 0; i < fields.length; i++) {
					                                var element = fields[i];
					                                if (!_.isUndefined(element.required)) {
					                                    if (this.user[element.name] == "") {
					                                        var message = (_.isUndefined(element.required_message)) ? "Erreur" : element.required_message;
					                                        this.error_tabs[element.name].message = message;
					                                        this.error_tabs[element.name].in_proccess = true;
					                                        this.check_error *= 0;
					                                        return false;
					                                    }
					                                }

					                                if (!_.isUndefined(element.is_numeric)) {
					                                    if (element.is_numeric) {
					                                        if (!jQuery.isNumeric(this.user[element.name])) {
					                                            var message = (_.isUndefined(element.is_numeric_msg)) ? "Valeur incorrecte" : element.is_numeric_msg;
					                                            this.error_tabs[element.name].message = message;
					                                            this.error_tabs[element.name].in_proccess = true;
					                                            this.check_error *= 0;
					                                            return false;
					                                        }
					                                    } else {
					                                        if (jQuery.isNumeric(this.user[element.name])) {
					                                            var message = (_.isUndefined(element.is_numeric_msg)) ? "Valeur incorrecte" : element.is_numeric_msg;
					                                            this.error_tabs[element.name].message = message;
					                                            this.error_tabs[element.name].in_proccess = true;
					                                            this.check_error *= 0;
					                                            return false;
					                                        }
					                                    }
					                                }

					                                if (!_.isUndefined(element.is_email)) {
					                                    if (!this.isEmail(this.user[element.name])) {
					                                        var message = (_.isUndefined(element.is_email_message)) ? "Erreur" : element.is_email_message;
					                                        //console.log(this.isEmail(this.user[element.name]));
					                                        this.error_tabs[element.name].message = message;
					                                        this.error_tabs[element.name].in_proccess = true;
					                                        return false;
					                                    }
					                                }

					                                if (!_.isUndefined(element.min_length)) {
					                                    if (!(this.user[element.name].length >= element.min_length)) {
					                                        var message = (_.isUndefined(element.min_length_msg)) ? "Erreur" : element.min_length_msg;
					                                        this.error_tabs[element.name].message = message;
					                                        this.error_tabs[element.name].in_proccess = true;
					                                        this.check_error *= 0;
					                                        return false;
					                                    }
					                                }


					                                if (!_.isUndefined(element.max_length)) {
					                                    if (!(this.user[element.name].length <= element.max_length)) {
					                                        message = (_.isUndefined(element.max_length_msg)) ? "Erreur" : element.max_length_msg;
					                                        this.error_tabs[element.name].message = message;
					                                        this.error_tabs[element.name].status = true;
					                                        this.check_error *= 0;
					                                        return false;
					                                    }
					                                }


					                                if (!_.isUndefined(element.field_length)) {
					                                    if (!(this.user[element.name].length == element.field_length)) {
					                                        message = (_.isUndefined(element.field_length_msg)) ? "Erreur" : element.field_length_msg;
					                                        this.error_tabs[element.name].message = message;
					                                        this.error_tabs[element.name].status = true;
					                                        this.check_error *= 0;
					                                        return false;
					                                    }
					                                }

					                                this.error_tabs[element.name].in_proccess = false;
					                            }

					                            return this.check_error;
					                        },
					                        validate_category_fields: function(){
					                            var fields = [
					                                {
					                                    name: "category",
					                                    required_message: "Vous devez choisir une catégorie",
					                                    required: true
					                                },
					                            ];
					                            this.check_error = 1;
					                            for (i = 0; i < fields.length; i++) {
					                                var element = fields[i];
					                                if (!_.isUndefined(element.required)) {
					                                    if (this.user[element.name] == "") {
					                                        var message = (_.isUndefined(element.required_message)) ? "Erreur" : element.required_message;
					                                        this.error_tabs[element.name].message = message;
					                                        this.error_tabs[element.name].in_proccess = true;
					                                        this.check_error *= 0;
					                                        return false;
					                                    }
					                                }

					                                this.error_tabs[element.name].in_proccess = false;
					                            }
					                            return this.check_error;

					                        },
					                        validate_diplome_fields: function(){
					                            var fields = [
					                                // {diploma: "", speciality: "", year: "", school: ""}
					                                {
					                                    name: "diploma",
					                                    required_message: "Champ obligatoire",
					                                    required: true
					                                },
					                                {
					                                    name: "speciality",
					                                    required_message: "Champ obligatoire",
					                                    required: true
					                                },
					                                {
					                                    name: "year",
					                                    required_message: "Champ obligatoire",
					                                    required: true
					                                },
					                                {
					                                    name: "school",
					                                    required_message: "Champ obligatoire",
					                                    required: true
					                                },
					                            ];
					                            this.check_error = 1;
					                            if (!_.isEmpty(this.user.degrees[0].diploma) && !_.isUndefined(this.user.degrees[0].diploma)){
					                                for(y= 0; y< this.user.degrees.length; y++) {
					                                    var degree = this.user.degrees[y];
					                                    for(i=0; i< fields.length; i++) {
					                                        var element = fields[i];
					                                        if (!_.isUndefined(element.required)) {
					                                            if (degree[element.name] == "") {
					                                                var message = (_.isUndefined(element.required_message)) ? "Erreur" : element.required_message;
					                                                this.error_tabs[element.name].message = message;
					                                                this.error_tabs[element.name].in_proccess = true;
					                                                this.check_error *= 0;
					                                                return false;
					                                            }
					                                        }
					                                        this.error_tabs[element.name].in_proccess = false;
					                                    }
					                                }
					                            }else{
					                                this.error_tabs['diploma'].message = "Entrez au moin un diplome";
					                                this.error_tabs["diploma"].in_proccess = true;
					                                this.check_error *= 0;
					                                return false
					                            }
										    },
					                        validate_experience_fields: function(){
					                            var fields = [
					                                // {company: "", position: "", place: "", start_date: "", end_date: "", tasks: ""}
					                                {
					                                    name: "company",
					                                    required_message: "Champ obligatoire",
					                                    required: true
					                                },
					                                {
					                                    name: "place",
					                                    required_message: "Champ obligatoire",
					                                    required: true
					                                },
					                                {
					                                    name: "position",
					                                    required_message: "Champ obligatoire",
					                                    required: true
					                                },
					                                {
					                                    name: "tasks",
					                                    required_message: "Champ obligatoire",
					                                    required: true
					                                },
					                                {
					                                    name: "start_date",
					                                    required_message: "Champ obligatoire",
					                                    required: true
					                                },
					                                {
					                                    name: "end_date",
					                                    required_message: "Champ obligatoire",
					                                    required: true
					                                },
					                                
					                            ];
					                            this.check_error = 1;
					                            if (!_.isEmpty(this.user.experiences[0].company) && !_.isUndefined(this.user.experiences[0].company)){
					                                for(y= 0; y< this.user.experiences.length; y++) {
					                                    var exp = this.user.experiences[y];
					                                    for(i=0; i< fields.length; i++) {
					                                        var element = fields[i];
					                                        if (!_.isUndefined(element.required)) {
					                                            if (exp[element.name] == "") {
					                                                var message = (_.isUndefined(element.required_message)) ? "Erreur" : element.required_message;
					                                                this.error_tabs[element.name].message = message;
					                                                this.error_tabs[element.name].in_proccess = true;
					                                                this.check_error *= 0;
					                                                return false;
					                                            }
					                                        }
					                                        this.error_tabs[element.name].in_proccess = false;
					                                    }
					                                }
					                            }else{
					                                this.error_tabs['company'].message = "Entrez au moin une expérience professionnelle";
					                                this.error_tabs["company"].in_proccess = true;
					                                this.check_error *= 0;
					                                return false
					                            }
										    },
										    validate_others_fields: function (){
					                            var fields = [
					                                {
					                                    name: "langues",
					                                    required_message: "Champ obligatoire",
					                                    required: true
					                                },
					                                {
					                                    name: "infos",
					                                    required_message: "Champ obligatoire",
					                                    required: true
					                                }
					                            ];
					                            this.check_error = 1;
					                            for (i = 0; i < fields.length; i++) {
					                                var element = fields[i];
					                                if (!_.isUndefined(element.required)) {
					                                    if (this.user[element.name] == "") {
					                                        var message = (_.isUndefined(element.required_message)) ? "Erreur" : element.required_message;
					                                        this.error_tabs[element.name].message = message;
					                                        this.error_tabs[element.name].in_proccess = true;
					                                        this.check_error *= 0;
					                                        return false;
					                                    }
					                                }

					                                this.error_tabs[element.name].in_proccess = false;
					                            }
					                            return this.check_error;

					                        },
					                        validate_generate_cv_fields: function(){
					                            var fields = [
					                                {
					                                    name: "gender",
					                                    required_message: "Vous devez choisir votre civilité",
					                                    required: true
					                                },
					                                {
					                                    name: "last_name",
					                                    min_length: 2,
					                                    min_length_msg: "Ce Nom n'est pas trop correct",
					                                    required_message: "Champ obligatoire",
					                                    required: true,
					                                }, {
					                                    name: "first_name",
					                                    min_length: 2,
					                                    min_length_msg: "Le Prénom n'est pas correct",
					                                    required_message: "Champ obligatoire",
					                                    required: true,
					                                },
					                                {
					                                    name: "category",
					                                    required_message: "Vous devez choisir une catégorie",
					                                    required: true
					                                },
					                                {
					                                    name: "cv",
					                                    required_message: "Champs obligatoire",
					                                    required: true
					                                },
					                            ];
					                            this.check_error = 1;
					                            for (i = 0; i < fields.length; i++) {
					                                var element = fields[i];
					                                if (!_.isUndefined(element.required)) {
					                                    if (this.upload_user_info[element.name] == "") {
					                                        var message = (_.isUndefined(element.required_message)) ? "Erreur" : element.required_message;
					                                        this.error_tabs[element.name].message = message;
					                                        this.error_tabs[element.name].in_proccess = true;
					                                        this.check_error *= 0;
					                                        return false;
					                                    }
					                                }

					                                this.error_tabs[element.name].in_proccess = false;
					                            }
					                            return this.check_error;

					                        },
					                        next_step: function () {
					                            if (this.current_step === 5) {
					                                if(!this.validate_others_fields() === 1 || this.validate_others_fields() == false){
					                                    $('html,body').animate({scrollTop: 0}, 'slow');
					                                    return;
					                                }
					                                this.submit();
					                                return;
					                            }
					                            if ((this.current_step + 1) > 5) {
					                                return;
					                            }
					                            if (this.current_step === 1){
					                                if(!this.validate_presentation_fields() === 1 || this.validate_presentation_fields() == false){
					                                    $('html,body').animate({scrollTop: 0}, 'slow');
					                                    return;
					                                }
					                            }
					                            if (this.current_step === 2){
					                                if(!this.validate_category_fields() === 1 || this.validate_category_fields() == false){
					                                    $('html,body').animate({scrollTop: 0}, 'slow');
					                                    return;
					                                }
					                            }
					                            if (this.current_step === 3){
					                                if(!this.validate_diplome_fields() === 1 || this.validate_diplome_fields() == false){
					                                    $('html,body').animate({scrollTop: 0}, 'slow');
					                                    return;
					                                }
					                            }
					                            if (this.current_step === 4){
					                                if(!this.validate_experience_fields() === 1 || this.validate_experience_fields() == false){
					                                    //console.log('bad');
					                                    $('html,body').animate({scrollTop: 0}, 'slow');
					                                    return;
					                                }
					                            }
					                            this.current_step += 1;
					                            $('html,body').animate({scrollTop: 0}, 'slow');
					                        },
                                            prev_step: function (step) {
                                                if (this.current_step < step) {
                                                    return;
                                                }
                                                if (step == 1) {
                                                    this.current_step = 1;
                                                } else {
                                                    this.current_step -= 1;
                                                }
                                            },
                                            set_choice: function (choice) {
                                                this.view_choice = choice;
                                                this.show_interface = false;
                                                return;
                                            },
                                            submit_upload_cv: function() {
                                                if(!this.validate_generate_cv_fields() === 1 || this.validate_generate_cv_fields() == false){
					                                $('html,body').animate({scrollTop: 0}, 'slow');
					                                return;
					                            }
                                                this.show_spinner = true;
                                                var that = $(this);
                                                var data = {action: "job", action_type: "hire", action_values: this.upload_user_info};
                                                $.ajax({
                                                    url: "<?php echo admin_url("admin-ajax.php"); ?>",
                                                    data: data,
                                                    type: "POST",
                                                    dataType: "json",
                                                    cache: false,
                                                    complete: function (response) {
                                                        that.show_spinner = true;
                                                        // console.log(response);
                                                        $("#candidature_MTN_container").fadeOut(300, function () {
                                                            $("#candidature_upload_cv").remove();
                                                            $("#candidature_MTN_message").removeClass("d-none");
                                                        });
                                                    }
                                                });
                                            },
                                            save_file: function() {
                                                this.error_tabs.cv.in_proccess = false;
                                                var fd = new FormData();
                                                var fileIpnut = document.getElementById('file');
                                                var files = $('#file')[0].files[0];
                                                var filePath = fileIpnut.value;
                                                var allowedExtensions = /(\.pdf|\.doc|\.docx)$/i;
                                                var self = this;

                                                if (allowedExtensions.exec(filePath)) {
                                                    fd.append('cv',files);
                                                    fd.append('action','job');
                                                    fd.append('action_type',"save_file");
                                                    
                                                    $.ajax({
                                                        url: "<?php echo admin_url("admin-ajax.php"); ?>",
                                                        type: 'POST',
                                                        data: fd,
                                                        contentType: false,
                                                        processData: false,
                                                        beforeSend: function() {
                                                            $('#loader-wrap').css('display', 'inline-block');
                                                        },
                                                        success: function(data){
                                                            data = JSON.parse(data);
                                                             if (data.status){
						                                            self.upload_user_info.cv = data.cv_id;
						                                            $('#file_name').text(files.name);
						                                            $('#loader-wrap').hide('slow');
						                                            $('#file_name_container').show('fast');
						                                        }else{
						                                            $('#file_name').text("Echec !!!");
						                                            $('#loader-wrap').hide('slow');
						                                            $('#file_name_container').show('fast');
						                                        }
                                                        }
                                                    });
                                                }else{
                                                    this.error_tabs.cv.in_proccess = true;
                                                }
                                            },
                                            remove_file: function(){
					                            var self = this;
					                            $("#file_name_container").hide('fast');
					                            $('#file_name').text("");
					                            if (!_.isEmpty(self.upload_user_info.cv) || ! _.isNull(self.upload_user_info.cv)){
					                                var data = {action: "hire", action_type: "remove_file", file: self.upload_user_info.cv}
					                                $.ajax({
						                                url: "<?php echo admin_url("admin-ajax.php"); ?>",
						                                data: data,
						                                type: "POST",
						                                dataType: "json",
						                                cache: false,
						                                complete: function (response) {
						                                    self.upload_user_info.cv = ""
						                                }
						                            });
					                            }
					                        },
                                            submit: function () {
                                                this.show_spinner = true;
                                                var that = $(this);
                                                var data = {action: "job", action_type: "hire", action_values: this.user};
                                                var ajax_request = {url: MYMTN.ajax_url, data: data};

                                                call_ajax(ajax_request, function (data) {
                                                    that.show_spinner = true;
                                                    $("#candidature_MTN_container").fadeOut(300, function () {
                                                        $("#candidature_MTN_form").remove();
                                                        $("#candidature_MTN_message").removeClass("d-none");
                                                    });
                                                }, true);
                                            }
                                            ,
                                            new_degree: function () {
                                                this.user.degrees.push({diploma: "", speciality: "", year: "", school: ""});
                                            },
                                            delete_degree: function (index) {
                                                this.$delete(this.user.degrees, index);
                                            },
                                            delete_experience: function (index) {
                                                this.$delete(this.user.experiences, index);
                                            },
                                            new_experience: function () {
                                                this.user.experiences.push({company: "", position: "", place: "", start_date: "", end_date: "", tasks: ""});
                                            },
                                        }
                                    });

                                });
                            </script>
                            <?php
                        }

                    }

                    new Jobs();
                    