<?php
/*
 Plugin Name: zeList Importer
 Plugin URI: https://solutions.fluenx.com/
 Description: Import FreeGlobes into zeList
 Author: Fluenx
 Version: 0.3
 Author URI: https://www.fluenx.com/

 Copyright (C) 2008  Malaiac

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 */


define('ZELIST_IMPORT_PATH',dirname(__FILE__));
define('ZELIST_IMPORT_URL',get_option('home').'/'.PLUGINDIR.'/zelist-importer');

function zelist_import_admin_menu() {
  global $submenu;
  $submenu['link-manager.php'][30] = array( __('zeList Import','zelist-importer'), 'manage_links_advanced', 'admin.php?page=zelist-importer/admin.php' );
}
add_action('zelist_admin_menu_hook','zelist_import_admin_menu');


function zelist_import_load() {
  wp_enqueue_style('zelist_admin',ZELIST_URL.'/style/admin.css');
  wp_enqueue_script('zelist-importer',ZELIST_IMPORT_URL.'/zelist-importer.js');
}
add_action('load-zelist-importer/admin.php','zelist_import_load');

/**
 * Before a 404 header is sent, and if import data is available, try to guess the correct location
 * @param $header
 * @return unknown_type
 */
function zelist_import_redirect_old_urls($header) {
  if($header != 'HTTP/1.1 404 Not Found') return;

  $heritage = get_option('zelist_heritage');
  if(!$heritage) return;

  $translation = array();
  foreach($heritage as $source => $true) {
    $regexes = get_option("zelist_$source".'_regexes');
    $regexes = array('links' => '[a-z\-]*?-(\d+)\.html', 'categories' => '[a-z\-]*?-(\d+)');
    update_option('zelist_freeglobes_regexes',$regexes);
    $translation['links_regex'][$source] = $regexes['links'];
    $translation['categories_regex'][$source] = $regexes['categories'];
    $translation[$source]['links'] = get_option("zelist_$source".'_links_translation');
    $translation[$source]['categories'] = get_option("zelist_$source".'_categories_translation');

  }

  if(!count($translation)) return;

  $request_uri = trim($_SERVER['REQUEST_URI'],'/');

  $location = '';
  foreach($translation['links_regex'] as $source => $regex) {
    if(preg_match("#$regex#i",$request_uri,$matches)) {
      $link_id = $translation[$source]['links'][$matches[1]];
      $location = get_link_permalink($link_id);
    }
  }
  if(empty($location)) {
    foreach($translation['categories_regex'] as $source => $regex) {
      if(preg_match("#$regex#i",$request_uri,$matches)) {
        $found = true;
        $cat_id = $translation[$source]['categories'][$matches[1]];
        $location = get_link_category_link($cat_id);
      }
    }
  }
  if(!empty($location)) {
    wp_redirect($location,'301');
    exit;
  }
  return;
}
add_filter('status_header','zelist_import_redirect_old_urls',9);


/**
 * Ajax action, FreeGlobes step 1, check prefix
 * @return unknown_type
 */
function zelist_import_freeglobes_check_prefix() {
  check_ajax_referer( 'import' );
  header('Content-type: text/json');
  $data = '';
  $warning = 1;
  $step = 0;
  $inputs = array();

  parse_str($_POST['settings'],$settings);
  extract($settings,EXTR_OVERWRITE);
  if(!$prefix) {
    $data = __('Missing_prefix','zelist-importer');
    $r = compact('warning','data');	echo json_encode($r);	die();
  }

  if(substr($prefix,-1,1) != '_') $prefix .= '_';

  global $wpdb;
  $tables = $wpdb->get_results("SHOW TABLES",ARRAY_N);
  $found_tables = 0;
  $freeglobes_tables = array();

  if($tables) foreach($tables as $table) {
    $table = $table[0];
    $data = "$table / $prefix = ".stripos($table,$prefix);
    if(stripos($table,$prefix) === 0) {
      $found_tables++;
      $freeglobes_tables[] = $table;
    }
  }
  if($found_tables == 0) {
    $data = __('No table found','zelist-importer');
    $r = compact('warning','data');	echo json_encode($r);	die();
    if($tables) $data .= '<ul><li>'.implode('</li><li>',$tables).'</li></ul>';

  }
  else {
    $data = __('Tables found:','zelist-importer');
    $data .= '<ul><li>'.implode('</li><li>',$freeglobes_tables).'</li></ul>';
    $warning = 0;
    $step = 2;
    $inputs['prefix'] = $prefix;
  }
  $r = compact('warning','data','step','inputs');	echo json_encode($r);	die();
}
add_action( 'wp_ajax_import_freeglobes_check_prefix','zelist_import_freeglobes_check_prefix');


