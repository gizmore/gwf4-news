<h1><?php echo $lang->lang('sign_title'); ?></h1>

<p><?php echo $tVars['info']; ?></p>

<?php echo $tVars['form']; ?>

<?php
if ($tVars['subscribed'] === true) {
	echo GWF_Button::generic($lang->lang('btn_unsign'), $tVars['href_unsign']);
}
?>