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
?>
<div>
	<h2>meta accelerator</h2>
	<input type="hidden" id="input_target_posttype" name="target_posttype" value="bukken" />
	<script type="text/javascript">
		var cols = {};
	</script>
<?php
		$post_types = get_post_types(array(), "objects");
		foreach($post_types as $post_type) {
			// カスタムフィールド一覧

?>
		<div>
			<h3><?php echo $post_type->labels->name;?></h3>
			<div>
				<div style="display: inline-block;" class="proceed<?php echo $post_type->name;?>"></div><img class="proceed_img proceed_img<?php echo $post_type->name;?>" style="display: none;" src="<?php echo plugins_url(); ?>/meta-accelerator/ajax-loader.gif" />
			<?php
			if(Posttype::is_accelerated($post_type->name)) {
				$obj_posttype = Posttype::get_instance($post_type->name);
			?>
					<input type="button" class="btn_delete  button-secondary" target="<?php echo $post_type->name;?>" value="<?php \_e("delete accelerator");?>" />
				<input type="button" class="btn_add  button-secondary" target="<?php echo $post_type->name;?>" value="<?php \_e("recreate accelerator");?>" />
				<table>
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


<?php
}