function zelist_import_freeglobes_categories() {
  check_ajax_referer( 'import' );
  header('Content-type: text/json');
  $data = '';
  $warning = 1;
  $step = 0;
  $inputs = array();

  parse_str($_POST['static_settings'],$settings);
  extract($settings,EXTR_OVERWRITE);

  if(!$static_prefix) {
    $data = __('Missing_prefix','zelist-importer');
    $r = compact('warning','data');	echo json_encode($r);	die();
  }
  global $wpdb;
  // DO NOT UNCOMMENT unless you know what you're doing
  //$wpdb->query("TRUNCATE $wpdb->terms;");
  //$wpdb->query("TRUNCATE $wpdb->term_relationships;");
  //$wpdb->query("TRUNCATE $wpdb->term_taxonomy;");


  $category_table = $static_prefix.'category';

  if(!$results = $wpdb->get_results("SELECT * FROM $category_table")) {
    $data = __('SQL error','zelist-importer');
    $r = compact('warning','data');	echo json_encode($r);	die();
  }

  // Categories hierarchy
  foreach($results as $category) {
    $categories[$category->id] = $category;
    if(!isset($categories_hierarchy[$category->id])) $categories_hierarchy[$category->id] = array();
    if($category->root != 0) $categories_hierarchy[$category->root][] = $category->id;
  }

  // Categories creation

  // reset categories translations
  $categories_translation = array();
  delete_option('zelist_freeglobes_categories_translation');
  add_option('zelist_freeglobes_categories_translation',$categories_translation);

  $created = $counter = 0;

  $i = 0;
  $log = array();
  while(count($categories)) {
    // no cat
    if(!isset($categories[$i])) { $i++; continue; }

    $category = $categories[$i];

    // already done...
    if(isset($categories_translation[$category->id])) { unset($categories[$i]); $i++; continue; }

    $parent_true_id = 0;
    if($category->root != 0) {
      // parent is not known yet
      if(!isset($categories_translation[$category->root])) { $i++; continue; }
      else $parent_true_id = $categories_translation[$category->root];
    }


    $name = $category->name;
    $args = array('parent' => $parent_true_id);
    $link_category = false;
    $link_category = wp_insert_term( $name, 'link_category', $args);
    $created++;
    $log[] = "name $name / id $category->id / parent $parent_true_id (was $category->root) ===> ".$link_category['term_id'];
    $categories_translation[$category->id] = $link_category['term_taxonomy_id'];
    unset($categories[$i]);
  }

  update_option('zelist_freeglobes_categories_translation',$categories_translation);

  $warning = 0;
  $step = 3;
  $data = sprintf(__('%d categories created','zelist-importer'),$created);
  // uncomment for debug
  //$data .= '<br />'.implode('<br />',$log);
  $data .= '<ul id="categories">'.wp_list_categories('type=link&echo=0&hide_empty=0&title_li=').'</ul>';

  $r = compact('warning','data','step','inputs');
  echo json_encode($r);
  die();
}
add_action( 'wp_ajax_import_freeglobes_categories','zelist_import_freeglobes_categories');

