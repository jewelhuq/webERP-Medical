<?php

/* $Id$*/

include('includes/SQL_CommonFunctions.inc');

include('includes/session.inc');
$title = _('Supplier Inquiry');
include('includes/header.inc');

// This is already linked from the menu
//echo '<a href='" . $rootpath . '/SelectSupplier.php'>" . _('Back to Suppliers') . '</a><br />';

// always figure out the SQL required from the inputs available

if(!isset($_GET['SupplierID']) AND !isset($_SESSION['SupplierID'])){
	echo '<br />' . _('To display the enquiry a Supplier must first be selected from the Supplier selection screen') .
		  '<br /><div class="centre"><a href="'. $rootpath . '/SelectSupplier.php">' . _('Select a Supplier to Inquire On') . '</a></div>';
	exit;
} else {
	if (isset($_GET['SupplierID'])){
		$_SESSION['SupplierID'] = $_GET['SupplierID'];
	}
	$SupplierID = $_SESSION['SupplierID'];
}

if (isset($_GET['FromDate'])){
	$_POST['TransAfterDate']=$_GET['FromDate'];
}
if (!isset($_POST['TransAfterDate']) OR !Is_Date($_POST['TransAfterDate'])) {

	$_POST['TransAfterDate'] = Date($_SESSION['DefaultDateFormat'],Mktime(0,0,0,Date("m")-12,Date("d"),Date("Y")));
}


$SQL = "SELECT suppliers.suppname,
		suppliers.currcode,
		currencies.currency,
		paymentterms.terms,
		SUM(supptrans.ovamount + supptrans.ovgst - supptrans.alloc) AS balance,
		SUM(CASE WHEN paymentterms.daysbeforedue > 0 THEN
			CASE WHEN (TO_DAYS(Now()) - TO_DAYS(supptrans.trandate)) >= paymentterms.daysbeforedue
			THEN supptrans.ovamount + supptrans.ovgst - supptrans.alloc ELSE 0 END
		ELSE
			CASE WHEN TO_DAYS(Now()) - TO_DAYS(DATE_ADD(DATE_ADD(supptrans.trandate, " . INTERVAL('1', 'MONTH') . "), " . INTERVAL('(paymentterms.dayinfollowingmonth - DAYOFMONTH(supptrans.trandate))', 'DAY') . ")) >= 0 THEN supptrans.ovamount + supptrans.ovgst - supptrans.alloc ELSE 0 END
		END) AS due,
		SUM(CASE WHEN paymentterms.daysbeforedue > 0  THEN
			CASE WHEN (TO_DAYS(Now()) - TO_DAYS(supptrans.trandate)) > paymentterms.daysbeforedue
					AND (TO_DAYS(Now()) - TO_DAYS(supptrans.trandate)) >= (paymentterms.daysbeforedue + " . $_SESSION['PastDueDays1'] . ")
			THEN supptrans.ovamount + supptrans.ovgst - supptrans.alloc ELSE 0 END
		ELSE
			CASE WHEN (TO_DAYS(Now()) - TO_DAYS(DATE_ADD(DATE_ADD(supptrans.trandate, " . INTERVAL('1','MONTH') . "), " . INTERVAL('(paymentterms.dayinfollowingmonth - DAYOFMONTH(supptrans.trandate))', 'DAY') .")) >= " . $_SESSION['PastDueDays1'] . ")
			THEN supptrans.ovamount + supptrans.ovgst - supptrans.alloc ELSE 0 END
		END) AS overdue1,
		Sum(CASE WHEN paymentterms.daysbeforedue > 0 THEN
			CASE WHEN TO_DAYS(Now()) - TO_DAYS(supptrans.trandate) > paymentterms.daysbeforedue AND TO_DAYS(Now()) - TO_DAYS(supptrans.trandate) >= (paymentterms.daysbeforedue + " . $_SESSION['PastDueDays2'] . ")
			THEN supptrans.ovamount + supptrans.ovgst - supptrans.alloc ELSE 0 END
		ELSE
			CASE WHEN (TO_DAYS(Now()) - TO_DAYS(DATE_ADD(DATE_ADD(supptrans.trandate, " . INTERVAL('1','MONTH') . "), " . INTERVAL('(paymentterms.dayinfollowingmonth - DAYOFMONTH(supptrans.trandate))', 'DAY') . ")) >= " . $_SESSION['PastDueDays2'] . ")
			THEN supptrans.ovamount + supptrans.ovgst - supptrans.alloc ELSE 0 END
		END ) AS overdue2
		FROM suppliers,
     			paymentterms,
     			currencies,
     			supptrans
		WHERE suppliers.paymentterms = paymentterms.termsindicator
     		AND suppliers.currcode = currencies.currabrev
     		AND suppliers.supplierid = '" . $SupplierID . "'
     		AND suppliers.supplierid = supptrans.supplierno
		GROUP BY suppliers.suppname,
      			currencies.currency,
      			paymentterms.terms,
      			paymentterms.daysbeforedue,
      			paymentterms.dayinfollowingmonth";

