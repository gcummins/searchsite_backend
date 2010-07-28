<?php

require_once "../includes/backend_requirements.php";

if (isset($_REQUEST['id']) && !empty($_REQUEST['id']))
{
	$banner_id = (int)$_REQUEST['id'];
}
else
{
	$banner_id = null;
}

$dealhuntingDatabase->query("SELECT `id`, `name`, `image_url` FROM `" . DEALHUNTING_BANNERS_TABLE . "`");

?>
<span class="edit_div_title">Select a Banner</span>
<div id="banner_selection_inner_div">
		<?php
		if ($dealhuntingDatabase->rowCount() == 0)
		{
			?><p>No banners were found</p>
			<?php
		}
		foreach ($dealhuntingDatabase->objects() as $row)
		{
			?><div class="banner_row" onclick="registerBannerSelection(<?php echo $row->id; ?>, '<?php echo $row->name;  ?>');">
				<label><?php echo $row->name; ?></label><br />
				<img src="<?php echo $row->image_url ?>" />
			</div>
			<?php
		}
		?>
</div>
<div class="form_button_div">
	<input type="button" value="Cancel" class="inputbutton" onclick="hideBannerSelection();" />
</div>