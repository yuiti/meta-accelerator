<?php
namespace meta_accelerator;
/**
 *
 *
 * Created by PhpStorm.
 * Author: Eyeta Co.,Ltd.(http://www.eyeta.jp)
 * 
 */

class Posttype {

	/**
	 * @var 対象ポストタイプ
	 */
	protected $post_type;

	protected $array_cols;

	protected $array_cols_cnt;

	/*
	 * インスタンス配列
	 */
	private static $instance;

	public static function get_instance($post_type) {
		if ( empty( self::$instance ) ) {
			self::$instance = array();
		}

		if(isset(self::$instance[$post_type])) {
			return self::$instance[$post_type];
		}

		self::$instance[$post_type] = new self($post_type);
		return self::$instance[$post_type];
	}

	/**
	 * コンストラクタ
	 *
	 * @param string $post_type 対象のポストタイプ
	 */
	function __construct($post_type = "post") {
		try {
			// post_typeの存在確認
			if(post_type_exists($post_type)) {
				$this->post_type = $post_type;
			} else {
				throw new \Exception(\__("post type: ") . $post_type . \__("is not found."));
			}

			$this->array_cols = \get_option("meta-accelerator_cols");
			$this->array_cols_cnt = \get_option("meta-accelerator_cols_cnt");

			//\update_option("meta-accelerator_index", array());
			//$target_posttypes["bukken"] = array("status" => "target");
			//\update_option("meta-accelerator_posttypes", $target_posttypes);

		} catch(\Exception $e) {
			throw $e;
		}

	}

	static public function is_accelerated($post_type) {
		$target_posttypes = \get_option("meta-accelerator_posttypes");
		//echo $target_posttypes[$post_type];
		if(isset( $target_posttypes[$post_type] ) ) {
			//echo "true";
			return true;
		} else {
			return false;
		}
	}

	function remove_posttype($post_type) {
		$target_posttypes = \get_option("meta-accelerator_posttypes");

		unset($target_posttypes[$post_type]);
		\update_option("meta-accelerator_posttypes", $target_posttypes);

		unset($this->array_cols[$post_type]);
		unset($this->array_cols_cnt[$post_type]);
		$this->save_options();

	}

	function save_options() {
		\update_option("meta-accelerator_cols", $this->array_cols);
		\update_option("meta-accelerator_cols_cnt", $this->array_cols_cnt);

	}

	/**
	 * post type用テーブルの生成
	 */
	function add_post_type() {
		meta_accelerator_log("add_post_type");
		try {
			global $wpdb;

			$target_posttypes = \get_option("meta-accelerator_posttypes");
			if(isset($target_posttypes[$this->post_type])) {
				// 登録がすでにあるため不要
				//throw new \Exception("ポストタイプ: " . $this->post_type . "はすでに対象です。");
				// 対象でもテーブルが無ければ作成されるのでこれでOK
				unset($target_posttypes[$this->post_type]);
				\update_option("meta-accelerator_posttypes", $target_posttypes);
			}

			// テーブル作成
			$this->create_meta_table($this->post_type);

			// 対象としてオプションへ登録 step1: create table
			$target_posttypes[$this->post_type] = array("status" => "target");
			\update_option("meta-accelerator_posttypes", $target_posttypes);

		} catch(\Exception $e) {
			throw $e;
		}

	}

	function build($post_type = "") {
		try {
			if($post_type == "") {
				$post_type = $this->post_type;
			}


			// 対象ポストタイプを全取得しループしながら対象のメタ配列をテーブルに保存してゆく

			if($_REQUEST["batch_paged"]) {
				$target_page = $_REQUEST["batch_paged"];
				$posts = new \WP_Query(array(
					"post_type" => $post_type,
					"posts_per_page" => intVal(\get_option("meta-accelerator_batch_count")),
					"paged" => $_REQUEST["batch_paged"]
				));
			} else {
				// 対象テーブルクリア
				$this->trancate_meta_table($post_type);
				$target_page = 1;
				$posts = new \WP_Query(array(
					"post_type" => $post_type,
					"posts_per_page" => intVal(\get_option("meta-accelerator_batch_count"))
				));
			}

			$this->update_meta_table_datas($posts);

			//$array_meta_keys = $this->get_meta_keys_from_posts($posts);

			if($posts->max_num_pages > 1 && $posts->max_num_pages > $target_page) {
				// 複数ページ有り、次ページ有り
				return array("next" => $target_page +1, "total" => $posts->max_num_pages);
			}

			return array("next" => "none");

		} catch(\Exception $e) {
			throw $e;
		}
	}

