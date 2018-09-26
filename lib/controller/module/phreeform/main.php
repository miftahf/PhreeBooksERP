<?php
/*
 * Main methods for Phreeform 
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
 * @copyright  2008-2018, PhreeSoft, Inc.
 * @license    http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @version    3.x Last Update: 2018-09-04
 * @filesource /controller/module/phreeform/main.php
 */

namespace bizuno;

require_once(BIZUNO_LIB."controller/module/phreeform/functions.php");

class phreeformMain 
{
	public $moduleID = 'phreeform';
    private $reloadTree = false;

    function __construct()
    {
		$this->lang = getLang($this->moduleID);
		$this->security = getUserCache('security', 'phreeform');
	}

	/**
     * Generates the structure for the PhreeForm home page
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function manager(&$layout=[]) 
    {
        $rID = clean('rID', 'integer', 'get');
        $gID = clean('gID', 'text', 'get');
		if (!$rID && $gID) { // no node so look for group
            $rID = dbGetValue(BIZUNO_DB_PREFIX."phreeform", 'id', "group_id='$gID:rpt' AND mime_type='dir'");
		}
        $tbTree = '
<a href="#" class="easyui-linkbutton" onClick="jq(\'#treePhreeform\').tree(\'expandAll\');">'  .lang('expand_all')  .'</a>
<a href="#" class="easyui-linkbutton" onClick="jq(\'#treePhreeform\').tree(\'collapseAll\');">'.lang('collapse_all')."</a>";
		$data = ['title'=> lang('reports'),
			'toolbars'  => ['tbPhreeForm'=>['icons'=>[
                'mimeRpt' => ['order'=>30,'icon'=>'mimeTxt','hidden'=>($this->security>1)?false:true,'events'=>['onClick'=>"hrefClick('phreeform/design/edit&type=rpt', 0);"],
					'label'=>$this->lang['new_report']],
				'mimeFrm' => ['order'=>40,'icon'=>'mimeDoc','hidden'=>($this->security>1)?false:true,'events'=>['onClick'=>"hrefClick('phreeform/design/edit&type=frm', 0);"],
					'label'=>$this->lang['new_form']],
				'import'  => ['order'=>90,'hidden'=>($this->security>1)?false:true, 'events'=>['onClick'=>"hrefClick('phreeform/io/manager');"]]]]],
			'tree' => ['treePhreeform'=>['classes'=>['easyui-tree'],'events'=>[
                'onClick'      =>"function(node) { if (typeof node.id != 'undefined') {
    if (jq('#treePhreeform').tree('isLeaf', node.target)) { jsonAction('phreeform/main/detailReport', node.id); }
    else { jq('#treePhreeform').tree('toggle', node.target); } } }",
                'onLoadSuccess'=> "function() { var node=jq('#treePhreeform').tree('find',$rID); jq('#treePhreeform').tree('expandTo',node.target);
jq('#treePhreeform').tree('expand',  node.target); }"],
					'attr' => ['url'=>BIZUNO_AJAX."&p=phreeform/main/managerTree"]]],
			'divs' => [
                'toolbar'  => ['order'=>10,'type'=>'toolbar','key' =>'tbPhreeForm'],
				'heading'  => ['order'=>15,'type'=>'html',   'html'=>"<h1>".lang('reports')."</h1>\n"],
				'body'     => ['order'=>50,'type'=>'divs',   'divs'=>[
                    'divTree'=> ['order'=>20,'classes'=>['blockView'],'type'=>'divs','attr'=>['id'=>'reportTree'],'divs'=>[
                        'toolbar'=>['order'=>10,'type'=>'html','html'=>$tbTree],
                        'tree'   =>['order'=>50,'type'=>'tree','key'=>'treePhreeform']]],
                    'myRpt'  => ['order'=>30,'type'=>'html','html'=>$this->getViewMine()],
                    'recent' => ['order'=>40,'type'=>'html','html'=>$this->getViewRecent()]]]],
            'jsHead'=> ['phreeform' => "jq.cachedScript('".BIZUNO_URL."controller/module/phreeform/phreeform.js?ver=".MODULE_BIZUNO_VERSION."');"],
            'values'=> ['recent'=>getRecent(), 'mine'=>getMine()],
            'lang'  => $this->lang];
		$layout = array_replace_recursive($layout, viewMain(), $data);
    }
    
	/**
     * Gets the available forms/reports from a JSON call in the database and returns to populate the treegrid
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function managerTree(&$layout=[])
    {
        $result = dbGetMulti(BIZUNO_DB_PREFIX."phreeform", '', 'mime_type, title');
		// filter by security
		$output = [];
        foreach ($result as $row) { if (phreeformSecurity($row['security'])) { $output[] = $row; } }
		msgDebug("\n phreeform number of rows returned: ".sizeof($output));
		$data = ['id'=>'-1','text'=>lang('home'),'children'=>viewTree($output, 0)];
        trimTree($data);
		msgDebug("\nSending data = ".print_r($data, true));
		$layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>'['.json_encode($data).']']);
	}

	/**
     * Fetches the detailed listings for the home page
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function detailHome(&$layout=[])
    {
        if (!$security = validateSecurity('phreeform', 'phreeform', 1)) { return; }
		$group   = clean('group','text', 'get');
		$rID     = clean('rID',  'integer', 'get');
		$data = ['type'=>'divHTML','divID'=>'rightColumn',
            'divs' => ['order'=>50,'type'=>'divs','divs'=>[
                'subContent'=>['order'=>20,'type'=>'html','classes'=>['blockView'],'html'=>$this->getViewMine()],
                'subContent'=>['order'=>30,'type'=>'html','classes'=>['blockView'],'html'=>$this->getViewRecent()]]]];
		if ($this->reloadTree) {
			$this->reloadTree = false;
    		$js  = "jq('#phreeform_tree').tree({ url:'".BIZUNO_AJAX."&p=phreeform/main/managerTree&group=$group&rID=$rID' }).tree('reload');";
			$data['jsReady']['pfTree'] = $js;
		}
		$layout = array_replace_recursive($layout, $data);
	}
	
    private function getViewMine()
    {
        $mine   = getMine();
        $output = '<div class="blockView"><h3>'.lang('my_documents')."</h3>";
        foreach ($mine as $report) {
            $output .= '<div><a href="#" onClick="jsonAction(\'phreeform/main/detailReport\', '.$report['id'].');">';
            $output .= html5('', ['icon'=>viewMimeIcon($report['mime_type']), 'size'=>'small', 'label'=>$report['title']]);
            $output .= ' '.$report['title']."</a></div><br />\n";
        }
        if (sizeof($mine) == 0) { $output .= lang('msg_no_documents'); }
        $output.= "</div>\n";
        return $output;
    }
    
    private function getViewRecent()
    {
        $recent = getRecent();
        $output = '<div class="blockView"><h3>'.$this->lang['msg_recently_added_docs']."</h3>";
        foreach ($recent as $report) {
            $output .= '<div><a href="#" onClick="jsonAction(\'phreeform/main/detailReport\', '.$report['id'].');">';
            $output .= html5('', ['icon'=>viewMimeIcon($report['mime_type']),'size'=>'small','label'=>$report['title']]);
            $output .= ' '.$report['title']."</a></div><br />\n";
        }
        if (sizeof($recent) == 0) { $output .= lang('msg_no_documents'); }
        $output .= "</div>\n";
        return $output;
    }

	/**
	 * Builds the right div for details of a requested report/form. Returns div html
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
	 */
	public function detailReport(&$layout=[])
    {
        if (!$security = validateSecurity('phreeform', 'phreeform', 1)) { return; }
		$rID    = clean('rID', 'integer', 'get');
		if (!$rID) { return $this->detailHome($layout); } // default to home listing
		$report = dbGetRow(BIZUNO_DB_PREFIX."phreeform", "id='$rID'");
        if ($report['mime_type'] == 'dir') { return; } // folder, just return to do nothing
        $details= phreeFormXML2Obj($report['doc_data']);
        $report['description'] = $details->description;
		$data   = ['type'=>'popup','title'=>$details->title,'attr'=>['id'=>'popupOpen'],
            'divs' => ['divDetail'=>['order'=>50,'type'=>'divs','divs'=>[
                'toolbar'=> ['order'=>10,'type'=>'toolbar','key'=>'tbReport'],
                'body'   => ['order'=>50,'type'=>'html','html'=>$this->getViewReport($report)]]]],
            'toolbars'  => ['tbReport'=>['hideLabels'=>true,'icons'=>[
                'open'  => ['order'=>10,'events'=>['onClick'=>"winOpen('phreeformOpen', 'phreeform/render/open&rID=$rID');"]],
				'edit'  => ['order'=>20,'hidden'=>($security>1)?false:true, 
                    'events'=>['onClick'=>"window.location.href='".BIZUNO_HOME."&p=phreeform/design/edit&rID='+$rID;"]],
				'rename'=> ['order'=>30,'hidden'=>($security>2)?false:true, 
					'events'=>['onClick'=>"var title=prompt('".lang('msg_entry_rename')."'); jsonAction('phreeform/main/rename', $rID, title);"]],
				'copy'  => ['order'=>40,'hidden'=>($security>1)?false:true, 
					'events'=>['onClick'=>"var title=prompt('".lang('msg_entry_rename')."'); jsonAction('phreeform/main/copy', $rID, title);"]],
				'trash' => ['order'=>50,'hidden'=>($security>3)?false:true, 
					'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('phreeform/main/delete', $rID, '');"]],
				'export'=> ['order'=>60,'hidden'=>($security>2)?false:true, 
                    'events'=>['onClick'=>"window.location.href='".BIZUNO_AJAX."&p=phreeform/io/export&rID='+$rID;"]]]]],
            'report' => $report];
        if ($this->reloadTree) { $data['jsReady']['reload'] = "jq('#phreeform_tree').tree('reload');"; }
		$layout = array_replace_recursive($layout, $data);
	}

    private function getViewReport($report)
    {
        return "<h1>".$report['title']."</h1>
          <fieldset>".$report['description']."</fieldset>
          <div>
            ".lang('id').": {$report['id']}<br />
            ".lang('type').": {$report['mime_type']}<br />
            ".lang('create_date').": ".viewFormat($report['create_date'], 'date')."<br />
            ".lang('last_update').': '.viewFormat($report['last_update'], 'date')."</div>\n";
    }

    /**
     * Generates the structure and executes the report/form renaming operation
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function rename(&$layout=[])
    {
        if (!$security = validateSecurity('phreeform', 'phreeform', 3)) { return; }
		$rID   = clean('rID',  'integer', 'get');
		$title = clean('data', 'text', 'get');
		if (!$rID || !$title) { return msgAdd($this->lang['err_rename_fail']); }
		$strXML = dbGetValue(BIZUNO_DB_PREFIX."phreeform", 'doc_data', "id=$rID");
        // deprecate this line after WordPress Update from initial release
        if (strpos($strXML, '<PhreeformReport>') === false) { $strXML = '<root>'.$strXML.'</root>'; }
        $report = parseXMLstring($strXML);
		$report->title = $title;
		$sql_data = [
            'title'      => $title,
            'doc_data'   => object_to_xml($report),
            'last_update'=> date('Y-m-d')];
		$result = dbWrite(BIZUNO_DB_PREFIX."phreeform", $sql_data, 'update', "id='$rID'");
		if ($result) {
			msgLog(lang('phreeform_manager').'-'.lang('rename')." $title ($rID)");
			$this->reloadTree = true;
            $this->detailReport($layout);
		}
	}

	/**
     * Generates the structure take a report and create a copy, add to the database
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function copy(&$layout=[])
    {
        if (!$security = validateSecurity('phreeform', 'phreeform', 2)) { return; }
		$rID   = clean('rID',  'integer', 'get');
		$title = clean('data', 'text', 'get');
        if (!$rID || !$title) { return msgAdd($this->lang['err_copy_fail']); }
		$row = dbGetRow(BIZUNO_DB_PREFIX."phreeform", "id=$rID");
		unset($row['id']);
		unset($row['last_update']);
		$row['title'] = $title;
		$row['create_date'] = date('Y-m-d');
        // deprecate this line after WordPress Update from initial release
        if (strpos($row['doc_data'], '<PhreeformReport>') === false) { $row['doc_data'] = '<root>'.$row['doc_data'].'</root>'; }
        $report = parseXMLstring($row['doc_data']);
		$report->title = $title;
		$row['doc_data'] = object_to_xml($report);
		$newID = dbWrite(BIZUNO_DB_PREFIX."phreeform", $row);
		if ($newID) {
			msgLog(lang('phreeform_manager').'-'.lang('copy')." - $title ($rID=>$newID)");
			$this->reloadTree = true;
            $_GET['rID'] = $newID;
			$this->detailReport($layout);
		}
	}

	/**
     * Creates the structure to to accept a database record id and deletes a report
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function delete(&$layout=[])
    {
        if (!$security = validateSecurity('phreeform', 'phreeform', 4)) { return; }
		$rID = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd('The report was not deleted, the proper id was not passed!'); }
		$title = dbGetValue(BIZUNO_DB_PREFIX."phreeform", 'title', "id='$rID'");
		msgLog(lang('phreeform_manager').'-'.lang('delete')." - $title ($rID)");
		$this->reloadTree = true;
		$this->detailHome($layout);
        $layout = array_replace_recursive($layout, ['dbAction' => [BIZUNO_DB_PREFIX."phreeform" => "DELETE FROM ".BIZUNO_DB_PREFIX."phreeform WHERE id=$rID"]]);
	}
}
