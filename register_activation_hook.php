<?php
/**
 *
 *
 * Created by PhpStorm.
 * Author: Eyeta Co.,Ltd.(http://www.eyeta.jp)
 * 
 */
namespace meta_accelerator;


function meta_accelerator_activate() {
	// プラグイン初期化
	\update_option("meta-accelerator_batch_count", 50);

	\update_option("meta-accelerator_cols", (array()));
	\update_option("meta-accelerator_cols_cnt", (array()));
	\update_option("meta-accelerator_index", (array()));


}


function meta_accelerator_deactivate() {
	// プラグイン削除
}
