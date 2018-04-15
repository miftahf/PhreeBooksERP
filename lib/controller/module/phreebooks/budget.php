<?php
/*
 * This class handles the budget and cash flow menu items
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
 * @copyright  2008-2018, PhreeSoft
 * @license    http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @version    2.x Last Update: 2018-02-27
 * @filesource /lib/controller/module/phreebooks/budget.php
 */

namespace bizuno;

class phreebooksBudget
{
    public $moduleID = 'phreebooks';
    
    function __construct()
    {
        $this->lang = getLang($this->moduleID);
    }

    /**
     * Main entry page for PhreeBooks budgeting
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateSecurity('phreebooks', 'budget', 3)) { return; }
		$title  = lang('phreebooks_budget');
		$selFY  = dbFiscalDropDown();
		$maxFY  = date('Y');
        foreach ($selFY as $year) { $maxFY = $year['id']; }
		$maxFY++;
		$selData= [['id'=>'actuals','text'=>lang('actuals')], ['id'=>'budget','text'=>lang('budget')]];
		$data   = [
            'pageTitle'=> $title,
			'divs'     => [
                'submenu'  => ['order'=>10, 'type' =>'html', 'html'=>viewSubMenu('ledger')],
                'heading'  => ['order'=>20, 'type' =>'html', 'html'=>"<h1>$title</h1>"],
				'divWizard'=> ['order'=>40, 'src'  =>BIZUNO_LIB."view/module/phreebooks/divBudgetWizard.php"],
				'fsethead' => ['order'=>69, 'type' =>'html', 'html'=>"<fieldset><legend>".'Details'."</legend>"],
				'divBudget'=> ['order'=>70, 'label'=>$title, 'type'=>'datagrid','key'=>'manager'],
				'fsetfoot' => ['order'=>71, 'type' =>'html', 'html'=>"</fieldset>"]],
			'forms' => ['frmWizard'=>  ['attr'=>  ['type'=>'form','action'=>BIZUNO_AJAX."&p=phreebooks/budget/wizard"]]],
			'fields'=> [
                'destFY'    => ['values'=>$selFY,  'attr'=>  ['type'=>'select', 'value'=>((int)date('Y')+1)]],
				'srcFY'     => ['values'=>$selFY,  'attr'=>  ['type'=>'select', 'value'=>date('Y')]],
				'srcData'   => ['values'=>$selData,'attr'=>  ['type'=>'select']],
				'adjVal'    => ['classes'=>  ['numbox'=>'easyui-numberbox'], 'attr'=>  ['value'=>'0', 'data-options'=>"precision:1"]],
				'avgVal'    => ['attr'=>  ['type'=>'checkbox', 'value'=>'1']],
				'btnSaveWiz'=> ['attr'=>  ['type'=>'button', 'value'=>lang('go')],
					'events'=>  ['onClick'=>"jq('#frmWizard').submit();"]],
				'btnNextFY' => ['attr'=>  ['type'=>'button', 'value'=>$this->lang['phreebooks_new_fiscal_year']],
					'events'=>  ['onClick'=>"if (confirm('".sprintf($this->lang['msg_gl_fiscal_year_confirm'], $maxFY)."')) jsonAction('phreebooks/tools/fyAdd');"]]],
			'datagrid' => ['manager'=>$this->dgBudget('dgBudget', $security)],
            'lang' => $this->lang,
            ];
		$layout = array_replace_recursive($layout, viewMain(), $data);
	}

	/**
     * Datagrid structure for budgeting
     * @param string $name - DOM field name used for the datagrid
     * @return array - datagrid structure ready to render
     */
    private function dgBudget($name)
    {
		// set the types to those that make sense
		// dbGLDropDown(false, ['0']) - for cash type
		return [
            'id'    => $name,
			'type'  => 'edatagrid',
			'attr'  => ['url'=>BIZUNO_AJAX."&p=phreebooks/budget/managerRows",'toolbar'=>"#{$name}Toolbar",'pagination'=>false,'singleSelect'=> true],
			'events'=> [
                'onLoadSuccess'=> "function(rowIndex, rowData) { jq('#$name').datagrid('fitColumns'); }",
				'onEndEdit'    => "function(index) { if (index!=12) budgetTotal(); }",
				'rowStyler'    => "function(index, row) { if (row.code=='".getUserCache('profile', 'currency', false, 'USD')."') { return {class:'row-default'}; }}"],
			'source' => [
                'actions'=> [
                    'saveBgt'=> ['order'=>10,'html'=>  ['icon'=>'save',   'events'=>  ['onClick'=>"budgetSave();"]]],
					'clrBgt' => ['order'=>20,'html'=>  ['icon'=>'clear',  'events'=>  ['onClick'=>"budgetClear();"]]],
					'copyBgt'=> ['order'=>30,'html'=>  ['icon'=>'copy',   'label'=>'Copy Actuals from Prior Fiscal Year','events'=>  ['onClick'=>"budgetCopy();"]]],
					'avgBgt' => ['order'=>40,'html'=>  ['icon'=>'average','label'=>'Spread Monthly Values Averaged Over the Fiscal Year','events'=>  ['onClick'=>"budgetAverage();"]]],
					'avgTtl' => ['order'=>50,'html'=>  ['icon'=>'fillup', 'label'=>'Distribute Total Budget Value Over the Fiscal Year','events'=>  ['onClick'=>"budgetDistribute();"]]],
                    ],
				'filters' => [
                    'fy'=> ['order'=>10,
						'html'=>  ['label'=>lang('phreebooks_fiscal_year'),'values'=>dbFiscalDropDown(), 'attr'=>  ['type'=>'select', 'value'=>date('Y')]]],
					'glAcct'=> ['order'=>20,
						'html'=>  ['label'=>lang('gl_account'), 'values'=>dbGLDropDown(false, [34]),'attr'=>  ['type'=>'select', 'value'=>getModuleCache('phreebooks', 'settings', 'customers', 'gl_cash')]]],
                        ],
                ],
			'columns' => [
                'period'  => ['order'=>10, 'label'=>lang('period'),   'attr'=>  ['width'=>50,'resizable'=>true,'align'=>'center']],
				'dates'   => ['order'=>20, 'label'=>$this->lang['fiscal_dates'], 'attr'=>  ['width'=>200,'resizable'=>true,'align'=>'center']],
				'last_act'=> ['order'=>30, 'label'=>$this->lang['ly_actual'],'attr'=>  ['width'=>100,'resizable'=>true,'align'=>'right'],
					'events'=> ['formatter'=>"function(value,row){ return formatCurrency(value); }"]],
				'last_bud'=> ['order'=>40, 'label'=>$this->lang['ly_budget'],'attr'=>  ['width'=>120,'resizable'=>true,'align'=>'right'],
					'events'=> ['formatter'=>"function(value,row){ return formatCurrency(value); }"]],
				'cur_act' => ['order'=>50, 'label'=>lang('actuals'),  'attr'=>  ['width'=>100,'resizable'=>true,'align'=>'right'],
					'events'=> ['formatter'=>"function(value,row){ return formatCurrency(value); }"]],
				'cur_bud' => ['order'=>60, 'label'=>lang('budget'),   'attr'=>  ['width'=>100,'resizable'=>true,'align'=>'right'],
					'events'=> ['editor'=>"{type:'numberbox'}", 'formatter'=>"function(value,row){ return formatCurrency(value); }"]],
                    ],
                ];
	}

