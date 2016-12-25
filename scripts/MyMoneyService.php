<?php
	if ( $_SERVER['REQUEST_METHOD'] == 'GET' ) {
		if ($_GET['action'] == 'loadAccounts') {
			loadAccounts();
		} elseif ( $_GET['action'] == 'getAccountData') {
			getAccountData($_GET['id']);
		} elseif ( $_GET['action'] == 'getAccounts' ) {
			getAccounts();
		} elseif ( $_GET['action'] == 'saveTransaction' ) {
			saveTransaction( $_GET['account'], $_GET['txn_date'], $_GET['txn_amount'],$_GET['bal_amount'] );
		} elseif ( $_GET['action'] == 'getNetworth') {
			getNetWorth();
		} elseif ( $_GET['action'] == 'getNetworthValue') {
			getNetWorthValue();
		} elseif ( $_GET['action'] == 'getDistribution' ) {
			getDistribution();
		} 
	} elseif ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
	 	if ( $_POST['action'] == 'saveTransaction' ) {
			saveTransaction( $_POST['account'], $_POST['txn_date'], $_POST['txn_amount'],  $_POST['bal_amount'] );
		}
	} else {
		fail( "Unsupported/Unrecognized request : " + $_SERVER['REQUEST_METHOD']);
	}

	function saveTransaction( $acctId, $txnDate, $txnAmount, $balAmount ) {
		error_log("-->saveTransaction");
		// check for and remove $
		$txnAmount = trim( $txnAmount, "$");
        $balAmount = trim( $balAmount, "$");
		// remove comma's
		$exploded = explode(',', $txnAmount);
		$txnAmount = join('', $exploded);
        $exploded = explode(',',$balAmount);
        $balAmount= join('', $exploded);
        
		error_log("[saveTransaction] DEBUG: trimmed txnAmount : " . $txnAmount );
		$query = "insert into AccountRegistry (accountid, txnamount, txndate, amount, regdate ) values (".
				$acctId .",". $txnAmount .",str_to_date('". $txnDate ."','%m/%d/%Y'),".$balAmount.",SYSDATE())";
		print("Insert query: $query\n");
		$result = db_executeInsert( $query );
		success( $result );
		exit;
					
	}

	function getAccounts() {
		$query = "select a.id as 'id', a.description as 'account'".
			" from account a ";

		$result = db_executeQuery( $query );
		// print_r($result);	
		$accountsArray = array();	
		foreach ( $result as $row ) {
			array_push( $accountsArray, array('Id'=>$row['id'], 'Account'=>$row['account'] ));
		}

		success( $accountsArray );	
	}

	function loadAccounts() {
		$query = "select a.id as 'id', a.description as 'account', a.number as 'number', t.description as 'type',".
			" cl.description as 'class', cat.description as 'category'".
			" from account a ".
			" join AccountType t on a.typeid = t.id ".
			" join AccountClass cl on a.classid = cl.id ".
			" join AccountCategory cat on a.categoryid = cat.id ".
			" order by cl.description";

		$result = db_executeQuery( $query );
		// for debugging:  print_r($result);	
		$accountsArray = array();	
		foreach ( $result as $row ) {
			array_push( $accountsArray, array('Id'=>$row['id'], 'Account'=>$row['account'], 'Number'=>$row['number'],
				'Type'=>$row['type'], 'Class'=>$row['class'], 'Category'=>$row['category']) );
		}

		success( $accountsArray );
	}

	function getNetWorthValue() {
		$query = 	"select sum(ar.amount) as Amount ".
					" from accountregistry ar ".
					" inner join ".
					" ( ".
					"   select max(regdate) maxdate, accountid ".
					"   from accountregistry ".
					"   where concat(year(regdate),quarter(regdate)) <= concat(year(sysdate()),quarter(sysdate())) ".
					"   group by accountid ".
					" ) t on t.accountid = ar.accountid ".
					" and t.maxdate = ar.regdate";
		$result = db_executeQuery( $query );
		// error_log( $result );
		$accountArray = array();	
		foreach ( $result as $row ) {
			array_push( $accountArray, array('Amount'=>$row['Amount']));
		}
		success( $accountArray );
	}

	function getNetWorth() {
		error_log("-->getNetWorth()");

		$dbHandle = db_connect();

		// get min date found in data set
		$minDateQuery = "select year(min( regdate )) as Year, month(min(regdate)) as Month from accountregistry";
		error_log("[getNetWorth] ... about to execute query ...");
		$minDateResult = $dbHandle->query( $minDateQuery );
		error_log("... returned from execution ...");
		$minDateYear = 0;
		$minDateMonth = 0;
		foreach ( $minDateResult as $minRow ) {
			error_log("[getNetWorth] ... processing result set ...");
			$minDateYear = $minRow['Year'];
			$minDateMonth = $minRow['Month'];
		}
		error_log("[getNetWorth] MIN Y[".$minDateYear."] M[".$minDateMonth."]");

		// get max date found in data set
		$maxDateQuery = "select year(max(regdate)) as Year, month(max(regdate)) as Month from accountregistry";
		$maxDateResult = $dbHandle->query( $maxDateQuery );
		$maxDateYear = 0; 
		$maxDateMonth = 0;
		foreach ( $maxDateResult as $maxRow ) {
			$maxDateYear = $maxRow['Year'];
			$maxDateMonth = $maxRow['Month'];
		}	
		error_log("[getNetWorth] MAX Y[".$maxDateYear."] M[".$maxDateMonth."]");

		// loop through all the months to retrieve the balance.  This data aggregated and returned to the caller.
		$accountArray = array();	
		
		// BEN - Debug
		// for ( $yr = $maxDateYear; $yr <= $maxDateYear; $yr++ ) {
		// BEN - End Debug
		for ( $yr = $minDateYear; $yr <= $maxDateYear; $yr++ ) {
			for ( $mth = 1; $mth <= 12; $mth++ ) {
				if ( $yr == $minDateYear && $mth < $minDateMonth ) continue;
				if ( $yr == $maxDateYear && $mth > $maxDateMonth ) continue;
				// error_log("[getNetWorth] In Date[" . $mth . "/" . $yr . "]");
				$balQuery = 	"select max(ar.regdate) as Date , sum(ar.amount) as Amount " .
						"from accountregistry ar " .
						"inner join " .
						"( " .
							"select max(regdate) maxdate, accountid " .
							"from accountregistry " .
							"where concat(year(regdate),quarter(regdate)) <= concat(year(str_to_date('" . $yr . "-" . $mth . "-01','%Y-%m-%d'))".
							",quarter(str_to_date('" . $yr . "-" . $mth . "-01','%Y-%m-%d'))) " .
							"group by accountid " .
						") t on t.accountid = ar.accountid " .
						"and t.maxdate = ar.regdate";
				// error_log("[getNetWorth] Stmt [" . $balQuery . "]");
				
				$result = $dbHandle->query( $balQuery );
				foreach ( $result as $row ) {
					// error_log("[getNetWorth] resDate[" .  $row['Date'] . "]:Amount [" . $row['Amount'] . "]" ); 
					array_push( $accountArray, array('Date'=>$row['Date'], 'Amount'=>$row['Amount']));
				}	
			}
		}
		error_log("[getNetWorth] Debug: Competed processing data.");
		$dbHandle = null;
		// DEBUGGING:
		// $db_act_array = array();
		// array_push( $db_act_array, array('Date'=>'06/28/2015', 'Amount'=>'69.69'));
		// success( $db_act_array );	
		// ORIG
		success( $accountArray );
		error_log("getNetWorth()-->");
	}


	function getDistribution() {
		$query = 	"select ac.description as Cat, sum(amount) as Amount" .
					" from accountregistry ar " .
					" left outer join account a on a.id=ar.accountid " .
					" left outer join accountCategory ac on ac.id=a.categoryid " .
					" where regdate = ( " .
					"   select max( regdate ) " .
					"   from accountregistry ar2 " .
					"   where ar.accountid = ar2.accountid " .
					"  ) " .
					" group by categoryid ";
		$result = db_executeQuery( $query );
		$accountArray = array();	
		foreach ( $result as $row ) {
			array_push( $accountArray, array('Cat'=>$row['Cat'], 'Amount'=>$row['Amount']));
		}
		success( $accountArray );
	}


	function getAccountData( $acctId ) {

		$query = 	"select id as 'Id', accountid as 'AccountId', amount as 'Amount', regdate as 'Date'".
					" from accountregistry ".
					" where accountid=".$acctId.
					" order by regdate asc";

		$result = db_executeQuery( $query );

		$accountArray = array();	
		foreach ( $result as $row ) {
			array_push( $accountArray, array('Id'=>$row['Id'], 'Account'=>$row['AccountId'], 'Amount'=>$row['Amount'],
				'Date'=>$row['Date'] ));
		}

		success( $accountArray );
	}

	function db_connect() {
		$hostname = '127.0.0.1';
		$dbname = 'MY_MONEY';
		$dsn = 'mysql:dbname='.$dbname.';host='.$hostname;
		$user = 'money_db_user';
		$pw = 'money_db_password';

		try {
			$dbh = new PDO( $dsn, $user, $pw );
			return $dbh;
		} catch (PDOException $e) {
			echo 'Connection Failed: ' . $e->getMessage();
		}
	}

	function db_executeInsert( $query ) {

		try {
			$dbh = db_connect();
		} catch (PDOException $e) {
			echo 'Connection Failed: ' . $e->getMessage();
		}

		try {
			$result = $dbh->exec( $query );
			//print("Inserted $result records.\n");
			if ($result === false) {
				echo 'Query returned false.  Query failed.';
			}
			return $result;
		} catch (PDOException $qe) {
			echo 'Query failed: ' . $qe->getMessage();
		}
	}

	function db_executeQuery( $query ) {

		try {
			$dbh = db_connect();
		} catch (PDOException $e) {
			echo 'Connection Failed: ' . $e->getMessage();
		}

		try {
			$result = $dbh->query( $query );
			if (!$result) {
				echo 'Query returned false.  Query failed.';
			}
			return $result;
		} catch (PDOException $qe) {
			echo 'Query failed: ' . $qe->getMessage();
		}
	}

	function fail($msg) {
		error_log("ERROR: Data [" . serialize($msg) . "]" );
		die( json_encode(array('status'=>'fail', 'message'=>$msg)));
	}

	function success($msg) {
		error_log("DEBUG: -->success");
		// msg is an array.
		$msg_str = serialize( $msg );
		error_log("DEBUG: Data [" . $msg_str . "]" );
		$json_msg = json_encode(array('status'=>'success','message'=>$msg));
		error_log("DEBUG: json_msg [" . serialize( $json_msg ) . "]" );
		// die( $json_msg );
		die( json_encode(array('status'=>'success','message'=>$msg)));
	}


?>