function zelist_import_freeglobes_links() {
  check_ajax_referer( 'import' );
  include(ZELIST_IMPORT_PATH.'/counter.class.php');
  $counter = new counter();

  header('Content-type: text/json');
  $data = '';
  $warning = 1;
  $step = 0;
  $inputs = array();

  if($counter) $counter->start('preparation');

  parse_str($_POST['static_settings'],$settings);
  extract($settings,EXTR_OVERWRITE);

  if(!$static_prefix) {
    $data = __('Missing_prefix','zelist-importer');
    $r = compact('warning','data');	echo json_encode($r);	die();
  }

  global $wpdb;

  // DO NOT UNCOMMENT unless you know what you're doing
  //$wpdb->query("TRUNCATE $wpdb->links;");



  $links_table = $static_prefix.'link';
  $feeds_table = $static_prefix.'feed';

  // links request
  $links = $wpdb->get_results("SELECT * FROM $links_table");

  if(!$links) {
    $data = __('SQL Error','zelist-importer');
    $r = compact('warning','data');	echo json_encode($r);	die();
  }

  // existing
  $existing = array();
  $results = $wpdb->get_results("SELECT link_id,link_url FROM $wpdb->links");
  foreach($results as $result) {
    $existing[$result->link_url] = $result->link_id;
  }


  // feed request
  $results = $wpdb->get_results("SELECT linkid,feed FROM $feeds_table");
  if($results)
  foreach($results as $result) {
    $feeds[$result->linkid] = $result->feed;
  }

  $categories_translation = get_option('zelist_freeglobes_categories_translation');

  $links_translation = get_option('zelist_freeglobes_links_translation');
  if(!$links_translation) {
    $links_translation = array();
    add_option('zelist_freeglobes_links_translation',$links_translation);
  }
  $known = $added = $users_created = 0;
  $link_stati = array();

  if($counter) $counter->stop('preparation');
  if($counter) $counter->start('loop');

  foreach($links as $link) {
    if(isset($existing[$link->url])) {
      $known++;
      $links_translation[$link->id] = $existing[$link->url];
      continue;
    }
    if($counter) $counter->start('link_prepare');
    $added++;
    global $linkdata;
    $link_name = '';
    $link_url = '';
    $link_name = wp_specialchars($link->name);
    $link_url = wp_specialchars(clean_url($link->url));

    if($counter) $counter->stop('link_prepare');
    if($counter) $counter->start('link_owner');
    $email = $link->email;
    if(!is_email($email)) {
      $host = parse_url($link->url);
      $host = $host['host'];
      $email = 'contact@'.str_replace('www.','',$host);
    }

    if($counter) $counter->start('get_user_by_email');
    $owner = get_user_by_email($email);
    if($counter) $counter->stop('get_user_by_email');

    if($owner) {
      $owner_id = $owner->ID;
    }
    else {
      $users_created++;
      if($counter) $counter->start('wp_create_user');
      $owner_id = wp_create_user($email, wp_generate_password(),$email);
      if($counter) $counter->stop('wp_create_user');
    }
    $link_owner = $owner_id;
    if($counter) $counter->stop('link_owner');

    if($counter) $counter->start('link_prepare');

    $link_description = $link->description;

    if(!isset($link->category)) $link_category = get_option( 'default_link_category' );
    else $link_category = $categories_translation[$link->category];
    $link_image = $link->image;
    $link_rating = $link->vote;
    if(isset($feeds[$link->id])) $link_rss = $feeds[$link->id];

    // too long. stops around 600 links on a shared hosting
    // $link_id = wp_insert_link($linkdata);


    // ADDING zeList values : status, pagerank, link_added, link_updated

    /* FreeGlobes Status :
     1 = submitted, waiting,
     2 = banned
     3 = unknown...
     4 = valid
     */

    if($link->state == 1) $link_status = 'pending';
    elseif($link->state == 2) $link_status = 'deny';
    elseif($link->state == 4) $link_status = 'publish';
    else $link_status = 'pending';
    $link_states[$link->state]++;
    $link_visible = ($link_status == 'publish') ? 'Y' : 'N';

    $link_pagerank = $link->pr;
    $link_updated = $link_added = $link->date;
    if($link->admin_date != '0000-00-00 00:00:00') $link_updated = $link->admin_date;

    if($counter) $counter->stop('link_prepare');
    if($counter) $counter->start('link_query');

    $query = $wpdb->prepare(
		"INSERT INTO $wpdb->links (
		link_url, link_name, link_image, link_target,
		link_description, link_visible, link_owner, link_rating,
		link_rel, link_notes, link_rss, link_status,
		link_added, link_updated) "
    ."VALUES("
    ."%s, %s, %s, %s, "
    ."%s, %s, %s, %s, "
    ."%s, %s, %s, %s, "
    ."%s, %s)",
    $link_url,$link_name, $link_image, $link_target,
    $link_description, $link_visible, $link_owner, $link_rating,
    $link_rel, $link_notes, $link_rss, $link_status,
    $link_added,$link_updated
    );
    if(!$wpdb->query( $query)) {
      $data .= __('SQL Error','zelist-importer');
      $data .= $wpdb->last_error;
      break;
    }

    if($counter) $counter->stop('link_query');

    $link_id = (int) $wpdb->insert_id;

    // Faster than wp_set_link_cats( $link_id, $link_category );
    if($counter) $counter->start('link_categories');
    $wpdb->insert( $wpdb->term_relationships, array( 'object_id' => $link_id, 'term_taxonomy_id' => $link_category ) );
    if($counter) $counter->stop('link_categories');

    $link_stati[$link_status]++;
    $links_translation[$link->id] = $link_id;

    $log[] = "lid$link->id > $link_id / url $link->url / $link_status";
    if($counter && $counter->reaching_time_limit()) {
      $data .= __('Time limit reached','zelist-importer');
      break;
    }

  }
  if($counter) $counter->stop('loop');


  update_option('zelist_freeglobes_links_translation',$links_translation);

  if($counter) $counter->start('wp_update_term_count_link_category');
  wp_update_term_count($categories_translation, 'link_category');
  if($counter) $counter->stop('wp_update_term_count_link_category');

  $step = 4;
  $warning = 0;

  $data .= '<br />'.sprintf(__('%d links found','zelist-importer'),count($links));
  $data .= '<br />'.sprintf(__('%d links known','zelist-importer'),$known);
  $data .= '<br />'.sprintf(__('%d links created','zelist-importer'),$added);
  foreach($link_stati as $status => $count) $data .= "<br />$status: $count";
  $data .= '<br />'.sprintf(__('%d users created','zelist-importer'),$users_created);

  // uncomment for debug
  //if(count($log)) $data .= '<br />'.implode('<br />',$log);
  //if($counter) $data .= $counter->show(0);

  $r = compact('warning','data','step','inputs');
  echo json_encode($r);
  die();
}
add_action( 'wp_ajax_import_freeglobes_links','zelist_import_freeglobes_links');

