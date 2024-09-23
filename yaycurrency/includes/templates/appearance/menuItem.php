<?php
defined( 'ABSPATH' ) || exit;
?>
<div id="yay-currency-dropdown" class="posttypediv">
		<div id="tabs-panel-yay-currency-dropdown" class="tabs-panel tabs-panel-active">
		<ul id="yay-currency-dropdown-checklist" class="categorychecklist form-no-clear">
			<li>
			<label class="menu-item-title">
				<input type="checkbox" class="menu-item-checkbox" name="menu-item[-1][menu-item-object-id]" value="-1"> <?php echo esc_html_e( 'YayCurrency Switcher', 'yay-currency' ); ?>
			</label>
			<input type="hidden" class="menu-item-title" name="menu-item[-1][menu-item-title]" value="YayCurrency Switcher">
			<input type="hidden" class="menu-item-classes" name="menu-item[-1][menu-item-classes]" value="yay-currency-dropdown">
			</li>
		</ul>
		</div>
		<p class="button-controls">
		<span class="add-to-menu">
			<input type="submit" class="button-secondary submit-add-to-menu right" value="Add to Menu" name="add-post-type-menu-item" id="submit-yay-currency-dropdown">
			<span class="spinner"></span>
		</span>
		</p>
</div>