	function update_meta_table_datas($posts, $post_type= "") {
		try {

			if($post_type == "") {
				$post_type = $this->post_type;
			}

			// ポストでループ
			if($posts->have_posts()) {
				while($posts->have_posts()) {
					$posts->the_post();

					$array_metas = \get_post_custom(get_the_ID());
					foreach($array_metas as $meta_key => $array_meta) {
						if(\get_option('meta-accelerator_no_underval') == 'no_underval' && 0 === strpos($meta_key, '_')) {
							// 何もしない
						} else {
							if(Posttype::update_meta_table_data($meta_key, $array_meta, $post_type, get_the_ID()) == false) {
								throw new \Exception("update_meta_table_data error: " . $meta_key . ":" . print_r($array_meta, true) . " : post_type: " . $post_type . " : post_id: " . get_the_ID());
							}
						}
					}
				}
			}
		} catch(\Exception $e) {
			throw $e;
		}
	}

	/**
	 * メタデータ保存
	 * 　プラグイン外からも呼ばれるため、throwしない
	 *
	 * @param $val
	 * @param $key
	 * @param bool $append
	 * @param string $post_type
	 */
	function update_meta_table_data($key, $val, $post_type, $post_id = "") {
		//meta_accelerator_log("update_meta_table_data $post_id $key " . print_r($val, true));
		//meta_accelerator_log("update_meta_table_data: " . $key . ":" . print_r($val, true) . " : post_type: " . $post_type);
		try {
			global $wpdb;

			if(Posttype::is_accelerated($post_type) == false) {
				// 対象外
				return false;
			}

			if($post_id == "") {
				$post_id = get_the_ID();
				if($post_id == "") {
					return false;
				}
			}

			if($post_id == "") {
				// 対象post_idなし
				return false;
			}

			// フィールドチェック
			if($this->has_col($key) === false) {
				// フィールド追加
				$this->add_cols(array($key => ""));
			}

			// 値が配列かどうかをチェック 配列の場合、文字列を,でつないでセット
			if(is_array($val)) {
				$insert_val = "";
				if(count($val) == 1) {
					$insert_val = array_pop($val);
				} else {
					foreach($val as $val2) {
						$insert_val .= "\n\n" . $val2;
					}
				}
			} else {
				$insert_val = $val;
			}

			Posttype::insert_accelerated_table($post_id, $post_type);

			if($insert_val == "") {
				$sql = $wpdb->prepare("update `" . Posttype::get_tablename($post_type) . "` set " . $this->get_col_name($key) . "='' where post_id = %d", $post_id);
			} else {
				$sql = $wpdb->prepare("update `" . Posttype::get_tablename($post_type) . "` set " . $this->get_col_name($key) . "=%s where post_id = %d", $insert_val, $post_id);
			}
			$wpdb->query($sql);

			meta_accelerator_log("updated_meta_table_data " . $this->get_col_name($key) . " " . $insert_val);


			return true;

		} catch(\Exception $e) {
			return false;
		}

	}

	/**
	 * レコード追加
	 *
	 * @param $post_id
	 * @param $post_type
	 */
	static function insert_accelerated_table($post_id, $post_type) {
		global $wpdb;

			// 既存レコードの有無をチェック
			$sql = $wpdb->prepare("select * from `" . Posttype::get_tablename($post_type) . "` where post_id = %d", $post_id);
			$row = $wpdb->get_row($sql);

			if($row == null) {
				$sql = $wpdb->prepare("insert into `" . Posttype::get_tablename($post_type) . "` (post_id) values(%d);", $post_id);
				$wpdb->query($sql);
			}

	}

	/**
	 * レコード削除
	 *
	 * @param $postid
	 * @param $post_type
	 */
	static function delete_post_record($postid, $post_type) {
		global $wpdb;
		if(Posttype::is_accelerated($post_type)) {
			$sql = $wpdb->prepare("delete from `" . Posttype::get_tablename($post_type) . "` where post_id = %d", $postid);
			$wpdb->query($sql);
		}
	}