$ErrMsg = _('The supplier details could not be retrieved by the SQL because');
$DbgMsg = _('The SQL that failed was');

$SupplierResult = DB_query($SQL, $db, $ErrMsg, $DbgMsg);

if (DB_num_rows($SupplierResult) == 0){

	/*Because there is no balance - so just retrieve the header information about the Supplier - the choice is do one query to get the balance and transactions for those Suppliers who have a balance and two queries for those who don't have a balance OR always do two queries - I opted for the former */

	$NIL_BALANCE = True;

	$SQL = "SELECT suppliers.suppname,
			suppliers.currcode,
			currencies.currency,
			paymentterms.terms
		FROM suppliers,
	     		paymentterms,
	     		currencies
		WHERE suppliers.paymentterms = paymentterms.termsindicator
		AND suppliers.currcode = currencies.currabrev
		AND suppliers.supplierid = '" . $SupplierID . "'";

	$ErrMsg = _('The supplier details could not be retrieved by the SQL because');
	$DbgMsg = _('The SQL that failed was');

	$SupplierResult = DB_query($SQL, $db, $ErrMsg, $DbgMsg);

} else {
	$NIL_BALANCE = False;
}

$SupplierRecord = DB_fetch_array($SupplierResult);

if ($NIL_BALANCE == True){
	$SupplierRecord['balance'] = 0;
	$SupplierRecord['due'] = 0;
	$SupplierRecord['overdue1'] = 0;
	$SupplierRecord['overdue2'] = 0;
}

echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/supplier.png" title="' ._('Supplier') . '" alt="" />' . ' ' . _('Supplier') . ' : ' . $SupplierRecord['suppname'] . ' - (' . _('All amounts stated in') .
	  ' ' . $SupplierRecord['currency'] . ')<br /><br />' . _('Terms') . ': ' . $SupplierRecord['terms'] . '</p>';

if (isset($_GET['HoldType']) AND isset($_GET['HoldTrans'])){

	if ($_GET['HoldStatus'] == _('Hold')){
		$SQL = "UPDATE supptrans
					SET hold=1
				WHERE type='" . $_GET['HoldType'] . "'
					AND transno='" . $_GET['HoldTrans'] . "'";
	} elseif ($_GET['HoldStatus'] == _('Release')){
		$SQL = "UPDATE supptrans
					SET hold=0
				WHERE type='" . $_GET['HoldType'] . "'
					AND transno='" . $_GET['HoldTrans'] . "'";
	}

	$ErrMsg = _('The Supplier Transactions could not be updated because');
	$DbgMsg = _('The SQL that failed was');
	$UpdateResult = DB_query($SQL, $db, $ErrMsg, $DbgMsg);

}

