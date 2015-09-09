<?php
/*
Plugin Name: Meta Accelerator
Description: meta query speed up accelerator
Version: 0.7
Plugin URI: http://www.eyeta.jp/archives/1012
Author: Eyeta Co.,Ltd.
Author URI: http://www.eyeta.jp/
License: GPLv2 or later
Text Domain: meta-accelerator
Domain Path: /languages/
*/
namespace meta_accelerator;

/*  Copyright 2014- Yuichiro ABE (email: y.abe at eyeta.jp)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/



require_once "register_activation_hook.php";
require_once "class/posttype.php";
require_once "functions.php";


register_activation_hook( __FILE__, '\meta_accelerator\meta_accelerator_activate' );
register_deactivation_hook( __FILE__, '\meta_accelerator\meta_accelerator_deactivate' );

class meta_accelerator {

	protected $_plugin_dirname;
	protected $_plugin_url;
	protected $_plugin_path;

	protected $_orderkey = "";
	protected $_query_posttype = "";

	public $no_accele = false;

	public function __construct() {
		// 初期パス等セット
		$this->init();

		// フックセット等
		add_action("init", array(&$this, "init_action"));

		// meta_query強化
		add_filter( 'get_meta_sql', array(&$this, "get_meta_sql"), 10, 6);
		add_filter( 'posts_orderby_request', array(&$this, "posts_orderby"), 10, 2);
		add_filter( 'posts_orderby', array(&$this, "posts_orderby"), 10, 2);

		// 登録時の処理を拡張
		add_action("updated_post_meta", array(&$this, "updated_post_meta"), 10, 4);
		add_action("added_post_meta", array(&$this, "added_post_meta"), 10, 4);
		add_action("deleted_post_meta", array(&$this, "deleted_post_meta"), 10, 4);
		add_action('save_post', array(&$this, "save_post"), 9999, 3);


		add_action('before_delete_post', array(&$this, "before_delete_post"), 9999, 1);
		add_action('after_delete_post', array(&$this, "after_delete_post"), 9999, 1);



		// 	do_action( "updated_{$meta_type}_meta", $meta_id, $object_id, $meta_key, $_meta_value );
		// 	do_action( "added_{$meta_type}_meta", $mid, $object_id, $meta_key, $_meta_value );
		// 	do_action( "deleted_{$meta_type}_meta", $meta_ids, $object_id, $meta_key, $_meta_value );

		// 管理画面追加
		add_action("admin_menu", array(&$this, "admin_menu"));

		// css, js
		add_action('admin_print_styles', array(&$this, 'head_css'));
		add_action('admin_print_scripts', array(&$this, "head_js"));

		// ajax
		add_action('wp_ajax_meta_accelerator_add', array(&$this, "add_posttype"));
		add_action('wp_ajax_meta_accelerator_remove', array(&$this, "remove_posttype"));

		add_action('wp_ajax_mktest', array(&$this, "mktest"));
	}


	function mktest() {

		$recs = 10000;

		$max= 100;
		for($i=0; $i<$recs; $i++) {
			$array_post = array(
				'post_type' => 'post',
				'post_title' => 'post_' . $i,
				'post_status' => 'publish',
					'post_content' => 'content_' . $i
			);
			$new_post_id = wp_insert_post($array_post);


			for($j=0; $j<$max ; $j++) {
				update_post_meta($new_post_id, 'metakey_' . $j, 'metaval_' . $j);
			}

		}

	}

	// remove_posttype($post_type)

	function remove_posttype() {
		if(!current_user_can("10")) {
			die(__('dont have permission.'));
		}

		if(Posttype::is_accelerated($_REQUEST["target"])) {
			$posttype = Posttype::get_instance($_REQUEST["target"]);
			$posttype->remove_posttype($_REQUEST["target"]);

			die($_REQUEST["target"] . \__("'s accelerator is deleted."));
		} else {
			die(\__("There is not accelerator"));
		}
	}

	/**
	 * 登録されたPOSTをacceleratorテーブルへ反映
	 *
	 * @param $meta_id
	 * @param $object_id
	 * @param $meta_key
	 * @param $_meta_value
	 */
	function save_post($post_ID, $post, $update) {
		if($post_ID == 0) {
			// postがまだ未設定のため何もしない
			return ;
		}
		// post_type確認
		if(Posttype::is_accelerated($post->post_type) && $update === false) {
			// 高速化対象
			Posttype::insert_accelerated_table($post_ID, $post->post_type);
		}
	}

	/**
	 * ポスト削除に合わせて該当レコード削除
	 *
	 * @param $postid
	 */
	function after_delete_post( $postid ) {
		if($postid == 0) {
			// postがまだ未設定のため何もしない
			return ;
		}
		// post_type確認
		if(Posttype::is_accelerated($this->delete_target_postype)) {
			// 高速化対象
			Posttype::delete_post_record($postid, $this->delete_target_postype);
		}

	}

	protected $delete_target_postype;
	function before_delete_post( $postid ) {
		if($postid == 0) {
			// postがまだ未設定のため何もしない
			return ;
		}
		$post = get_post($postid);
		$this->delete_target_postype = $post->post_type;

	}


	/**
	 * 登録されたメタ情報をacceleratorテーブルへ反映
	 *
	 * @param $meta_id
	 * @param $object_id
	 * @param $meta_key
	 * @param $_meta_value
	 */
	function deleted_post_meta($meta_ids, $object_id, $meta_key, $_meta_value) {
		// post_type確認
		$post = get_post($object_id);


		if(Posttype::is_accelerated($post->post_type)) {
			if(\get_option('meta-accelerator_no_underval') == 'no_underval' && 0 === strpos($meta_key, '_')) {
				// 何もしない
				return;
			}
			// 高速化対象
			$postmeta = get_post_meta($object_id, $meta_key);
			$posttype = Posttype::get_instance($post->post_type);
			$posttype->update_meta_table_data($meta_key, $postmeta, $post->post_type, $object_id);
		}
	}

	/**
	 * 登録されたメタ情報をacceleratorテーブルへ反映
	 *
	 * @param $meta_id
	 * @param $object_id
	 * @param $meta_key
	 * @param $_meta_value
	 */
	function added_post_meta($mid, $object_id, $meta_key, $_meta_value) {
		// post_type確認
		$post = get_post($object_id);
		if(Posttype::is_accelerated($post->post_type)) {
			if(\get_option('meta-accelerator_no_underval') == 'no_underval' && 0 === strpos($meta_key, '_')) {
				// 何もしない
				return;
			}
			// 高速化対象
			$postmeta = get_post_meta($object_id, $meta_key);
			$posttype = Posttype::get_instance($post->post_type);
			$posttype->update_meta_table_data($meta_key, $postmeta, $post->post_type, $object_id);
		}
	}

	/**
	 * 登録されたメタ情報をacceleratorテーブルへ反映
	 *
	 * @param $meta_id
	 * @param $object_id
	 * @param $meta_key
	 * @param $_meta_value
	 */
	function updated_post_meta($meta_id, $object_id, $meta_key, $_meta_value) {
		// post_type確認
		$post = get_post($object_id);
		meta_accelerator_log("updated_post_meta $meta_key $_meta_value");
		if(Posttype::is_accelerated($post->post_type)) {
			if(\get_option('meta-accelerator_no_underval') == 'no_underval' && 0 === strpos($meta_key, '_')) {
				// 何もしない
				return;
			}
			// 高速化対象
			$postmeta = get_post_meta($object_id, $meta_key);
			$posttype = Posttype::get_instance($post->post_type);
			$posttype->update_meta_table_data($meta_key, $postmeta, $post->post_type, $object_id);
		}
	}

	/**
	 * メタの場合の並替え強化
	 *
	 * @param $orderby
	 * @param $context
	 */
	function posts_orderby( $orderby, $context) {

		if($this->no_accele) {
			return $orderby;
		}
		global $wpdb;
		//meta_accelerator_log("order by : $orderby : $this->_orderkey : $this->_query_posttype");
		$meta_clauses = $context->meta_query->get_clauses();
		if ( ! empty( $meta_clauses ) ) {
			$primary_meta_query = reset( $meta_clauses );
			if(is_array($orderby)) {
				foreach($orderby as $key => $aorderby) {
					if(strpos($aorderby, "$wpdb->postmeta.meta_value") !== false) {
						// メタ並替え有り
						$obj_posttype = Posttype::get_instance($this->_query_posttype);
						$orderby[$key] = str_replace("$wpdb->postmeta.meta_value", $obj_posttype->get_tablename($this->_query_posttype) . "." . $obj_posttype->get_col_name($primary_meta_query['key']), $orderby);
					}
				}
			} else {
				if(strpos($orderby, "$wpdb->postmeta.meta_value") !== false) {
					// メタ並替え有り
					$obj_posttype = Posttype::get_instance($this->_query_posttype);
					$orderby = str_replace("$wpdb->postmeta.meta_value", $obj_posttype->get_tablename($this->_query_posttype) . "." . $obj_posttype->get_col_name($primary_meta_query['key']), $orderby);
				}
			}
			//meta_accelerator_log("order by 2 : $orderby: " . print_r($meta_clauses, true));
		}

		return $orderby;
	}

	/**
	 * メタクエリー強化
	 *
	 * @param $array_join_where
	 * @param $queries
	 * @param $type
	 * @param $primary_table
	 * @param $primary_id_column
	 * @param $context
	 */
	function get_meta_sql($array_join_where, $queries, $type, $primary_table, $primary_id_column, $context) {
		if($this->no_accele) {
			return $array_join_where;
		}

		meta_accelerator_log("get_meta_sql");//return $array_join_where;

		$meta_clauses = $context->meta_query->get_clauses();
		meta_accelerator_log("get_meta_sql 2 : meta_clauses: " . print_r($meta_clauses, true));

		global $wpdb;
		//  array( compact( 'join', 'where' ), $this->queries, $type, $primary_table, $primary_id_column, $context )
		// $join[0] は、meta_query配列で指定していない場合

		// post以外は対象外
		if($type != "post") {
			return $array_join_where;
		}
		/** post type 処理 */
		$q = $context->query_vars;
		$post_type = $q['post_type'];
		if ( $context->is_tax ) {
			if ( empty($post_type) ) {
				// Do a fully inclusive search for currently registered post types of queried taxonomies
				$post_type = array();
				$taxonomies = wp_list_pluck( $context->tax_query->queries, 'taxonomy' );
				foreach ( get_post_types( array( 'exclude_from_search' => false ) ) as $pt ) {
					$object_taxonomies = $pt === 'attachment' ? get_taxonomies_for_attachments() : get_object_taxonomies( $pt );
					if ( array_intersect( $taxonomies, $object_taxonomies ) )
						$post_type[] = $pt;
				}
				if ( ! $post_type )
					$post_type = 'any';
				elseif ( count( $post_type ) == 1 )
					$post_type = $post_type[0];
			}
		}
		$obj_posttype = "";
		if ( 'any' == $post_type ) {
			// 全てのpost_type
			return $array_join_where;
		} elseif ( !empty( $post_type ) && is_array( $post_type ) ) {
			// 配列で指定されている
			return $array_join_where;
		} elseif ( ! empty( $post_type ) ) {
			// 1ツだけ指定
			if(Posttype::is_accelerated($post_type)) {
				$obj_posttype = Posttype::get_instance($post_type);
			} else {
				return $array_join_where;
			}
		} elseif ( $context->is_attachment ) {
			// 添付ファイル
			$post_type = "attachment";
			if(Posttype::is_accelerated($post_type)) {
				$obj_posttype = Posttype::get_instance($post_type);
			} else {
				return $array_join_where;
			}
		} elseif ( $context->is_page ) {
			// 固定ページ
			$post_type = "page";
			if(Posttype::is_accelerated($post_type)) {
				$obj_posttype = Posttype::get_instance($post_type);
			} else {
				return $array_join_where;
			}
		} else {
			// その他
			$post_type = "post";
			if(Posttype::is_accelerated($post_type)) {
				$obj_posttype = Posttype::get_instance($post_type);
			} else {
				return $array_join_where;
			}
		}


		if($obj_posttype == "") {
			// meta_accelerator対象なし
			return $array_join_where;
		}

		$this->_query_posttype = $post_type;

		// メタテーブルを追加した場合に保持する配列
		$array_joined_post_type = array();
		$join = $array_join_where["join"];
		$where = $array_join_where["where"];

		//meta_accelerator_log("get_meta_sql : queries: " . print_r($queries, true));
		//meta_accelerator_log("get_meta_sql : type: " . print_r($type, true));

		$array_join_1 = explode( "\n", $join );
		$array_join = array();
		foreach($array_join_1 as $str_join_1) {
			$array_join_1 = explode( "INNER JOIN ", $str_join_1 );
			if(count($array_join_1) > 2) {
				// INNER JOINが2つ以上ある
				foreach($array_join_1 as $str_join) {
					if(trim($str_join) != '') {
						$array_join[] = "INNER JOIN " . $str_join;
					}
				}
			} else {
				$array_join_1 = explode( "LEFT JOIN ", $str_join_1 );
				if(count($array_join_1) > 2) {
					// LEFT JOINが2つ以上ある
					foreach($array_join_1 as $str_join) {
						if(trim($str_join) != '') {
							$array_join[] = "LEFT JOIN " . $str_join;
						}
					}
				} else {
					// joinは1つ
					$array_join[] = $str_join_1;
				}

			}
		}
		$array_where = explode( "\n", $where );
		//meta_accelerator_log("get_meta_sql : join: " . print_r($array_join, true));
		//meta_accelerator_log("get_meta_sql : where: " . print_r($array_where, true));

		// joinを整理
		$array_replace_aliases = array();
		$array_left_key = array();
		foreach($array_join as $key => $current_join) {
			// INNER JOIN wp_postmeta AS ? の場合?を抽出してこのレコードを消す
			if(strpos($current_join, "INNER JOIN $wpdb->postmeta AS") !== false) {
				// postmetaテーブルのJOIN
				//$len = strpos($current_join, " ON ") - strlen("INNER JOIN $wpdb->postmeta AS ");
				//$array_replace_aliases[substr($current_join, strlen("INNER JOIN $wpdb->postmeta AS "), $len)] = "inner";
				unset($array_join[$key]);
			} elseif(strpos($current_join, "INNER JOIN $wpdb->postmeta") !== false) {
					// postmetaテーブルのJOIN
					//
					//$array_replace_aliases[$wpdb->postmeta] = "inner";
					unset($array_join[$key]);
			} elseif(strpos($current_join, "LEFT JOIN $wpdb->postmeta AS") !== false) {
				//$len = strpos($current_join, " ON ") - strlen("LEFT JOIN $wpdb->postmeta AS ");
				//$array_replace_aliases[substr($current_join, strlen("LEFT JOIN $wpdb->postmeta AS "), $len)] = "left";
				//$str_tmp = substr($current_join, 0, strrpos($current_join, "'"));
				//$str_tmp = substr($str_tmp, strrpos($str_tmp, "'")+1);
				//$array_left_key[substr($current_join, strlen("LEFT JOIN $wpdb->postmeta AS "), $len)] =$str_tmp;
				unset($array_join[$key]);
			} elseif(strpos($current_join, "LEFT JOIN $wpdb->postmeta") !== false) {
				//$len = strpos($current_join, " ON ") - strlen("LEFT JOIN $wpdb->postmeta AS ");
				//$array_replace_aliases[substr($current_join, strlen("LEFT JOIN $wpdb->postmeta AS "), $len)] = "left";
				//$str_tmp = substr($current_join, 0, strrpos($current_join, "'"));
				//$str_tmp = substr($str_tmp, strrpos($str_tmp, "'")+1);
				//$array_left_key[substr($current_join, strlen("LEFT JOIN $wpdb->postmeta AS "), $len)] =$str_tmp;
				unset($array_join[$key]);
			}
		}

		//meta_accelerator_log('left_key' . print_r($array_left_key, true));

		// joinを追加
		$array_lefts = array();
		$array_inner = array();
		foreach($meta_clauses as $join_key => $clause) {
			if('NOT EXISTS' == $clause['compare']) {
				$array_lefts[] = $join_key;
			} else {
				$array_inner[] = $join_key;
			}
		}
		if(array() != $array_inner) {
			$array_join[] = "INNER JOIN " . $obj_posttype->get_tablename($post_type) . " ON ($primary_table.$primary_id_column = " . $obj_posttype->get_tablename($post_type) . ".post_id) ";
		}
		foreach($array_lefts as $clause['alias']) {
			$array_join[] = "LEFT JOIN " . $obj_posttype->get_tablename($post_type) . " as " . $clause['alias'] . " ON ($primary_table.$primary_id_column = " . $obj_posttype->get_tablename($post_type) . ".post_id) ";
		}
		//meta_accelerator_log("get_meta_sql : array_inner: " . print_r($array_inner, true));

		// whereを整理
		foreach($array_where as $key => $current_where) {
			foreach($array_inner as $join_key) {
				//meta_accelerator_log("get_meta_sql : join_key: $join_key / current_where: $current_where");
				if(mb_strpos($current_where, $meta_clauses[$join_key]['alias'] . ".meta_key = '" . $meta_clauses[$join_key]['key'] . "' AND") !== false) {
					//meta_accelerator_log("get_meta_sql : " . $meta_clauses[$join_key]['alias'] . ".meta_key = '" . $meta_clauses[$join_key]['key'] . "' AND");
					$current_where = mb_ereg_replace($meta_clauses[$join_key]['alias'] . ".meta_key = '" . $meta_clauses[$join_key]['key'] . "' AND" , "", $current_where);
					//meta_accelerator_log("get_meta_sql : $array_where[$key]");
				}
				if(mb_strpos($current_where, $meta_clauses[$join_key]['alias'] . ".meta_key") !== false) {
					//meta_accelerator_log("get_meta_sql : " . $meta_clauses[$join_key]['alias'] . ".meta_key = '" . $meta_clauses[$join_key]['key'] . "'");
					$current_where = mb_ereg_replace($meta_clauses[$join_key]['alias'] . ".meta_key = '" . $meta_clauses[$join_key]['key'] . "'" , " 1=1 ", $current_where);
					//meta_accelerator_log("get_meta_sql : $array_where[$key]");
				}
				if(mb_strpos($current_where, $meta_clauses[$join_key]['alias'] . ".meta_value") !== false) {
					$current_where = mb_ereg_replace($meta_clauses[$join_key]['alias'] . ".meta_value" , $obj_posttype->get_tablename($post_type) . "." . $obj_posttype->get_col_name($meta_clauses[$join_key]['key']), $current_where);
				}
			}

			foreach($array_lefts as $join_key) {
				if(mb_strpos($current_where, $meta_clauses[$join_key]['alias'] . ".meta_key AND") !== false) {
					$current_where = mb_ereg_replace($meta_clauses[$join_key]['alias'] . "\.meta_key = '" . $meta_clauses[$join_key]['key'] . "' AND" , "", $current_where);
				}
				if(mb_strpos($current_where, $meta_clauses[$join_key]['alias'] . ".meta_key") !== false) {
					$current_where = mb_ereg_replace($meta_clauses[$join_key]['alias'] . "\.meta_key = '" . $meta_clauses[$join_key]['key'] . "'" , " 1=1 ", $current_where);
				}
				if(mb_strpos($current_where, $meta_clauses[$join_key]['alias'] . ".meta_value") !== false) {
					$current_where = mb_ereg_replace($meta_clauses[$join_key]['alias'] . "\.meta_value" , $obj_posttype->get_tablename($post_type) . "." . $obj_posttype->get_col_name($meta_clauses[$join_key]['key']), $current_where);
				}
				if(mb_strpos($current_where, $meta_clauses[$join_key]['alias'] . ".post_id IS NULL") !== false) {
					if( ( $col_name = $obj_posttype->get_col_name($meta_clauses[$join_key]['key'], '', true) ) != '' ) {
						$current_where = mb_ereg_replace($meta_clauses[$join_key]['alias'] . "\.post_id IS NULL" , $obj_posttype->get_tablename($post_type) . "." . $col_name . " IS NULL", $current_where);
					} else {
						// 列が登録されていないということは全投稿でこのフィールドは空
					}
				}
			}

			$array_where[$key] = $current_where;

		}
		//meta_accelerator_log("get_meta_sql : array_where: " . print_r($array_where, true));

		//meta_accelerator_log("A" . print_r($array_join, true));
		//meta_accelerator_log(print_r($array_where, true));

		$join = implode( "\n", $array_join );
		$where = implode( "\n", $array_where );



		return array("join" => $join, "where" => $where);

	}

	/**
	 * ポストタイプを対象に追加する。
	 *
	 */
	function add_posttype() {
		if(!current_user_can("10")) {
			die(__('dont have permission.'));
		}

		try {
			if($_REQUEST["batch_paged"]) {
				$cls_posttype = Posttype::get_instance($_REQUEST["target"]);

				// 対象テーブル内を一度クリアして再構築
				$build_result = $cls_posttype->build();

				// 設定類保存
				$cls_posttype->save_options();
			} else {
				$cls_posttype = Posttype::get_instance($_REQUEST["target"]);

				// テーブル追加
				$cls_posttype->add_post_type();

				// meta_key抽出
				$array_keys = $cls_posttype->get_meta_keys();

				// add columｎ
				$cls_posttype->add_cols($array_keys);

				// 対象テーブル内を一度クリアして再構築
				$build_result = $cls_posttype->build();

				// 設定類保存
				$cls_posttype->save_options();
			}

			//die( json_encode(array("rsl" => true, "next" => "none")));
			// array("next" => $_REQUEST["batch_paged"] +1, "total" => $posts->max_num_page)
			if($build_result["next"] != "none") {
				die( json_encode(array("rsl" => true, "next" => $build_result["next"], "total" => $build_result["total"], "target" => $_REQUEST["target"])));
			} else {
				die( json_encode(array("rsl" => true, "next" => "none", "target" => $_REQUEST["target"])));
			}

		} catch(\Exception $e) {
			die( json_encode(array("rsl" => false, "msg" => $e->getMessage(), "target" => $_REQUEST["target"])));
		}

	}

	/*
	 * 管理画面追加
	 */
	function admin_menu () {
		// RAINS処理関連ページ
		require_once "meta_accelerator_admin.php";

		add_submenu_page( 'options-general.php', \__('meta speedup'), \__('meta speedup'), 10, 'meta_accelerator_admin.php', array(&$this, 'meta_accelerator_admin'));
	}

	function meta_accelerator_admin() {
		meta_accelerator_admin();
	}

	/**
	 * 管理画面CSS追加
	 */
	function head_css () {
		if(isset($_REQUEST["page"]) && $_REQUEST["page"] == "meta_accelerator_admin.php") {
			wp_enqueue_style('wp-jquery-ui-dialog');
			wp_enqueue_style('meta_accelerator_jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.4 /themes/smoothness/jquery-ui.css');
			wp_enqueue_style( "meta_accelerator_css", $this->get_plugin_url() . '/css/style.css');
		}
	}

	/*
	 * 管理画面JS追加
	 */
	function head_js () {
		if(isset($_REQUEST["page"]) && $_REQUEST["page"] == "meta_accelerator_admin.php") {
			wp_enqueue_script( "jquery-ui-tabs");
			wp_enqueue_script( "jquery-ui-dialog");
			wp_enqueue_script( "meta-accelerator_js", $this->get_plugin_url() . '/js/scripts.js', array("jquery"));
		}
	}

	/*
	 * initフック
	 */
	function init_action() {
		// 各種フックセット



	}

	/*
	 * 初期化
	**/
	function init() {

		$array_tmp = explode(DIRECTORY_SEPARATOR, dirname(__FILE__));
		$this->_plugin_dirname = $array_tmp[count($array_tmp)-1];
		$this->_plugin_url = '/'. PLUGINDIR . '/' . $this->_plugin_dirname;
		$this->_plugin_path = dirname(__FILE__);

		$this->load_textdomain();
	}

	function get_plugin_url() {
		return $this->_plugin_url;
	}

	function get_plugin_dirname() {
		return $this->_plugin_dirname;
	}

	function get_plugin_path() {
		return $this->_plugin_path;
	}

	function load_textdomain( $locale = null ) {
		global $l10n;

		$domain = 'meta-accelerator';

		if ( \get_locale() == $locale ) {
			$locale = null;
		}

		if ( empty( $locale ) ) {
			if ( \is_textdomain_loaded( $domain ) ) {
				return true;
			} else {
				return \load_plugin_textdomain( $domain, false, $domain . '/languages' );
			}
		} else {
			$mo_orig = $l10n[$domain];
			unload_textdomain( $domain );

			$mofile = $domain . '-' . $locale . '.mo';
			$path = WP_PLUGIN_DIR . '/' . $domain . '/languages';

			if ( $loaded = \load_textdomain( $domain, $path . '/'. $mofile ) ) {
				return $loaded;
			} else {
				$mofile = WP_LANG_DIR . '/plugins/' . $mofile;
				return \load_textdomain( $domain, $mofile );
			}

			$l10n[$domain] = $mo_orig;
		}

		return false;
	}


}

/*
 * エラー関数
 */
if(!function_exists("meta_accelerator_log")) {
	function meta_accelerator_log($msg, $level = "DEBUG") {
		$level_array = array(
			"DEBUG" => 0,
			"DETAIL" => 1,
			"INFO" => 2,
			"ERROR" => 3
		);

		if($level_array["ERROR"] <= $level_array[$level]) {
			if(mb_strlen($msg)< 800) {
				error_log($_SERVER["SERVER_NAME"] . " : " . $level . " : " . $msg);
			} else {
				$size = mb_strlen($msg);
				for($i=0; $i < $size; $i+=800) {
					error_log($_SERVER["SERVER_NAME"] . " : " . $level . " : " . mb_substr($msg, $i, 800));
				}
			}
		}

		//print_r($msg);


	}
}


global $meta_accelerator;
$meta_accelerator = new meta_accelerator();
