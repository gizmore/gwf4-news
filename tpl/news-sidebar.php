<gwf-sidebar-item>
	<h2>News</h2>
<?php foreach ($news as $item) { ?>
	<div><?php echo $item->displayTitle(); ?></div>
<?php } ?>
</gwf-sidebar-item>