echo '<table width=90% class="selection">
		<tr><th>' . _('Total Balance') . '</th>
			<th>' . _('Current') . '</th>
			<th>' . _('Now Due') . '</th>
			<th>' . $_SESSION['PastDueDays1'] . '-' . $_SESSION['PastDueDays2'] . ' ' . _('Days Overdue') . '</th>
			<th>' . _('Over') . ' ' . $_SESSION['PastDueDays2'] . ' ' . _('Days Overdue') . '</th>
		</tr>';

echo '<tr>	<td class="number">' . locale_money_format($SupplierRecord['balance'],$SupplierRecord['currcode']) . '</td>
			<td class="number">' . locale_money_format(($SupplierRecord['balance'] - $SupplierRecord['due']),$SupplierRecord['currcode']) . '</td>
			<td class="number">' . locale_money_format(($SupplierRecord['due']-$SupplierRecord['overdue1']),$SupplierRecord['currcode']) . '</td>
			<td class="number">' . locale_money_format(($SupplierRecord['overdue1']-$SupplierRecord['overdue2']) ,$SupplierRecord['currcode']) . '</td>
			<td class="number">' . locale_money_format($SupplierRecord['overdue2'],$SupplierRecord['currcode']) . '</td>
		</tr></table>';

echo '<br /><form action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post">';
echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
echo '<table class="selection">
		<tr>';
echo '<td>' . _('Show all transactions after') . '</td><td>: ' .
		'<input type="text" class="date" alt="'.$_SESSION['DefaultDateFormat'].'" name="TransAfterDate" value="' . $_POST['TransAfterDate'] . '" maxlength="10" size="10" /></td>
	  <td><button type="submit" name="Refresh Inquiry">' . _('Refresh Inquiry') . '</button></td></tr>
	  </table></form>
	  <br />';

$DateAfterCriteria = FormatDateForSQL($_POST['TransAfterDate']);

$SQL = "SELECT supptrans.id,
				systypes.typename,
				supptrans.type,
				supptrans.transno,
				supptrans.trandate,
				supptrans.suppreference,
				supptrans.rate,
				suppliers.currcode,
				(supptrans.ovamount + supptrans.ovgst) AS totalamount,
				supptrans.alloc AS allocated,
				supptrans.hold,
				supptrans.settled,
				supptrans.transtext,
				supptrans.supplierno
			FROM supptrans
			LEFT JOIN systypes
				ON supptrans.type = systypes.typeid
			LEFT JOIN suppliers
				ON suppliers.supplierid=supptrans.supplierno
			WHERE supptrans.supplierno = '" . $SupplierID . "'
				AND supptrans.trandate >= '" . $DateAfterCriteria . "'
			ORDER BY supptrans.trandate";

$ErrMsg = _('No transactions were returned by the SQL because');
$DbgMsg = _('The SQL that failed was');

$TransResult = DB_query($SQL, $db, $ErrMsg, $DbgMsg);

if (DB_num_rows($TransResult) == 0){
	prnMsg( _('There are no transactions to display since') . ' ' . $_POST['TransAfterDate'], 'info');
	include('includes/footer.inc');
	exit;
}

/*show a table of the transactions returned by the SQL */


echo '<table width="90%" cellpadding="2" class="selection">';
$TableHeader = '<tr>
					<th>' . _('Trans') . ' #' . '</th>
					<th>' . _('Type') . '</th>
					<th>' . _('Supplier Ref') . '</th>
					<th>' . _('Date') . '</th>
					<th>' . _('Total') . '</th>
					<th>' . _('Allocated') . '</th>
					<th>' . _('Balance') . '</th>
					<th>' . _('Comments') . '</th>
					<th>' . _('More Info') . '</th>
					<th>' . _('More Info') . '</td>
				</tr>';

echo $TableHeader;