	/**
     * Lists the budget information to populate the datagrid
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function managerRows(&$layout=[])
    {
        if (!$security = validateSecurity('phreebooks', 'budget', 3)) { return; }
		$fy = clean('fy',   ['format'=>'integer','default'=>date('Y')], 'post');
		$gl = clean('glAcct','text', 'post');
        if (!$gl) { return ['type'=>'raw', 'content'=>json_encode(['total'=>0, 'rows'=>  []])]; }
		// get the fiscal year first period (for start and end dates)
		$period = dbGetValue(BIZUNO_DB_PREFIX."journal_periods", 'period', "fiscal_year=$fy ORDER BY period");
		// fetch the prior fy budget and actuals
		$result = dbGetMulti(BIZUNO_DB_PREFIX."journal_history", "period>=".($period-12)." AND period<=".($period-1)." AND gl_account='$gl'", 'period');
		msgDebug("\nPrior year sql found ".sizeof($result)." rows.");
		$key    = 0;
		$budget = 0;
		$actual = 0;
        $output = [];
		for ($i=0; $i<12; $i++) { // should always be 12 entries
			$output[$key]['last_bud']= isset($result[$i]['budget'])      ? $result[$i]['budget'] : 0;
			$output[$key]['last_act']= isset($result[$i]['debit_amount'])? $result[$i]['debit_amount'] - $result[$i]['credit_amount'] : 0;
			$budget += isset($result[$i]['budget'])      ? $result[$i]['budget'] : 0;
			$actual += isset($result[$i]['debit_amount'])? $result[$i]['debit_amount'] - $result[$i]['credit_amount'] : 0;
			$key++;
		}
		$output[$key]['last_bud']= $budget;
		$output[$key]['last_act']= $actual;
		// fetch the current fy budget and actuals
		$result = dbGetMulti(BIZUNO_DB_PREFIX."journal_history", "period>=$period AND period<=".($period+11)." AND gl_account='$gl'", 'period');
		msgDebug("\nCurrent year sql found ".sizeof($result)." rows.");
		$key    = 0;
		$budget = 0;
		$actual = 0;
		foreach ($result as $row) { // should always be 12 entries
			$tmp = dbGetFiscalDates($period+$key); // also get the fiscal dates for the current info
			$output[$key]['period'] = $period + $key;
			$output[$key]['dates']  = viewFormat($tmp['start_date'], 'date').' - '.viewFormat($tmp['end_date'], 'date');
			$output[$key]['cur_bud']= $row['budget'];
			$output[$key]['cur_act']= $row['debit_amount'] - $row['credit_amount'];
			$budget += $row['budget'];
			$actual += $row['debit_amount'] - $row['credit_amount'];
			$key++;
		}
		$output[$key]['period'] = '';
		$output[$key]['dates']  = lang('total');
		$output[$key]['cur_bud']= $budget;
		$output[$key]['cur_act']= $actual;
		$layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>sizeof($output), 'rows'=>$output])]);
	}

	/**
     * Wizard to populate budget fields based on prior years data
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function wizard(&$layout=[])
    {
        if (!$security = validateSecurity('phreebooks', 'budget', 3)) { return; }
		$srcFY     = clean('srcFY',  ['format'=>'integer', 'default'=>date('Y')], 'post'); // 2014
		$destFY    = clean('destFY', ['format'=>'integer', 'default'=>date('Y')+1], 'post'); // 2015
		$srcData   = clean('srcData',['format'=>'text', 'default'=>'actuals'], 'post'); // actuals
		$adjVal    = 1 + (clean('adjVal', 'float', 'post') / 100);// 3.0, converted to decimal
		$avgVal    = clean('avgVal', 'integer', 'post'); // 0
		$srcDB   = dbGetMulti(BIZUNO_DB_PREFIX."journal_periods", "fiscal_year=$srcFY", "period", 'period');
		$srcPeriods= [];
        foreach ($srcDB as $row) { $srcPeriods[] = $row['period'] ; }
		$destDB   = dbGetMulti(BIZUNO_DB_PREFIX."journal_periods", "fiscal_year=$destFY","period", 'period');
		$dstPeriods= [];
        foreach ($destDB as $row) { $dstPeriods[] = $row['period'] ; }
		$actTotal  = [];
		$budTotal  = [];
		$output    = [];
		$values    = dbGetMulti(BIZUNO_DB_PREFIX.'journal_history', "period IN (".implode(',', $srcPeriods).") AND gl_type IN (34)", "period, gl_account");
		foreach ($values as $row) {
			$key = array_search($row['period'], $srcPeriods);
			$output[$dstPeriods[$key]][$row['gl_account']] = $adjVal * ($srcData=='actuals' ? $row['debit_amount']-$row['credit_amount'] : $row['budget']);
            if (!isset($actTotal[$row['gl_account']])) { $actTotal[$row['gl_account']] = 0; }
            if (!isset($budTotal[$row['gl_account']])) { $budTotal[$row['gl_account']] = 0; }
			$actTotal[$row['gl_account']] += $adjVal * ($row['debit_amount']-$row['credit_amount']);
			$budTotal[$row['gl_account']] += $adjVal * $row['budget'];
		}
		foreach ($output as $period => $rows) { foreach ($rows as $gl => $value) {
            if ($srcData == 'actuals') { $amt = $avgVal ? $actTotal[$gl]/12 : $value; }
            else					   { $amt = $avgVal ? $budTotal[$gl]/12 : $value; }
			dbWrite(BIZUNO_DB_PREFIX.'journal_history', ['budget'=>$amt], 'update', "period=$period AND gl_account='$gl'");
        } }
		msgDebug("\n Wrote to db data = ".print_r($output, true));
		msgLog(lang('phreebooks_budget').' - '.lang('wizard')." (Source FY:$srcFY TO $destFY)");
		msgAdd("Wizard successful!", 'success');
		// reload datagrid
		$layout = array_replace_recursive($layout, ['content'=>  ['action'=>'eval','actionData'=>"jq('#dgBudget').datagrid('reload');"]]);
	}

	/**
     * Saves the datagrid budget with user values
     * @return user message with result
     */
    public function save()
    {
        if (!$security = validateSecurity('phreebooks', 'budget', 3)) { return; }
		$data = clean('data', 'json', 'get');
        if (!$data) { return msgAdd(lang('err_no_data')); }
		msgDebug("\n Working with data = ".print_r($data, true));
        if (!isset($data['dg']['rows'])) { return msgAdd(lang('err_no_data')); }
		foreach($data['dg']['rows'] as $row) { if ($row['period']) {
			dbWrite(BIZUNO_DB_PREFIX.'journal_history', ['budget'=>$row['cur_bud']], 'update', "period={$row['period']} AND gl_account={$data['gl']}");
        } }
		msgLog(lang('phreebooks_budget').' - '.lang('save')." (FY: {$data['fy']}, GL: {$data['gl']})");
		msgAdd(lang('msg_settings_saved'), 'success');
	}
    
    /**
     * Method to create and analyze cash flow, eventually creating a chart or graph
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function cashFlow(&$layout=[])
    {
        if (!$security = validateSecurity('phreebooks', 'cashflow', 1)) { return; }
        $data = [];
        // get current cash balance, fill array for each day from now until end duration (3 months by default)
        // get vendor invoices, paid and unpaid, from today on
        // foreach invoice, adjust running balance based on due date
        // get customer invoices, paid and unpaid, from today on
        // foreach invoice, adjust running balance based on payment due date
        // get open POs, 
        // send the data to a graphical interface, line chart
        $layout = array_replace_recursive($layout, $data);
    }
}