function zelist_import_freeglobes_tags() {
  check_ajax_referer( 'import' );
  header('Content-type: text/json');
  include(ZELIST_IMPORT_PATH.'/counter.class.php');
  $counter = new counter();

  if($counter) $counter->start('preparation');

  $data = '';
  $warning = 1;
  $step = 0;
  $inputs = array();

  parse_str($_POST['static_settings'],$settings);
  extract($settings,EXTR_OVERWRITE);
  if(!$static_prefix) {
    $data = __('Missing_prefix','zelist-importer');
    $r = compact('warning','data');	echo json_encode($r);	die();
  }

  global $wpdb;
  $tags_table = $static_prefix.'tag';
  $l2t_table = $static_prefix.'link2tag';

  $tags = $wpdb->get_results("SELECT t.tag, l2t.lid, l2t.tid FROM $tags_table t JOIN $l2t_table l2t ON l2t.tid = t.id");
  $links_translation = get_option('zelist_freeglobes_links_translation');


  if(!$tags) {
    $data = __('SQL Error','zelist-importer');
    $r = compact('warning','data');	echo json_encode($r);	die();
  }


  // existing
  $all_tags = array();
  $results = $wpdb->get_results("SELECT $wpdb->terms.name, $wpdb->term_taxonomy.term_taxonomy_id FROM $wpdb->terms "
  ."JOIN $wpdb->term_taxonomy ON $wpdb->term_taxonomy.term_id = $wpdb->terms.term_id "
  ."WHERE $wpdb->term_taxonomy.taxonomy = 'link_tag'");
  if($results) foreach($results as $result) {
    $all_tags[$result->name] = $result->term_taxonomy_id;
  }


  $link_tags = array();


  $counter = 0;
  $created = 0;
  if($counter) $counter->stop('preparation');


  if($counter) $counter->start('first_loop');
  foreach($tags as $tag) {
    $link_id = $links_translation[$tag->lid];
    if(!$link_id) $data .= sprintf(__('Missing link : %s','zelist-importer'),$tag->lid);
    if(!isset($link_tags[$link_id])) $link_tags[$link_id] = array();
    $tags = zelist_import_clean_tags($tag->tag);

    foreach($tags as $tag) {
      if(strlen($tag) < 3) continue;

      if(!isset($all_tags[$tag])) {
        if ( !$term_info = is_term($tag, 'link_tag') ){
          if($counter) $counter->start('wp_insert_term');
          $term_info = wp_insert_term($tag, 'link_tag');
          if($counter) $counter->stop('wp_insert_term');
        }

        $all_tags[$tag] = $term_info['term_taxonomy_id'];
      }
      $link_tags[$link_id][] = $all_tags[$tag];
    }
    if($counter && $counter->reaching_time_limit()) {
      $data .= __('Time limit reached','zelist-importer');
      break;
    }
  }
  if($counter) $counter->stop('first_loop');


  if($counter) $counter->start('linking_loop');
  // linking tags
  foreach($link_tags as $link_id => $tags_tt_ids) {
    if($counter && $counter->reaching_time_limit()) {
      $data .= __('Time limit reached','zelist-importer');
      break;
    }

    $tags_tt_ids = array_unique($tags_tt_ids);

    // too slow
    // $error = wp_set_object_terms($link_id,$tags_tt_ids,'link_tag');

    foreach ( $tags_tt_ids as $tt_id ) {
      $values[] = $wpdb->prepare( "(%d, %d)", $link_id, $tt_id);
    }
    if ( $values )  {
      $query = "INSERT IGNORE INTO $wpdb->term_relationships (object_id, term_taxonomy_id) VALUES " . join(',', $values);
      $wpdb->query($query);
      //$log[] = $query;

    }

  }
  if($counter) $counter->start('linking_loop');

  if($counter) $counter->start('wp_update_term_count_link_tag');
  $tags =  $wpdb->get_col("SELECT term_taxonomy_id FROM $wpdb->term_taxonomy WHERE taxonomy = 'link_tag'");
  wp_update_term_count($tags, 'link_tag');
  if($counter) $counter->stop('wp_update_term_count_link_tag');

  $warning = 0;
  $step = 5;
  $data .= sprintf(__('%d tags associations found for %d links','zelist-importer'),count($tags),count($link_tags));
  $data .= '<br />';
  $data .= __('These counts may be wrong if you had to click several times','zelist-importer');
  // uncomment for debug
  //if($log) $data .= '<br />'.implode('<br />',$log);
  //if($counter) $data .= $counter->show(0);

  // store the fact we have an heritage to deal with
  $heritage = get_option('zelist_heritage');
  if(!$heritage) $heritage = array();
  $heritage['freeglobes'] = true;
  update_option('zelist_heritage',$heritage);

  $r = compact('warning','data','step','inputs');
  echo json_encode($r);
  die();
}
add_action( 'wp_ajax_import_freeglobes_tags','zelist_import_freeglobes_tags');


function zelist_import_clean_tags($tag) {
  $explode = explode(' ',$tag);
  if(count($explode) < 3) return array(strtolower($tag));
  $tags = array();
  foreach($explode as $stem) {
    if(strlen($stem) > 3) $tags[] = trim(strtolower($stem));
  }
  return $tags;
}

