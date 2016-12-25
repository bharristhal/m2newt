var allAccountsTableDiv = document.getElementById('AccountsTable_div');
var allAccountsTable = new google.visualization.Table( allAccountsTableDiv );
var allAccountsTableData = null; // data object for the all-accounts-table

var distributionChartDiv = document.getElementById('distchart_div');
var distributionChart = new google.visualization.PieChart( distributionChartDiv );

var singleAccountTableDiv = document.getElementById('SingleAccountTable_div');
var singleAccountTable = new google.visualization.Table( singleAccountTableDiv );

var singleAccountChartDiv = document.getElementById('SingleAccountChart_div');
var singleAccountChart = new google.visualization.AreaChart( singleAccountChartDiv );

$('#btnshowaccounts').click( function() {
    loadAccountsTable('all');	
});

$('#btnSave').click(  function() {
	console.log("btsave click");
	var data = $("#accountAdminFormId :input").serializeArray();
    $.post( "scripts/MyMoneyService.php", data, function(json) {
    	console.log("Returned from db call.");
        if ( json.status == "fail" ) {
            console.error( "Failed to capture transaction." );
        }

        if ( json.status == "success" ) {
            console.log( "Transaction has been saved.");
			var acctId = $("#accountSelect").val();
            loadSingleAccountTable( acctId );
            clearInputs();
        }
    }, "json");
    // .fail( function( fdata ) {
    // 	console.log( fdata.responseText );
    //     console.log("Save Data Failed!");
    // });
    clearInputs();
    console.log("leaving button click");
});

$("#accountAdminFormId").submit( function() {
    return false;
});

$(document).ready( function() {

	google.visualization.events.addListener( allAccountsTable, 'select', loadSingleAccount );

	/*google.load('visualization', '1.0', {'packages':['corechart']});*/
	google.setOnLoadCallback( updateHeaderCharts );
	$('#datepicker').datepicker({changeMonth: true, changeYear: true});
	$('#datepicker').datepicker("setDate", new Date() );
	loadAccountSelector();
	loadAccountsTable('all');
});

function updateHeaderCharts() {
	console.log("-->updateHeaderCharts");
	loadNetworthChart();
	loadDistributionChart();
	console.log("updateHeaderCharts-->");
}


function clearInputs() {
	console.log("-->clearInputs()");
	$("#accountSelect").val(0);
	$('#datepicker').datepicker("setDate", new Date() );
	$("#txn_amount").val('');
	console.log("clearInputs()-->");
}

function getNetworthValue() {
	console.debug("-->getNetworthValue");
	var value = 0;
	$.ajax( {
		url: 'scripts/MyMoneyService.php?action=getNetworthValue',
		dataType: "json",
		async: false 
	}).done( function( json_data ) {
		$.each( json_data.message, function() {
			value = this['Amount'];
			console.log("get:snv: " + value ); 
		})
	});
	console.debug("getNetworthValue-->");
	return value;

}

function loadAccountSelector() {
	$.getJSON('scripts/MyMoneyService.php?action=getAccounts', function( json_data ) {
		var accountSelector_obj = $("#accountSelect");
		accountSelector_obj.empty();
		var selector_option = "<option value='99'>--Please Select An Account--</option>";
		accountSelector_obj.append( selector_option );
		/* load up the accounts from db into selector */
		var i=0;
		$.each( json_data.message, function() {
			var selector_option = "<option value='" + this['Id'] + "'>" +
									this['Account'] + "</option>";
			accountSelector_obj.append( selector_option );
		});
	});

}


function loadAccountsTable( acctType ) {
	// need to filter based upon parameter passed in.
	$.getJSON('scripts/MyMoneyService.php?action=loadAccounts', function( json_data ) {
		var tableData = new Array();
		var tableDataHeader = new Array();

		tableDataHeader[0]="Id";
		tableDataHeader[1]="Account";
		tableDataHeader[2]="Account Number";
		tableDataHeader[3]="Account Type";
		tableDataHeader[4]="Account Class";
		tableDataHeader[5]="Account Category";

		tableData[0] = tableDataHeader;

		var i=1;
		$.each( json_data.message, function() {
			var dataRec = new Array();
			dataRec[0]=this['Id'];
			dataRec[1]=this['Account'];
			dataRec[2]=this['Number'];
			dataRec[3]=this['Type'];
			dataRec[4]=this['Class'];
			dataRec[5]=this['Category'];

			tableData[i++]=dataRec;
		});

		allAccountsTableData = google.visualization.arrayToDataTable( tableData );
		allAccountsTable.draw( allAccountsTableData, null);
	});

}

function loadSingleAccount( e ) {
	var selectedItems = allAccountsTable.getSelection();
	for ( var i=0; i<selectedItems.length; i++ ) {
		var item = selectedItems[i];
		if ( item.row != null && item.column == null ) {
			accountId = allAccountsTableData.getValue( item.row, 0 );
			
			console.log("....   BEN  .... : " + accountId );
			setAccountSelection( accountId );
			loadSingleAccountTable( accountId );

		} else {
			alert("Warning... Selection was not as expected.");
		}
	}
}