	/**
	 * 対象ポストタイプのテーブルをtruncate
	 *
	 * @param string $post_type
	 * @throws \Exception
	 */
	function trancate_meta_table($post_type = "") {
		try {
			meta_accelerator_log("trancate_meta_table");
			global $wpdb;

			if($post_type == "") {
				$post_type = $this->post_type;
			}

			// 対象テーブルクリア
			$sql = "truncate table `" . Posttype::get_tablename($post_type) . "`;";
			//meta_accelerator_log($sql);
			$wpdb->query($sql);

		} catch(\Exception $e) {
			throw $e;
		}
	}

	/**
	 * テーブルを作成
	 *
	 * @param $post_type
	 * @return bool
	 * @throws \Exception
	 */
	function create_meta_table($post_type) {
		meta_accelerator_log("create_meta_table");

		try {
			global $wpdb;

			// テーブルの存在を確認
			if($this->table_exists($post_type)) {
				// テーブル有り一度削除
				$sql = "drop table `" . Posttype::get_tablename($post_type) . "`;";
				//meta_accelerator_log($sql);
				$wpdb->query($sql);

				// フィールド配列削除
				unset($this->array_cols[$post_type]);
				unset($this->array_cols_cnt[$post_type]);
				$this->save_options();

			}

			// 対象テーブル作成
			$sql = "create table `" . Posttype::get_tablename($post_type) . "` (post_id bigint primary key) CHARACTER SET 'utf8';";
			//meta_accelerator_log($sql);
			$wpdb->query($sql);

			return true;

		} catch(\Exception $e) {
			throw $e;
		}

	}

	/**
	 * ポストタイプテーブルにメタフィールドを追加する。
	 * テーブルはある前提
	 *
	 * @param $post_type
	 * @param $array_keys
	 */
	function add_cols($array_keys, $post_type = "") {
		meta_accelerator_log("add_cols");
		try {
			if($post_type == "") {
				$post_type = $this->post_type;
			}

			global $wpdb;

			foreach($array_keys as $key => $val) {
				//meta_accelerator_log($key);
				// カラムの存在確認
				$sql = "show columns from `" . Posttype::get_tablename($post_type) . "` where Field='" . $this->get_col_name($key) . "'";
				//meta_accelerator_log($sql);
				$row = $wpdb->get_row($sql);
				if($row) {
					// すでに存在している
					continue;
				} else {
					// カラム追加
					meta_accelerator_log("add_col: $post_type $key " . $this->get_col_name($key) );
					$sql = "alter table `" . Posttype::get_tablename($post_type) . "` add " . $this->get_col_name($key) . " longtext;";
					$wpdb->query($sql);
				}
			}

		} catch(\Exception $e) {
			throw $e;
		}
	}


	/**
	 * ポストタイプテーブルにメタフィールドを追加する。
	 * テーブルはある前提
	 *
	 * @param $post_type
	 * @param $array_keys
	 */
	function add_col($key, $col_name, $post_type) {
		meta_accelerator_log("add_col");
		try {

			global $wpdb;

			//meta_accelerator_log($key);
			// カラムの存在確認
			$sql = "show columns from `" . Posttype::get_tablename($post_type) . "` where Field='" . $col_name . "'";
			//meta_accelerator_log($sql);
			$row = $wpdb->get_row($sql);
			if($row) {
				// すでに存在している
				return true;
			} else {
				meta_accelerator_log("add_col $key $col_name");
				// カラム追加
				meta_accelerator_log("add_col: $post_type $key " . $col_name );
				$sql = "alter table `" . Posttype::get_tablename($post_type) . "` add " . $col_name . " longtext;";
				$wpdb->query($sql);
			}

		} catch(\Exception $e) {
			throw $e;
		}
	}


