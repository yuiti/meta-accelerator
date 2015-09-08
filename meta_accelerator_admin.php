<?php
/**
 * プラグイン管理画面
 *
 * Created by PhpStorm.
 * Author: Eyeta Co.,Ltd.(http://www.eyeta.jp)
 * 
 */
namespace meta_accelerator;

function meta_accelerator_admin() {


	if(isset($_REQUEST['opt-save']) && 'opt-save' == $_REQUEST['opt-save']) {

		if(isset($_REQUEST['no_underval']) && 'no_underval' == $_REQUEST['no_underval']) {
			\update_option("meta-accelerator_no_underval", 'no_underval');
		} else {
			\update_option("meta-accelerator_no_underval", '');
		}
	}

	$no_underval = \get_option('meta-accelerator_no_underval');


?>
<div>
	<h2>meta accelerator</h2>
	<input type="hidden" id="input_target_posttype" name="target_posttype" value="" />
	<script type="text/javascript">
		var cols = {};
	</script>

	<div id="tabs">
		<ul>
			<li><a href="#tabs-1"><?php \_e("Setting");?></a></li>
			<li><a href="#tabs-2"><?php \_e("Post Types");?></a></li>
		</ul>
		<div id="tabs-1">
			<form method="post" name="frm-opt" action="" >
				<input type="hidden" name="opt-save" value="opt-save" />
			<input type="checkbox" name="no_underval" value="no_underval" <?php if($no_underval == 'no_underval') echo 'checked';?> /> <?php \_e("Dont Accelerate System Value (The key that begins with underscore)");?>

			<br /><br />
			<input type="submit" class="btn_save_opt button-primary" value="<?php \_e("save");?>" />
				<br /><br />
			</form>
		</div>
		<div id="tabs-2">
	<?php
			$post_types = get_post_types(array(), "objects");
			foreach($post_types as $post_type) {
				// カスタムフィールド一覧

	?>
			<div style="border-bottom: dotted 1px gray; padding: 1em;">
				<h3><?php echo $post_type->labels->name;?></h3>
				<div>
					<div style="display: inline-block;" class="proceed<?php echo $post_type->name;?>"></div><img class="proceed_img proceed_img<?php echo $post_type->name;?>" style="display: none;" src="<?php echo plugins_url(); ?>/meta-accelerator/ajax-loader.gif" />
				<?php
				if(Posttype::is_accelerated($post_type->name)) {
					$obj_posttype = Posttype::get_instance($post_type->name);
				?>
						<input type="button" class="btn_delete  button-secondary" target="<?php echo $post_type->name;?>" value="<?php \_e("delete accelerator");?>" />
					<input type="button" class="btn_add  button-secondary" target="<?php echo $post_type->name;?>" value="<?php \_e("recreate accelerator");?>" />

					<br /><br /><a href="#" class="btn_showdetail" target="<?php echo $post_type->name;?>"><?php \_e("detail show/hide");?></a>

					<table class="tbl_detail_<?php echo $post_type->name;?>" style="display: none;">
						<tr>
							<th>meta_key</th>
							<th>field_key</th>
						</tr>
						<?php
						$array_cols = $obj_posttype->get_cols();
						if(is_array($array_cols)) {
						foreach($array_cols as $key => $val) {
	?>
							<tr>
								<td><?php echo $key;?></td>
								<td>col_<?php echo $val;?></td>
							</tr>
	<?php
						}}
						?>
					</table>
				<?php
				} else {
				?>
					<input type="button" class="btn_add  button-primary" target="<?php echo $post_type->name;?>" value="<?php \_e("create accelerator");?>" />
				<?php
				}
				?>
				</div>
					<?php
					if(Posttype::is_accelerated($post_type->name)) {
						// カスタムフィールド一覧
						$posttype = new Posttype($post_type->name);
						$cols = $posttype->get_cols();
					?>
					<div>

					</div>
					<?php
					}
					?>
			</div>


	<?php

			}
	?>

		</div>
	</div>

</div>


<?php
}