$j = 1;
$k = 0; //row colour counter
while ($myrow=DB_fetch_array($TransResult)) {

	if ($myrow['hold'] == 0 AND $myrow['settled'] == 0){
		$HoldValue = _('Hold');
	} elseif ($myrow['settled'] == 1) {
		$HoldValue = '';
	}else {
		$HoldValue = _('Release');
	}
	if ($myrow['hold'] == 1){
//		echo '<tr bgcolor="#DD99BB">';
	}elseif ($k == 1){
		echo '<tr class="EvenTableRows">';
		$k = 0;
	} else {
		echo '<tr class="OddTableRows">';
		$k = 1;
	}

	$FormatedTranDate = ConvertSQLDate($myrow['trandate']);

	if ($myrow['type'] == 20){ /*Show a link to allow GL postings to be viewed but no link to allocate */

		if ($_SESSION['CompanyRecord']['gllink_creditors'] == True){
			if ($myrow['totalamount'] - $myrow['allocated'] == 0){

			/*The trans is settled so don't show option to hold */

				echo '<td>' . $myrow['transno'] . '</td>
						<td>' . $myrow['typename'] . '</td>
						<td>' . $myrow['suppreference'] . '</td>
						<td>' . ConvertSQLDate($myrow['trandate']) . '</td>
						<td class="number">' . locale_money_format($myrow['totalamount'],$myrow['currcode']) .'</td>
						<td class="number">' . locale_money_format($myrow['allocated'],$myrow['currcode']) . '</td>
						<td class="number">' . locale_money_format($myrow['totalamount']-$myrow['allocated'],$myrow['currcode']) . '</td>
						<td align="left">' . $myrow['transtext'] . '</td>
						<td><a target="_blank" href="' . $rootpath . '/GLTransInquiry.php?TypeID=' . $myrow['type'] . '&TransNo=' . $myrow['transno'] .'">' . _('View GL Postings') . '</a></td>
						<td><a href="' . $rootpath . '/PaymentAllocations.php?SuppID=' . $myrow['supplierno'] . '&InvID=' . $myrow['suppreference'] .'">' . _('View Payments') . '</a></td></tr>';
			} else {
				echo '<td>' . $myrow['transno'] . '</td>
						<td>' . $myrow['typename'] . '</td>
						<td>' . $myrow['suppreference'] . '</td>
						<td>' . ConvertSQLDate($myrow['trandate']) . '</td>
						<td class="number">' . locale_money_format($myrow['totalamount'],$myrow['currcode']) . '</td>
						<td class="number">' . locale_money_format($myrow['allocated'],$myrow['currcode']) . '</td>
						<td class="number">' . locale_money_format($myrow['totalamount'] - $myrow['allocated'],$myrow['currcode']) .'</td>
						<td align="left">' . $myrow['transtext'] . '</td>';
				$authsql="SELECT offhold
							FROM purchorderauth
							WHERE userid='".$_SESSION['UserID'] . "'
								AND currabrev='".$SupplierRecord['currcode']."'";
				$authresult=DB_query($authsql, $db);
				$authrow=DB_fetch_array($authresult);
				if ($authrow[0]==0) {
					echo '<td><a href="'.htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8').'?HoldType='.$myrow['type'].'&HoldTrans='.$myrow['transno'].'&HoldStatus='.$HoldValue.'&FromDate='.$_POST['TransAfterDate'].'">'.$HoldValue.'</a></td>';
				} else {
					if ($HoldValue==_('Release')) {
						echo '<td>'.$HoldValue.'</a></td>';
					} else {
						echo '<td><a href="'.htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8').'?HoldType='.$myrow['type'].'&HoldTrans='.$myrow['transno'].'&HoldStatus='.$HoldValue.'&FromDate='.$_POST['TransAfterDate'].'">'.$HoldValue.'</a></td>';
					}
				}
				echo '<td><a target="_blank" href="'.$rootpath.'/GLTransInquiry.php?TypeID='.$myrow['type'].'&TransNo='.$myrow['transno'].'">' ._('View GL Postings') . '</a></td></tr>';
			}
		} else {

			if ($myrow['totalamount'] - $myrow['allocated'] == 0){

			/*The trans is settled so don't show option to hold */

				printf('<td>%s</td>
						<td>%s</td>
						<td>%s</td>
						<td>%s</td>
						<td class="number">%s</td>
						<td class="number">%s</td>
						<td class="number">%s</td>
						<td align=left>%s</td>
						</td></tr>',
						$myrow['transno'],
						$myrow['typename'],
						$myrow['suppreference'],
						ConvertSQLDate($myrow['trandate']),
						locale_money_format($myrow['totalamount'],$myrow['currcode']),
						locale_money_format($myrow['allocated'],$myrow['currcode']),
						locale_money_format($myrow['totalamount'] - $myrow['allocated'],$myrow['currcode']),
						$myrow['transtext']);

			} else {

				printf('<td>%s</td>
						<td>%s</td>
						<td>%s</td>
						<td>%s</td>
						<td class="number">%s</td>
						<td class="number">%s</td>
						<td class="number">%s</td>
						<td align=left>%s</td>
						<td><a href="%s?HoldType=%s&HoldTrans=%s&HoldStatus=%s&FromDate=%s">%s</a></td>
						<td><a href="%s/PaymentAllocations.php?SuppID=%s&InvID=%s">' . _('View Payments') . '</a></tr>',
						$myrow['transno'],
						$myrow['typename'],
						$myrow['suppreference'],
						ConvertSQLDate($myrow['trandate']),
						locale_money_format($myrow['totalamount'],$myrow['currcode']),
						locale_money_format($myrow['allocated'],$myrow['currcode']),
						locale_money_format($myrow['totalamount']-$myrow['allocated'],$myrow['currcode']),
						$myrow['transtext'],
						htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'),
						$myrow['type'],
						$myrow['transno'],
						$HoldValue,
						$_POST['TransAfterDate'],
						$HoldValue,
						$rootpath,
						$myrow['supplierno'],
						$myrow['suppreference']);
			}
		}

	} else { /*its a credit note or a payment */

		if ($_SESSION['CompanyRecord']['gllink_creditors'] == True){

			printf('<td>%s</td>
					<td>%s</td>
					<td>%s</td>
					<td>%s</td>
					<td class="number">%s</td>
					<td class="number">%s</td>
					<td class="number">%s</td>
					<td align=left>%s</td>
					<td><a href="%s/SupplierAllocations.php?AllocTrans=%s">' .
					  _('View Allocations') . '</a></td>
					<td><a target="_blank" href="%s/GLTransInquiry.php?TypeID=%s&TransNo=%s">' .
					  _('View GL Postings') . '</a></td>
					</tr>',
					$myrow['transno'],
					$myrow['typename'],
					$myrow['suppreference'],
					ConvertSQLDate($myrow['trandate']),
					locale_money_format($myrow['totalamount'],$myrow['currcode']),
					locale_money_format($myrow['allocated'],$myrow['currcode']),
					locale_money_format($myrow['totalamount']-$myrow['allocated'],$myrow['currcode']),
					$myrow['transtext'],
					$rootpath,
					$myrow['id'],
					$rootpath,
					$myrow['type'],
					$myrow['transno'] );

		} else { /*Not linked to GL */

			printf('<td>%s</td>
				<td>%s</td>
				<td>%s</td>
				<td>%s</td>
				<td class="number">%s</td>
				<td class="number">%s</td>
				<td class="number">%s</td>
				<td align=left>%s</td>
				<td><a href="%s/SupplierAllocations.php?AllocTrans=%s">' . _('View Allocations') . '</a></td>
				</tr>',
				$myrow['transno'],
				$myrow['typename'],
				$myrow['suppreference'],
				ConvertSQLDate($myrow['trandate']),
				locale_money_format($myrow['totalamount'],$myrow['currcode']),
				locale_money_format($myrow['allocated'],$myrow['currcode']),
				locale_money_format(($myrow['totalamount'] - $myrow['allocated']),$myrow['currcode']),
				$myrow['transtext'],
				$rootpath,
				$myrow['id']);

		}
	}


//end of page full new headings if

}

//end of while loop

echo '</table>';
include('includes/footer.inc');
?>