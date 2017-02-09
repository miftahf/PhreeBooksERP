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
//  Path: /modules/phreebooks/dashboards/todays_orders/todays_orders.php
//
// Revision history
// 2011-07-01 - Added version number for revision control
namespace phreebooks\dashboards\todays_orders;
class todays_orders extends \core\classes\ctl_panel {
	public $description	 		= CP_TODAYS_ORDERS_DESCRIPTION;
	public $security_id  		= SECURITY_ID_SALES_ORDER;
	public $text		 		= CP_TODAYS_ORDERS_TITLE;
	public $version      		= '4.0';
	
	function panelContent(){
	?>
		<table id='todays_orders' >
	    	<thead>
	    		<tr>
		        	<th data-options="field:'purchase_invoice_id',sortable:true, align:'center'"><?php echo TEXT_INVOICE_NUMBER;?></th>
	    	        <th data-options="field:'purch_order_id',sortable:true, align:'center'"><?php echo TEXT_PO_NUMBER?></th>
	    	        <th data-options="field:'bill_primary_name',sortable:true, align:'center'"><?php echo TEXT_COMPANY;?></th>
	        	    <th data-options="field:'post_date',sortable:true, align:'center', formatter: function(value,row,index){ return formatDate(new Date(value))}"><?php echo TEXT_DATE?></th>
	            	<th data-options="field:'closed_date',sortable:true, align:'center', formatter: function(value,row,index){ if ( value == '0000-00-00') {return ''}else{return formatDate(new Date(value))}}"><?php echo TEXT_PAID?></th>
		            <th data-options="field:'total_amount',sortable:true, align:'right', formatter: function(value,row,index){ return formatCurrency(value)}"><?php echo TEXT_AMOUNT?></th>
	    	    </tr>
	    	</thead>
	    </table> 
		
		<script type="text/javascript">
		$('#todays_orders').datagrid({
			url:		"index.php?action=loadOrders",
			queryParams: {
				journal_id: '10',
				post_date: '<?php echo date('Y-m-d') ?> ', 
<?php if($_SESSION['user']->is_role == 0) echo "store_id:{$_SESSION['user']->admin_prefs['def_store_id']},"?> 
				dataType: 'json',
		        contentType: 'application/json',
		        async: false,
			},
			onBeforeLoad:function(){
				console.log('loading of the todays orders datagrid');
			},
			onLoadSuccess: function(data){
				console.log('the loading of the todays orders was succesfull');
				$.messager.progress('close');
			},
			onLoadError: function(){
				console.error('the loading of the todays orders resulted in a error');
				$.messager.progress('close');
				$.messager.alert('<?php echo TEXT_ERROR?>','Load error for table todays orders');
			},
			onDblClickRow: function(index , row){
				console.log('a row in the todays orders was double clicked');
				//@todo open order
			},
			pagination: true,
			pageSize:   <?php echo MAX_DASHBOARD_SEARCH_RESULTS?>,
			remoteSort:	true,
			fitColumns:	true,
			showFooter: true,
			idField:	"id",
			singleSelect:true,
			sortName:	"purchase_invoice_id",
			sortOrder: 	"dsc",
			loadMsg:	"<?php echo TEXT_PLEASE_WAIT?>",
			rowStyler: function(index,row){
				if (row.closed == '1') return 'background-color:pink;';
			},
		});
		</script><?php
	}
}
?>