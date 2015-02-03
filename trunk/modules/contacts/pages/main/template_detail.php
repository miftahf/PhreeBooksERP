<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2015 PhreeSoft      (www.PhreeSoft.com)       |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /modules/contacts/pages/main/template_detail.php
//
echo html_form('contacts', FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'post', 'enctype="multipart/form-data"', true) . chr(10);
// include hidden fields
echo html_hidden_field('action',        '') . chr(10);
echo html_hidden_field('id',  $basis->cInfo->contact->id) . chr(10);
echo html_hidden_field('del_crm_note','') . chr(10);
echo html_hidden_field('payment_id',  '') . chr(10);
// customize the toolbar actions
if ($_REQUEST['action'] == 'properties') {
  $basis->toolbar->icon_list['cancel']['params'] = 'onclick="self.close()"';
  $basis->toolbar->icon_list['save']['show']     = false;
} else {
  $basis->toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'SSL') . '\'"';
  if ((!$basis->cInfo->contact->id && $security_level < 2) || ($basis->cInfo->contact->id && $security_level < 3)) {
    $basis->toolbar->icon_list['save']['show']   = false;
  } else {
    $basis->toolbar->icon_list['save']['params'] = 'onclick="submitToDo(\'save\')"';
  }
}
$basis->toolbar->icon_list['open']['show']       = false;
$basis->toolbar->icon_list['delete']['show']     = false;
$basis->toolbar->icon_list['print']['show']      = false;

// pull in extra toolbar overrides and additions
if (count($extra_toolbar_buttons) > 0) {
  foreach ($extra_toolbar_buttons as $key => $value) $basis->toolbar->icon_list[$key] = $value;
}

// add the help file index and build the toolbar
if( !$basis->cInfo->contact->help == '' ) $basis->toolbar->add_help($basis->cInfo->contact->help);
echo $basis->toolbar->build();
$basis->cInfo->contact->fields->display();
// Build the page

$custom_path = DIR_FS_MODULES . 'contacts/custom/pages/main/extra_tabs.php';
if (file_exists($custom_path)) { include($custom_path); }
?>
<h1><?php echo PAGE_TITLE; ?></h1>
<div class="easyui-tabs" id="detailtabs">
<?php
foreach ($basis->cInfo->contact->tab_list as $value) {
  if (file_exists(DIR_FS_WORKING . "custom/pages/main/{$value['file']}.php")) {
	include(DIR_FS_WORKING . "custom/pages/main/{$value['file']}.php");
  } else {
	include(DIR_FS_WORKING . "pages/main/{$value['file']}.php");
  }
}
// pull in additional custom tabs
if (isset($extra_contact_tabs) && is_array($extra_contact_tabs)) {
  foreach ($extra_contact_tabs as $tabs) {
    $file_path = DIR_FS_WORKING . "custom/pages/main/{$tabs['tab_filename']}.php";
    if (file_exists($file_path)) { require($file_path);	}
  }
}
echo $basis->cInfo->contact->fields->extra_tab_html;// user added extra tabs
?>
</div>
</form>