	/**
	 * meta_keyからフィールド名を生成する。
	 * マルチバイトを考慮しエンコードする。
	 *
	 * @param $key
	 */
	function get_col_name($key, $post_type = "", $is_null = false) {
		if($post_type == "") {
			$post_type = $this->post_type;
		}

		if(isset($this->array_cols[$post_type]) && isset($this->array_cols[$post_type][$key])) {
			// すでにある
			//$this->add_col($key, "col_" . $this->array_cols[$post_type][$key], $post_type);
		} elseif($this->array_cols[$post_type]){

			if($is_null) {
				// 列がない
				return '';
			}
			if(\get_option('meta-accelerator_no_underval') == 'no_underval' && 0 === strpos($key, '_')) {
				// 何もしない
				return '';
			}
			// キーを生成して処理
			$this->array_cols_cnt[$post_type]++;
			$this->array_cols[$post_type][$key] = $this->array_cols_cnt[$post_type];
			$this->add_col($key, "col_" . $this->array_cols[$post_type][$key], $post_type);
			$this->save_options();
		} else {
			if($is_null) {
				// 列がない
				return '';
			}
			if(\get_option('meta-accelerator_no_underval') == 'no_underval' && 0 === strpos($key, '_')) {
				// 何もしない
				return '';
			}
			// キーを生成して処理
			$this->array_cols_cnt[$post_type] = 1;
			$this->array_cols[$post_type] = array();
			$this->array_cols[$post_type][$key] = $this->array_cols_cnt[$post_type];
			$this->add_col($key, "col_" . $this->array_cols[$post_type][$key], $post_type);
			$this->save_options();
		}

		return "col_" . $this->array_cols[$post_type][$key];
	}

	/*
	 * 指定のカスタム投稿タイプのフィールドリスト
	 */
	function get_cols($post_type = "") {
		if($post_type == "") {
			$post_type = $this->post_type;
		}

		if(isset($this->array_cols[$post_type])) {
			return $this->array_cols[$post_type];
		} else {
			return array();
		}

	}

	/**
	 * フィールドが生成されているかをチェック
	 *
	 * @param $key
	 * @param string $post_type
	 * @return bool
	 */
	function has_col($key, $post_type = "") {
		if($post_type == "") {
			$post_type = $this->post_type;
		}

		if(isset($this->array_cols[$post_type]) && isset($this->array_cols[$post_type][$key])) {
			// すでにある
			return true;
		} else {
			return false;
		}
	}


	/**
	 * テーブルの存在確認
	 *
	 * @param $post_type
	 * @return bool
	 */
	function table_exists($post_type) {

		global $wpdb;

		$sql = "SHOW TABLES FROM " . DB_NAME . ";";
		//meta_accelerator_log($sql);
		$rows = $wpdb->get_results($sql, ARRAY_N);
		$rsl = false;
		foreach($rows as $row) {
			if($row[0] == Posttype::get_tablename($post_type)) {
				$rsl = true;
			}
		}

		return $rsl;

	}




	/**
	 * メタキーの配列を取得
	 *
	 * @param string $post_type
	 * @return array
	 */
	function get_meta_keys($post_type = "") {
		meta_accelerator_log("get_meta_keys");

		if($post_type == "") {
			$post_type = $this->post_type;
		}

		// 対象ポストタイプを全取得しループしながら対象のメタ配列を取得する。
		$posts = new \WP_Query(array(
			"post_type" => $post_type,
			"posts_per_page" => intVal(\get_option("meta-accelerator_batch_count"))
		));

		$array_meta_keys = $this->get_meta_keys_from_posts($posts);

		if($posts->max_num_pag > 1 ) {
			// 複数ページ有り
			for($page = 0; $page < $posts->max_num_pag; $page++) {
				$posts = new \WP_Query(array(
					"post_type" => $post_type,
					"posts_per_page" => intVal(\get_option("meta-accelerator_batch_count")),
					"paged" => $page
				));

				$array_meta_keys = $this->get_meta_keys_from_posts($posts, $array_meta_keys);
			}
		}

		return $array_meta_keys;
	}

	/**
	 * WP_Queryオブジェクトからメタキーの配列を返す。
	 *
	 * @param $posts
	 */
	function get_meta_keys_from_posts($posts, $array_meta_keys = array()) {
		meta_accelerator_log("get_meta_keys");

		if($posts->have_posts()) {
			while($posts->have_posts()) {
				$posts->the_post();
				$array_keys_temp = get_post_custom_keys();
				if(is_array($array_keys_temp)) {
					foreach($array_keys_temp as $key) {
						if(!isset($array_meta_keys[$key])) {
							$array_meta_keys[$key] = true;
						}
					}
				}
			}
		}

		return $array_meta_keys;
	}

	/**
	 * メタ用テーブル抽出
	 *
	 * @param string $post_type
	 * @return string
	 */
	function get_tablename($post_type) {
		global $table_prefix;

		return $table_prefix . "meta_accelerator_" . $post_type;
	}


	function get_array_cols() {
		return $this->array_cols;
	}
}