<?php
/**
 * zeList Import Page
 */
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) ==  realpath(__FILE__))
die("Are you sure ? ");
?>
<style>
.freeglobes_step {
	margin: 1em 0;
}

#zelist_import ul {
	margin-left: 1em;
}
</style>
<div id="zelist_import" class="wrap">
<h2><?php _e('zeList Import','zelist-importer'); ?></h2>

<?php
//$_POST['static_settings']['static_prefix'] = 'moqs_';zelist_import_freeglobes_links();die();


?>
<div id="freeglobes" class="div_settings">
	<input type="hidden" name="freeglobes_step" id="freeglobes_step" value="1" />
	<input type="hidden" name="import_nonce" id="import_nonce" value="<?php echo wp_create_nonce('import'); ?>" />

<h3><?php _e('FreeGlobes','zelist-importer'); ?></h3>
<a style="float: right;" id="back" href="#"><?php _e('Back'); ?></a>

<div class="freeglobes_step" id="freeglobes_step_1">
<p class="help"><?php _e('Howdy! This importer allows you to import link categories, links, link tags and link feeds from your FreeGlobes directory into your WordPress zeList directory.','zelist-importer'); ?>
<br />
<?php _e('Please copy databases tables from your FreeGlobes MySQL base to your WordPress Base.','zelist-importer')?>
</p>
<label for="prefix">1. <?php _e('Enter table prefix here','zelist-importer'); ?></label>
<input type="text" class="import_freeglobes_check_prefix_settings" name="prefix" value="globes_" />
<a class="button action" href="#" id="import_freeglobes_check_prefix"><?php _e('Check','zelist-importer'); ?></a>
</div>

<div class="freeglobes_step" id="freeglobes_step_2"><a
	class="button action" href="#" id="import_freeglobes_categories"><?php _e('Import Categories','zelist-importer'); ?></a>
</div>

<div class="freeglobes_step" id="freeglobes_step_3">
<a class="button action" href="#" id="import_freeglobes_links"><?php _e('Import Links','zelist-importer'); ?></a>
</div>

<div class="freeglobes_step" id="freeglobes_step_4">
<a class="button action" href="#" id="import_freeglobes_tags"><?php _e('Import Tags','zelist-importer'); ?></a>
<br /><div class="tip"><?php _e('This step can be intensive with thousands of associations to add. If an error is sent back, click again','zelist-importer'); ?></div>
</div>

<div class="freeglobes_step" id="freeglobes_step_5">
<?php _e('Congratulations, you just imported your FreeGlobes directory into zeList','zelist-importer'); ?>
<br /><?php printf(__('Now you can <a href="%s">edit links</a>, <a href="%s">categories</a> and <a href="%s">tags</a>','zelist-importer'),ZELIST_ADMIN_URL_LINK_MANAGER,ZELIST_ADMIN_URL_LINK_CATEGORIES,ZELIST_ADMIN_URL_LINK_TAGS); ?>
</div>



</div>
<div id="ajax_response">&nbsp;</div>

</div>



<?php
/*
 mysql -udbo191330257 -pGwnP6Rzu -hdb778.1and1.fr db191330257
 */