function setAccountSelection( acctId ) {
	console.log("-->setAccountSelection");
	$("#accountSelect").val( acctId );
	console.log("setAccountSelection-->");

}


function loadSingleAccountTable( acctId ) {
	console.log('-->loadSingleAccountTable():' + acctId );
	$.getJSON('scripts/MyMoneyService.php?action=getAccountData&id='+acctId, function( json_data ) {

		var dataset = new Array();	
		var datasetHeader = new Array();
		datasetHeader[0]='Date';
		datasetHeader[1]='Amount';
		dataset[0]=datasetHeader;

		var i=1;
		$.each( json_data.message, function() {
			var dataRec = new Array();
			dataRec[0] = this['Date'];
			dataRec[1] = parseFloat(this['Amount']);
			dataset[i++]=dataRec;	
		});
		var data = google.visualization.arrayToDataTable( dataset );

		var options = {
				height: 300,
				width: 300,
				hAxis: { title:'Date', titleTextStyle: {color: 'red'}}
		};

		var tableOptions = {
			height: 100,
			width: 300,
			sortColumn: 0,
			sortAscending: false
		};

		singleAccountTable.draw( data, tableOptions);
		singleAccountChart.draw( data, options);
		updateHeaderCharts();
	});
	console.log('loadSingleAccountTable()-->');
}

function loadDistributionChart() {
	console.log('-->loadDistributionChart()');
	$.getJSON('scripts/MyMoneyService.php?action=getDistribution', function( json_data ) {
		var data = new google.visualization.DataTable();	
		data.addColumn('string', 'Category');
		data.addColumn('number', 'Amount');

		var dataset = new Array();
		var i=0;
		$.each( json_data.message, function() {
			var datarec = new Array();
			datarec[0] = "'" + this['Cat'] + "'";
			datarec[1] = this['Amount'] * 100;
			dataset[i++]=datarec;
		});
		data.addRows( dataset );
		console.log('ldc dataset:' + dataset);
		var options= {'title':'Distribution', 'width':400, 'height':300, 'is3D':true };
		distributionChart.draw( data, options );

	}).fail( 
		function(x, m, e) {
			console.log("ERROR: " + m + "|" + e );
		}
	);
	console.log('loadDistributionChart()-->');
}

function loadNetworthChart() {
	console.log('-->loadNetworthChart()');
	$.getJSON('scripts/MyMoneyService.php?action=getNetworth', 
		function( json_data ) { 
			console.log("... In success methd of loadNetworthChart and processing data...");
			var chartDataArray = new Array();
			var dataHeaderArray = new Array(); 
			
			dataHeaderArray[0]='Date';
			dataHeaderArray[1]='Networth Amount';
			chartDataArray[0]=dataHeaderArray; 
			
			var i=0;
			$.each( json_data.message, 
				function() {
					var dataRec = new Array();
					dataRec[0] = this['Date'];
					var nwVal = parseFloat(this['Amount']);
					console.log("nwVal:" + nwVal);
					dataRec[1] = nwVal; 
					chartDataArray[i+1]=dataRec;
					i++;}
			);
			console.log('Loading the networth chart with data.');
			var nwValue = getNetworthValue();
			console.log('debug: ' + formatAsMoney( nwValue ) );
			var data = google.visualization.arrayToDataTable( chartDataArray );
			var options = {
				height: 300,
				width: 700,
				title: 'Networth: ' + formatAsMoney( nwValue ),
				hAxis: { title:'Year', titleTextStyle: {color: 'red'}}
			};
			var chart = new google.visualization.AreaChart(document.getElementById('networthChart_div'));
        	chart.draw( data, options);
		}
	).fail( function(jqxhr, textStatus, error) {
		console.log("Error occurred: " + jqxhr + "|" + textStatus + " | " + error);
	});
	console.log('loadSNetworthChart()-->');
}

function formatAsMoney( amount ) {
	var cAmount = "00";
	var fAmount = "0";

	if ( amount === null || amount === 0 ) {
		return "$" + fAmount + "." + cAmount;
	}
	var dPnt = amount.indexOf(".");
	if ( dPnt > -1 ) {
		cAmount = amount.substring( dPnt+1 ) ;
		console.log("cAmount: " + cAmount);
		fAmount = amount.substring( 0 , dPnt );
		console.log("fAmount: " + fAmount );
	} else {
		fAmount = amount;
	}

	if ( fAmount.length > 3 ) {
		var fAmount_tmp = "";
		for ( var i=fAmount.length; i >= 0; i-- ) {
			if ( i%3 === 2 && fAmount_tmp.length > 2 ) {
				console.log("x: i:" + i );
				fAmount_tmp = fAmount.charAt(i) + "," + fAmount_tmp;
			} else {
				fAmount_tmp = fAmount.charAt(i) + fAmount_tmp;
			}
		}
		fAmount = fAmount_tmp;
	}	

	return "$" + fAmount + "." + cAmount;
}
