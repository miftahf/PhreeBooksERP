<?php
/*
 * Handles the backup and restore functions
 *
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.TXT.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please refer to http://www.phreesoft.com for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2019, PhreeSoft, Inc.
 * @license    http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @version    3.x Last Update: 2018-11-16
 * @filesource /lib/controller/module/bizuno/backup.php
 */

namespace bizuno;

class bizunoBackup
{
    public $moduleID = 'bizuno';
    private $update_queue = [];
        
    function __construct()
    {
        $this->lang = getLang($this->moduleID);
        $this->max_execution_time = 20000;
        $this->dirBackup = "backups/";
    }

    /**
     * Page entry point for the backup methods
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'backup', 1)) { return; }
        $incFiles = ['label'=>$this->lang['desc_backup_all'], 'position'=>'after', 'attr'=>['type'=>'checkbox', 'value'=>'all']];
        $btnBackup= ['icon'=>'backup', 'size'=>'large','label'=>lang('go'), 'events'=> ['onClick'=>"jq('body').addClass('loading'); jq('#frmBackup').submit();"]];
        $btnAudit = ['icon'=>'backup', 'size'=>'large','label'=>lang('go'), 'events'=> ['onClick'=>"jq('body').addClass('loading'); jsonAction('bizuno/backup/saveAudit');"]];
        $dateClean= ['attr'=>['type'=>'date', 'value'=>localeCalculateDate(date('Y-m-d'), 0, -1)]];
        $btnClean = ['icon'=>'next', 'size'=>'large','label'=>lang('go'), 'events'=> ['onClick'=>"jq('body').addClass('loading'); jq('#frmAudit').submit();"]];

        $htmlBackup = '<p>'.$this->lang['desc_backup'].'</p>'.html5('incFiles', $incFiles).'
        <p><div style="text-align:right">'.html5('btnBackup', $btnBackup).'</div></p>';
        $htmlAudit  = '<p>'.$this->lang['audit_log_backup_desc'].'</p>'.html5('btnAudit', $btnAudit)."\n<hr />\n
        <p>".$this->lang['desc_audit_log_clean'].'</p>'.html5('dateClean', $dateClean).html5('btnClean', $btnClean);
        $data = ['title'=>lang('bizuno_backup'),
            'toolbars'=> ['tbBackup'=>['icons'=>[
                'restore'=> ['order'=>20,'hidden'=>$security==4?false:true,'events'=>['onClick'=>"hrefClick('bizuno/backup/managerRestore');"]],
                'help'   => ['order'=>99,'index'=>'']]]],
            'divs'    => [
                'submenu'=> ['order'=> 5,'type'=>'html',   'html'=>viewSubMenu('tools')],
                'toolbar'=> ['order'=>10,'type'=>'toolbar','key'=>'tbBackup'],
                'heading'=> ['order'=>15,'type'=>'html',   'html'=>"<h1>".lang('bizuno_backup')."</h1>\n"],
                'body'   => ['order'=>50,'type'=>'divs','divs'=>[
                    'backup' => ['order'=>10,'type'=>'divs','label'=>lang('bizuno_backup'),'classes'=>['blockView'],'divs'=>[
                        'formBOF'=> ['order'=>15,'type'=>'form','key'=>'frmBackup'],
                        'body'   => ['order'=>50,'type'=>'html','html'=>$htmlBackup],
                        'formEOF'=> ['order'=>85,'type'=>'html','html'=>"</form>"]]],
                    'dgFiles'=> ['order'=>30,'type'=>'datagrid','classes'=>['blockView'],'key'=>'dgBackup'],
                    'audit'  => ['order'=>70,'type'=>'divs','label'=>$this->lang['audit_log_backup'],'classes'=>['blockView'],'divs'=>[
                        'formBOF'=> ['order'=>15,'type'=>'form','key'=>'frmAudit'],
                        'body'   => ['order'=>50,'type'=>'html','html'=>$htmlAudit],
                        'formEOF'=> ['order'=>85,'type'=>'html','html'=>"</form>"]]]]]],
            'forms'   => [
                'frmBackup'=> ['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&p=bizuno/backup/save"]],
                'frmAudit' => ['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&p=bizuno/backup/cleanAudit"]]],
            'datagrid'=> ['dgBackup'=>$this->dgBackup('dgBackup', $security)],
            'jsReady' => ['init'=>"ajaxForm('frmBackup'); ajaxForm('frmAudit');"]];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     * Load stored backup files through ajax call
     * @param array $layout - structure coming in
     */
    public function mgrRows(&$layout=[])
    {
        $io = new \bizuno\io();
        $rows = $io->fileReadGlob($this->dirBackup);
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>sizeof($rows), 'rows'=>$rows])]);
    }
    
    /**
     * This method executes a backup and download
     * @param array $layout - structure coming in
     * @return Doesn't return if successful, returns messageStack error if not.
     */
    public function save(&$layout)
    {
        if (!$security = validateSecurity('bizuno', 'backup', 2)) { return; }
        $incFiles = clean('data', 'text', 'post');
        // set execution time limit to a large number to allow extra time
        if (ini_get('max_execution_time') < $this->max_execution_time) { set_time_limit($this->max_execution_time); }
        // @todo add include files capability
        dbDump("bizuno-".date('Ymd-His'), $this->dirBackup);
        msgLog($this->lang['msg_backup_success']);
        msgAdd($this->lang['msg_backup_success'], 'success');
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jq('#dgBackup').datagrid('reload');"]]);
    }
    
    /**
     * Datagrid to create the list of backup files from the backup folder
     * @param string $name - html element id of the datagrid
     * @return array $data - datagrid structure
     */
    private function dgBackup($name, $security=0)
    {
        $data = ['id'=> $name, 'title'=>lang('files'),
            'attr'   => ['idField'=>'title', 'url'=>BIZUNO_AJAX."&p=bizuno/backup/mgrRows"],
            'columns'=> [
                'action' => ['order'=>1,'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index) { return {$name}Formatter(value,row,index); }"],
                    'actions'=> [
                        'download'=>['order'=>30,'icon'=>'download',
                            'events'=>['onClick'=>"jq('#attachIFrame').attr('src','".BIZUNO_AJAX."&p=bizuno/main/fileDownload&pathID={$this->dirBackup}&fileID=idTBD');"]],
                        'trash'   =>['order'=>70,'icon'=>'trash','hidden'=>$security<4?true:false,
                            'events'=>['onClick'=>"if (confirm('".lang('msg_confirm_delete')."')) jsonAction('bizuno/main/fileDelete','$name','{$this->dirBackup}idTBD');"]]]],
                'title'=> ['order'=>10,'label'=>lang('filename'),'attr'=>['width'=>200,'align'=>'center','resizable'=>true]],
                'size' => ['order'=>20,'label'=>lang('size'),    'attr'=>['width'=> 75,'align'=>'center','resizable'=>true]],
                'date' => ['order'=>30,'label'=>lang('date'),    'attr'=>['width'=> 75,'align'=>'center','resizable'=>true]]]];
        return $data;
    }
    
    /**
     * This method backs up the audit log database sends the result to the backups folder.
     * @param array $layout - structure coming in
     * @return json to reload datagrid
     */
    public function saveAudit(&$layout)
    {
        if (!$security = validateSecurity('bizuno', 'backup', 2)) { return; }
        dbDump("bizuno_log-".date('Ymd-His'), $this->dirBackup, BIZUNO_DB_PREFIX."audit_log");
        msgAdd($this->lang['msg_backup_success'], 'success');
        $layout = array_replace_recursive($layout,['content'=>['action'=>'eval','actionData'=>"jq('#dgBackup').datagrid('reload');"]]);
    }

    /**
     * Cleans old entries from the audit_log table prior to user specified data
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function cleanAudit(&$layout)
    {
        if (!$security = validateSecurity('bizuno', 'backup', 4)) { return; }
        $toDate = isset($_POST['dateClean']) ? clean($_POST['dateClean'], 'date') : localeCalculateDate(date('Y-m-d'), 0, -1); // default to -1 month from today
        $data['dbAction'] = [BIZUNO_DB_PREFIX."audit_log"=>"DELETE FROM ".BIZUNO_DB_PREFIX."audit_log WHERE date<='$toDate 23:59:59'"];
        $layout = array_replace_recursive($layout, $data);
    }

    public function managerUpgrade(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'backup', 4)) { return; }
        $btnUpgrade = ['icon'=>'next', 'size'=>'large','label'=>lang('go'), 'events'=> ['onClick'=>"jq('body').addClass('loading'); jsonAction('bizuno/backup/bizunoUpgradeGo');"]];
        $html  = "<h1>".lang('bizuno_upgrade')."</h1>\n";
        $html .= "<fieldset><legend>".lang('bizuno_upgrade')."</legend>";
        $html .= "<p>Click here to start your upgrade. Please make sure all users are not using the system. Once complete, all users will need to log out and back in to reset their cache.</p>";
        $html .= html5('', $btnUpgrade);
        $html .= "</fieldset>";
        $data = ['title'=> lang('bizuno_upgrade'),
            'toolbars'=> ['tbUpgrade'=>['icons'=>['cancel'=>['order'=>10,'events'=>['onClick'=>"location.href='".BIZUNO_HOME."&p=bizuno/backup/manager'"]]]]],
            'divs'    => [
                'toolbars'=> ['order'=>20,'type'=>'toolbar','key'=>'tbUpgrade'],
                'content' => ['order'=>50,'type'=>'html',   'html'=>$html]]];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }
    
    public function bizunoUpgradeGo(&$layout=[])
    {
        global $io;
        $pathLocal= BIZUNO_DATA."temp/";
        $zipFile  = $pathLocal."bizuno.zip";
        $bizID    = getUserCache('profile', 'biz_id');
        $bizUser  = getModuleCache('bizuno', 'settings', 'my_phreesoft_account', 'phreesoft_user');
        $bizPass  = getModuleCache('bizuno', 'settings', 'my_phreesoft_account', 'phreesoft_pass');
        $data     = http_build_query(['bizID'=>$bizID, 'UserID'=>$bizUser, 'UserPW'=>$bizPass]);
        $context  = stream_context_create(['http'=>[
            'method' =>'POST',
            'header' => "Content-type: application/x-www-form-urlencoded\r\n"."Content-Length: ".strlen($data)."\r\n",
            'content'=> $data]]);
        try {
            $source = "https://www.phreesoft.com/wp-admin/admin-ajax.php?action=bizuno_ajax&p=myPortal/admin/upgradeBizuno&host=".BIZUNO_HOST;
            $dest   = $zipFile;
            msgDebug("\nReady to fetch $source to $zipFile");
            @copy($source, $dest, $context);
            if (@mime_content_type($zipFile) == 'text/plain') { // something went wrong
                $msg = json_decode(file_get_contents($zipFile), true);
                if (is_array($msg)) { return msgAdd("Unknown Exception: ".print_r($msg, true)); }
                else                { return msgAdd("Unknown Error: ".print_r($msg, true)); }
            }
            if (file_exists($zipFile) && $io->zipUnzip($zipFile, $pathLocal, false)) {
                msgDebug("\nUnzip successful, removing downloaded zipped file: $zipFile");
                @unlink($zipFile);
                $srcFolder = $this->guessFolder("temp/");
                if (!$srcFolder) { return msgAdd("Could not find downloded upgrade folder, aborting!"); }
                $io->folderMove("temp/$srcFolder/", '', true);
                rmdir($pathLocal.$srcFolder);
            } else {
                return msgAdd('There was a problem retrieving the upgrade, please visit PhreeSoft community forum for assistance.');
            }
        } catch (Exception $e) {
            return msgAdd("We had an exception upgrading Bizuno: ". print_r($e, true));
        }
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"alert('".$this->lang['msg_upgrade_success']."'); jsonAction('bizuno/portal/logout');"]]);
    }

    /**
     * 
     * @param type $path
     * @return type
     */
    private function guessFolder($path)
    {
        global $io;
        $files = $io->folderRead($path);
        msgDebug("\nTrying to read folder $path and got results: ".print_r($files, true));
        foreach ($files as $file) {
            if (!is_dir(BIZUNO_DATA.$path.$file)) { continue; }
            $found = filemtime(BIZUNO_DATA.$path.$file) > time()-3600 ? true : false;
            msgDebug("\nGuessing folder $path$file with timestamp: ".filemtime(BIZUNO_DATA.$path.$file)." compared to a minute ago: ".(time()-60)." to be within 60 seconds and result = ".($found ? 'ture' : 'false'));
            if ($found) { return $file; }
        }
        msgAdd("Looking for unzipped upgrade files in folder ".BIZUNO_DATA."$path but could not find any. Please delete all folders in the directory and retry the upgrade.");
    }

    /**
     * Entry point for Bizuno db Restore page
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function managerRestore(&$layout)
    {
        global $io;
        $delFile = clean('del', 'text', 'get');
        if (!$security = validateSecurity('bizuno', 'backup', 4)) { return; }
        $jsHead = '<script type="text/javascript" src="'.BIZUNO_SRVR.'apps/jquery-file-upload/js/vendor/jquery.ui.widget.js"></script>
<script type="text/javascript" src="'.BIZUNO_SRVR.'apps/jquery-file-upload/js/jquery.iframe-transport.js"></script>
<script type="text/javascript" src="'.BIZUNO_SRVR.'apps/jquery-file-upload/js/jquery.fileupload.js"></script>';
        $jsBody = "jq(function () {
    jq('#file_upload').fileupload({ dataType:'json',maxChunkSize:500000,multipart:false,url:'".BIZUNO_AJAX."&p=bizuno/backup/uploadRestore',
        add:        function(e, data) { data.context = jq('#btn_upload').show().click(function () { jq('#btn_upload').hide(); jq('progress').show(); data.submit(); }); },
        progressall:function(e, data) { var progress = parseInt(data.loaded / data.total * 100, 10); jq('progress').attr({value:progress,max:100}); },
        done:       function(e, data) { alert('done!'); return; window.location = '".BIZUNO_HOME."&p=bizuno/backup/managerRestore'; }
    });
});";
        $data = ['type'=>'page','title'=>lang('bizuno_restore'),
            'toolbars'=> ['tbRestore'=>['icons'=>['cancel'=>['order'=>10,'events'=>['onClick'=>"location.href='".BIZUNO_HOME."&p=bizuno/backup/manager'"]]]]],
            'divs'    => [
                'toolbar'=> ['order'=>10,'type'=>'toolbar', 'key'=>'tbRestore'],
                'heading'=> ['order'=>15,'type'=>'html',    'html'=>"<h1>".lang('bizuno_restore')."</h1>\n"],
                'restore'=> ['order'=>50,'type'=>'divs','divs'=>[
                    'dgRstr'=> ['order'=>20,'type'=>'datagrid','key'=>'dgRestore'],
                    'body'  => ['order'=>40,'type'=>'fields','fields'=>$this->getViewRestore()]]]],
            'datagrid'=> ['dgRestore' => $this->dgRestore('dgRestore')],
            'jsHead'  => ['init'=>$jsHead],
            'jsBody'  => ['init'=>$jsBody]];
        if ($delFile) { $io->fileDelete($this->dirBackup.$delFile); }
        $data['bkFiles'] = $io->folderRead($this->dirBackup);
        msgDebug('found files: '.print_r($data['bkFiles'], true));
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    private function getViewRestore()
    {
        $fldFile = ['label'=>lang('msg_io_upload_select'),'attr'=>['type'=>'file','name'=>'files[]','multiple'=>true]];
        $btnUpld = ['break'=>true,'styles'=>['display'=>'none'],'attr'=>['type'=>'button','value'=>lang('upload')]];
        $barProg = ['html'=>'<progress style="display:none"></progress>','attr'=>['type'=>'raw']];
        return ['fldFile'=>$fldFile, 'btnUpld'=>$btnUpld, 'barProg'=>$barProg];
    }
    
    /**
     * Datagrid to list files to restore 
     * @param string $name - html element id of the datagrid
     * @return array $data - datgrid structure
     */
    private function dgRestore($name='dgRestore')
    {
        $data = ['id'=>$name, 'title'=>lang('files'),
            'attr'   => ['idField'=>'title', 'url'=>BIZUNO_AJAX."&p=bizuno/backup/mgrRows"],
            'columns'=> [
                'action' => ['order'=>1,'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index) { return {$name}Formatter(value,row,index); }"],
                    'actions'=> [
                        'start' => ['order'=>30,'icon'=>'import',
                            'events'=>  ['onClick'=>"if(confirm('".$this->lang['msg_restore_confirm']."')) { jq('body').addClass('loading'); jsonAction('bizuno/backup/saveRestore', 0, '{$this->dirBackup}idTBD'); }"]],
                        'trash' => ['order'=>70,'icon'=>'trash', 
                            'events'=>  ['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('bizuno/main/fileDelete','$name','{$this->dirBackup}idTBD');"]]]],
                'title'=> ['order'=>10,'label'=>lang('filename'),'attr'=>['width'=>200,'align'=>'center','resizable'=>true]],
                'size' => ['order'=>20,'label'=>lang('size'),    'attr'=>['width'=> 75,'align'=>'center','resizable'=>true]],
                'date' => ['order'=>30,'label'=>lang('date'),    'attr'=>['width'=> 75,'align'=>'center','resizable'=>true]]]];
        return $data;
    }

    /**
     * Method to receive a file to upload into the backup folder for db restoration
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function uploadRestore(&$layout)
    {
        global $io;
        if (!$security = validateSecurity('bizuno', 'backup', 2)) { return; }
        $io->options = [
            'script_url' => BIZUNO_ROOT."apps/jquery-file-upload/server/php/index.php",
            'upload_dir' => BIZUNO_DATA.$this->dirBackup,
            'upload_url' => BIZUNO_AJAX.'&p=bizuno/backup/uploadRestore',
            'param_name' => 'file_upload',
            'image_versions' => []]; // supresses creation of thumbnail folder 
        if (!isset($_SERVER['CONTENT_TYPE'])) { $_SERVER['CONTENT_TYPE'] = null; }
        $io->fileUpload();
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>null]); // content generated by jquery file upload plugin 
    }

    /**
     * This method restores a gzip db backup file to the database, replacing the current tables
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function saveRestore(&$layout)
    {
        if (!$security = validateSecurity('bizuno', 'backup', 4)) { return; }
        $dbFile = clean('data', 'text', 'get');
        if (!file_exists(BIZUNO_DATA.$dbFile)) { return msgAdd("Bad filename passed! ".BIZUNO_DATA.$dbFile); }
        // set execution time limit to a large number to allow extra time
        dbRestore($dbFile);
        $layout = array_replace_recursive($layout, ['content'=>  ['action'=>'eval','actionData'=>"alert('".$this->lang['msg_restore_success']."'); jsonAction('bizuno/portal/logout');"]]);
    }
}
