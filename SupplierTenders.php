<?php
/* $Id$*/

//$PageSecurity = 9;

include('includes/DefineOfferClass.php');
include('includes/session.inc');
$title = _('Supplier Tendering');
include('includes/header.inc');

$Maximum_Number_Of_Parts_To_Show=50;

if (isset($_GET['TenderType'])) {
	$_POST['TenderType']=$_GET['TenderType'];
}

if (!isset($_POST['SupplierID'])) {
	$sql="SELECT supplierid FROM www_users WHERE userid='".$_SESSION['UserID']."'";
	$result=DB_query($sql, $db);
	$myrow=DB_fetch_array($result);
	if ($myrow['supplierid']=='') {
		prnMsg(_('This functionality can only be accessed via a supplier login.'), 'warning');
		include('includes/footer.inc');
		exit;
	} else {
		$_POST['SupplierID']=$myrow['supplierid'];
	}
}

if (isset($_GET['Delete'])) {
	$_POST['SupplierID']=$_SESSION['offer']->SupplierID;
	$_POST['TenderType']=$_GET['Type'];
	$_SESSION['offer']->remove_from_offer($_GET['Delete']);
}

$sql="SELECT suppname,
			currcode
		FROM suppliers
		WHERE supplierid='".$_POST['SupplierID']."'";
$result=DB_query($sql, $db);
$myrow=DB_fetch_array($result);
$Supplier=$myrow['suppname'];
$Currency=$myrow['currcode'];

if (isset($_POST['Confirm'])) {
	$_SESSION['offer']->Save($db);
	$_SESSION['offer']->EmailOffer();
	$sql="UPDATE tendersuppliers
			SET responded=1
			WHERE supplierid='" . $_SESSION['offer']->SupplierID . "'
			AND tenderid='" . $_SESSION['offer']->TenderID . "'";
	$result=DB_query($sql, $db);
}

if (isset($_POST['Process'])) {
	if (isset($_SESSION['offer'])) {
		unset($_SESSION['offer']);
	}
	$_SESSION['offer']=new Offer($_POST['SupplierID']);
	$_SESSION['offer']->TenderID=$_POST['Tender'];
	$_SESSION['offer']->CurrCode=$Currency;
	$LineNo=0;
	foreach ($_POST as $key=>$value) {
		if (substr($key, 0, 8)=='Quantity') {
			$ItemCode=substr($key, 8, strlen($key));
			$Quantity=$value;
			$Price=$_POST['Price'.$ItemCode];
			$_SESSION['offer']->add_to_offer(
				$LineNo,
				$ItemCode,
				$Quantity,
				$_POST['ItemDescription'.$ItemCode],
				$Price,
				$_POST['UOM'.$ItemCode],
				$_POST['DecimalPlaces'.$ItemCode],
				$_POST['RequiredByDate'.$ItemCode]);
			$LineNo++;
		}
	}
	echo '<p class="page_title_text"><img src="' . $rootpath . '/css/' . $theme . '/images/supplier.png" title="' .
		_('Tenders') . '" alt="" />' . ' ' . _('Confirm the Response For Tender') . ' ' . $_SESSION['offer']->TenderID .'</p>';
	echo '<form action="' . $_SERVER['PHP_SELF'] . '" method=post>';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	echo '<table class="selection">';
	echo '<input type="hidden" name="TenderType" value="3" />';
	$LocationSQL="SELECT tenderid,
						locations.locationname,
						address1,
						address2,
						address3,
						address4,
						address5,
						address6,
						telephone
					FROM tenders
					LEFT JOIN locations
					ON tenders.location=locations.loccode
					WHERE closed=0
					AND tenderid='".$_SESSION['offer']->TenderID."'";
	$LocationResult=DB_query($LocationSQL, $db);
	$MyLocationRow=DB_fetch_row($LocationResult);
	echo '<tr><td valign="top" style="background-color:#cccce5">' . _('Deliver To') . ':</td><td valign="top" style="background-color:#cccce5">';
	for ($i=1; $i<8; $i++) {
		if ($MyLocationRow[$i]!='') {
			echo $MyLocationRow[$i] . '<br />';
		}
	}
	echo '</td>';
	echo '<th colspan="8" style="vertical-align:top"><font size="2" color="navy">' . _('Tender Number') . ': ' .$_SESSION['offer']->TenderID . '</font></th>';
	echo '<input type="hidden" value="' . $_SESSION['offer']->TenderID . '" name="Tender" />';
	echo '<tr><th>' . stripslashes($_SESSION['CompanyRecord']['coyname']) . '<br />' . _('Item Code') . '</th>';
	echo '<th>' . _('Item Description') . '</th>';
	echo '<th>' . _('Quantity') . '<br />' . _('Offered') . '</th>';
	echo '<th>' . $Supplier . '<br />' . _('Units of Measure') . '</th>';
	echo '<th>' . _('Currency') . '</th>';
	echo '<th>' . $Supplier . '<br />' . _('Price') . '</th>';
	echo '<th>' . _('Line Value') . '</th>';
	echo '<th>' . _('Delivery By') . '</th>';
	foreach ($_SESSION['offer']->LineItems as $LineItem)  {
		echo '<tr><td>' . $LineItem->StockID . '</td>';
		echo '<td>' . $LineItem->ItemDescription . '</td>';
		echo '<td class="number"> ' .number_format($LineItem->Quantity, $LineItem->DecimalPlaces) . '</td>';
		echo '<td>' . $LineItem->Units . '</td>';
		echo '<td>' . $_SESSION['offer']->CurrCode . '</td>';
		echo '<td class="number">' . number_format($LineItem->Price, 2) . '</td>';
		echo '<td class="number">' . number_format($LineItem->Price*$LineItem->Quantity, 2) . '</td>';
		echo '<td>' . $LineItem->ExpiryDate . '</td>';
	}
	echo '</table><br />';
	echo '<div class="centre"><input type="submit" name="Confirm" value="' . _('Confirm and Send Email') . '" /><br />';
	echo '<br /><input type="submit" name="Cancel" value="' . _('Cancel Offer') . '" /></div>';
	echo '</form>';
	include('includes/footer.inc');
	exit;
}

/* If the supplierID is set then it must be a login from the supplier but if nothing else is
 * set then the supplier must have just logged in so show them the choices.
 */
if (isset($_POST['SupplierID']) and empty($_POST['TenderType']) and empty($_POST['Search']) and empty($_POST['NewItem']) and empty($_GET['Delete'])) {
	if (isset($_SESSION['offer'])) {
		unset($_SESSION['offer']);
	}
	echo '<form method="post" action="' . $_SERVER['PHP_SELF'] . '">';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	echo '<p class="page_title_text"><img src="' . $rootpath . '/css/' . $theme . '/images/supplier.png" title="' .
		_('Tenders') . '" alt="" />' . ' ' . _('Create or View Offers from') . ' '.$Supplier.'</p>';
	echo '<table class=selection>';
	echo'<tr><td>'._('Select option for tendering').'</td>';
	echo '<td><select name=TenderType>';
	echo '<option value=1>'._('View or Amend outstanding offers from').' '.$Supplier .'</option>';
	echo '<option value=2>'._('Create a new offer from').' '.$Supplier .'</option>';
	echo '<option value=3>'._('View any open tenders without an offer from').' '.$Supplier .'</option>';
	echo '</select></td></tr>';
	echo '<input type=hidden name=SupplierID value="'.$_POST['SupplierID'].'" />';
	echo '<tr><td colspan=2><div class=centre><input type="submit" name="submit" value="' . _('Select') . '"></div></td></tr>';
	echo '</table></form>';
}

if (isset($_POST['NewItem']) and !isset($_POST['Refresh'])) {
	foreach ($_POST as $key => $value) {
		if (substr($key,0,3)=='qty') {
			$StockID=substr($key,3);
			$Quantity=$value;
		}
		if (substr($key,0,5)=='price') {
			$Price=$value;
		}
		if (substr($key,0,3)=='uom') {
			$UOM=$value;
		}
		if (isset($UOM)) {
			$sql="SELECT description, decimalplaces FROM stockmaster WHERE stockid='".$StockID."'";
			$result=DB_query($sql, $db);
			$myrow=DB_fetch_array($result);
			$_SESSION['offer']->add_to_offer(
				$_SESSION['offer']->LinesOnOffer,
				$StockID,
				$Quantity,
				$myrow['description'],
				$Price,
				$UOM,
				$myrow['decimalplaces'],
				DateAdd(date($_SESSION['DefaultDateFormat']),'m',3));
			unset($UOM);
		}
	}
}

if (isset($_POST['Refresh']) and !isset($_POST['NewItem'])) {
	foreach ($_POST as $key => $value) {
		if (substr($key,0,3)=='qty') {
			$LineNo=substr($key,3);
			$Quantity=$value;
		}
		if (substr($key,0,5)=='price') {
			$Price=$value;
		}
		if (substr($key,0,10)=='expirydate') {
			$ExpiryDate=$value;
		}
		if (isset($ExpiryDate)) {
			$_SESSION['offer']->update_offer_item(
				$LineNo,
				$Quantity,
				$Price,
				$ExpiryDate);
			unset($ExpiryDate);
		}
	}
}

if (isset($_POST['Update'])) {
	foreach ($_POST as $key => $value) {
		if (substr($key,0,3)=='qty') {
			$LineNo=substr($key,3);
			$Quantity=$value;
		}
		if (substr($key,0,5)=='price') {
			$Price=$value;
		}
		if (substr($key,0,10)=='expirydate') {
			$ExpiryDate=$value;
		}
		if (isset($ExpiryDate)) {
			$_SESSION['offer']->update_offer_item(
				$LineNo,
				$Quantity,
				$Price,
				$ExpiryDate);
			unset($ExpiryDate);
		}
	}
	$_SESSION['offer']->Save($db, 'Yes');
	$_SESSION['offer']->EmailOffer();
	unset($_SESSION['offer']);
	include('includes/footer.inc');
	exit;
}

if (isset($_POST['Save'])) {
	foreach ($_POST as $key => $value) {
		if (substr($key,0,3)=='qty') {
			$LineNo=substr($key,3);
			$Quantity=$value;
		}
		if (substr($key,0,5)=='price') {
			$Price=$value;
		}
		if (substr($key,0,10)=='expirydate') {
			$ExpiryDate=$value;
		}
		if (isset($ExpiryDate)) {
			$_SESSION['offer']->update_offer_item(
				$LineNo,
				$Quantity,
				$Price,
				$ExpiryDate);
			unset($ExpiryDate);
		}
	}
	$_SESSION['offer']->Save($db);
	$_SESSION['offer']->EmailOffer();
	unset($_SESSION['offer']);
	include('includes/footer.inc');
	exit;
}

/*The supplier has chosen option 1
 */
if (isset($_POST['TenderType']) and $_POST['TenderType']==1 and !isset($_POST['Refresh'])) {
	$sql="SELECT offers.offerid,
				offers.stockid,
				stockmaster.description,
				offers.quantity,
				offers.uom,
				offers.price,
				offers.expirydate,
				stockmaster.decimalplaces
			FROM offers
			LEFT JOIN stockmaster
				ON offers.stockid=stockmaster.stockid
			WHERE offers.supplierid='".$_POST['SupplierID']."'";
	$result=DB_query($sql, $db);
	$_SESSION['offer']=new Offer($_POST['SupplierID']);
	$_SESSION['offer']->CurrCode=$Currency;
	while ($myrow=DB_fetch_array($result)) {
		$_SESSION['offer']->add_to_offer(
				$myrow['offerid'],
				$myrow['stockid'],
				$myrow['quantity'],
				$myrow['description'],
				$myrow['price'],
				$myrow['uom'],
				$myrow['decimalplaces'],
				ConvertSQLDate($myrow['expirydate']));
	}
}

if ($_POST['TenderType']!=3 and isset($_SESSION['offer']) and $_SESSION['offer']->LinesOnOffer>0 or isset($_POST['Update'])) {
	echo '<form method="post" action="' . $_SERVER['PHP_SELF'] . '">';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	echo '<p class="page_title_text"><img src="' . $rootpath . '/css/' . $theme . '/images/supplier.png" title="' .
		_('Search') . '" alt="" />' . ' ' . _('Items to offer from').' '.$Supplier .'</p>';
	echo '<table>';
	echo '<tr>';
	echo '<th>'._('Stock ID').'</th>';
	echo '<th>'._('Description').'</th>';
	echo '<th>'._('Quantity').'</th>';
	echo '<th>'._('UOM').'</th>';
	echo '<th>'._('Price').' ('.$Currency.')</th>';
	echo '<th>'._('Line Total').' ('.$Currency.')</th>';
	echo '<th>'._('Expiry Date').'</th>';
	echo '</tr>';
	$k=0;
	foreach ($_SESSION['offer']->LineItems as $LineItems) {
		if ($LineItems->Deleted==False) {
			if ($k==1){
				echo '<tr class="EvenTableRows">';
				$k=0;
			} else {
				echo '<tr class="OddTableRows">';
				$k=1;
			}
			if ($LineItems->ExpiryDate < date('Y-m-d')) {
				echo '<tr bgcolor=#F7A9A9>';
			}
			echo '<td>'.$LineItems->StockID.'</td>';
			echo '<td>'.$LineItems->ItemDescription.'</td>';
			echo '<td><input type=text class=number name="qty'.$LineItems->LineNo.'" value='.number_format($LineItems->Quantity,$LineItems->DecimalPlaces).'></td>';
			echo '<td>'.$LineItems->Units.'</td>';
			echo '<td><input type=text class=number name="price'.$LineItems->LineNo.'" value='.number_format($LineItems->Price,2,'.','').'></td>';
			echo '<td class=number>'.number_format($LineItems->Price*$LineItems->Quantity,2).'</td>';
			echo '<td><input type=text size=11 class=date alt='.$_SESSION['DefaultDateFormat'].' name="expirydate'.$LineItems->LineNo.'" value='.$LineItems->ExpiryDate.'></td>';
			echo "<td><a href='" . $_SERVER['PHP_SELF'] . "?" . SID . "Delete=" . $LineItems->LineNo . "&Type=" . $_POST['TenderType'] . "'>" . _('Remove') . "</a></td></tr>";
			echo '</tr>';
		}
	}
	echo '</table>';
	echo '<input type=hidden name=TenderType value="'.$_POST['TenderType'].'">';
	if ($_POST['TenderType']==1) {
		echo '<br /><div class="centre"><input type="submit" name="Update" value="Update offer">';
		echo '<input type="submit" name="Refresh" value="Refresh screen"></div>';
	} else if ($_POST['TenderType']==2) {
		echo '<br /><div class="centre"><input type="submit" name="Save" value="Save offer">';
		echo '<input type="submit" name="Refresh" value="Refresh screen"></div>';
	}
	echo '</form>';
}

/*The supplier has chosen option 2
 */
if (isset($_POST['TenderType']) and $_POST['TenderType']==2 and !isset($_POST['Search']) or isset($_GET['Delete'])) {
	if (!isset($_SESSION['offer'])) {
		$_SESSION['offer']=new Offer($_POST['SupplierID']);
	}
	echo '<form action="' . $_SERVER['PHP_SELF'] . '" method=post>';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	echo '<p class="page_title_text"><img src="' . $rootpath . '/css/' . $theme . '/images/magnifier.png" title="' .
		_('Search') . '" alt="" />' . ' ' . _('Search for Inventory Items') . '</p>';
	$sql = "SELECT categoryid,
			categorydescription
		FROM stockcategory
		ORDER BY categorydescription";
	$result = DB_query($sql, $db);
	if (DB_num_rows($result) == 0) {
		echo '<p><font size=4 color=red>' . _('Problem Report') . ':</font><br />' .
			_('There are no stock categories currently defined please use the link below to set them up');
		echo '<br /><a href="' . $rootpath . '/StockCategories.php">' . _('Define Stock Categories') . '</a></p>';
		exit;
	}
	echo '<table class=selection><tr>';
	echo '<td>' . _('In Stock Category') . ':';
	echo '<select name="StockCat">';
	if (!isset($_POST['StockCat'])) {
		$_POST['StockCat'] = "";
	}
	if ($_POST['StockCat'] == "All") {
		echo '<option selected value="All">' . _('All');
	} else {
		echo '<option value="All">' . _('All');
	}
	while ($myrow1 = DB_fetch_array($result)) {
		if ($myrow1['categoryid'] == $_POST['StockCat']) {
			echo '<option selected value="' . $myrow1['categoryid'] . '">' . $myrow1['categorydescription'];
		} else {
			echo '<option value="' . $myrow1['categoryid'] . '">' . $myrow1['categorydescription'];
		}
	}
	echo '</select>';
	echo '<td>' . _('Enter partial') . '<b> ' . _('Description') . '</b>:</td><td>';
	if (isset($_POST['Keywords'])) {
		echo '<input type="text" name="Keywords" value="' . $_POST['Keywords'] . '" size=20 maxlength=25>';
	} else {
		echo '<input type="text" name="Keywords" size=20 maxlength=25>';
	}
	echo '<input type="hidden" name="TenderType" value='.$_POST['TenderType'].'>';
	echo '<input type="hidden" name="SupplierID" value='.$_POST['SupplierID'].'>';
	echo '</td></tr><tr><td></td>';
	echo '<td><font size 3><b>' . _('OR') . ' ' . '</b></font>' . _('Enter partial') . ' <b>' . _('Stock Code') . '</b>:</td>';
	echo '<td>';
	if (isset($_POST['StockCode'])) {
		echo '<input type="text" name="StockCode" value="' . $_POST['StockCode'] . '" size=15 maxlength=18>';
	} else {
		echo '<input type="text" name="StockCode" size=15 maxlength=18>';
	}
	echo '</td></tr></table><br />';
	echo '<div class="centre"><input type=submit name="Search" value="' . _('Search Now') . '"></div><br /></form>';
	echo '<script  type="text/javascript">defaultControl(document.forms[0].StockCode);</script>';
	echo '</form>';
}

/*The supplier has chosen option 3
 */
if (isset($_POST['TenderType']) and $_POST['TenderType']==3 and !isset($_POST['Search']) or isset($_GET['Delete'])) {
	echo '<p class="page_title_text"><img src="' . $rootpath . '/css/' . $theme . '/images/supplier.png" title="' .
		_('Tenders') . '" alt="" />' . ' ' . _('Tenders Waiting For Offers').'</p>';
	$sql="SELECT DISTINCT tendersuppliers.tenderid,
				suppliers.currcode
			FROM tendersuppliers
			LEFT JOIN suppliers
			ON suppliers.supplierid=tendersuppliers.supplierid
			LEFT JOIN tenders
			ON tenders.tenderid=tendersuppliers.tenderid
			WHERE tendersuppliers.supplierid='" . $_POST['SupplierID'] . "'
			AND tenders.closed=0
			AND tendersuppliers.responded=0
			ORDER BY tendersuppliers.tenderid";
	$result=DB_query($sql, $db);
	echo '<table class="selection">';
	echo '<tr><th colspan="13"><font size="3" color="navy">' . _('Outstanding Tenders Waiting For Offer') . '</font></th></tr>';
	while ($myrow=DB_fetch_row($result)) {
		echo '<form action="' . $_SERVER['PHP_SELF'] . '" method=post>';
		echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
		echo '<input type="hidden" name="TenderType" value="3" />';
		$LocationSQL="SELECT tenderid,
							locations.locationname,
							address1,
							address2,
							address3,
							address4,
							address5,
							address6,
							telephone
						FROM tenders
						LEFT JOIN locations
						ON tenders.location=locations.loccode
						WHERE closed=0
						AND tenderid='".$myrow[0]."'";
		$LocationResult=DB_query($LocationSQL, $db);
		$MyLocationRow=DB_fetch_row($LocationResult);
		echo '<tr><td valign="top" style="background-color:#cccce5">' . _('Deliver To') . ':</td><td valign="top" style="background-color:#cccce5">';
		for ($i=1; $i<8; $i++) {
			if ($MyLocationRow[$i]!='') {
				echo $MyLocationRow[$i] . '<br />';
			}
		}
		echo '</td>';
		echo '<th colspan="8" style="vertical-align:top"><font size="2" color="navy">' . _('Tender Number') . ': ' .$myrow[0] . '</font></th>';
		echo '<input type="hidden" value="' . $myrow[0] . '" name="Tender" />';
		echo '<th><input type="submit" value="' . _('Process') . "\n" . _('Tender') . '" name="Process" /></th></tr>';
		$ItemSQL="SELECT tenderitems.tenderid,
						tenderitems.stockid,
						stockmaster.description,
						stockmaster.decimalplaces,
						purchdata.suppliers_partno,
						tenderitems.quantity,
						tenderitems.units,
						tenders.requiredbydate,
						purchdata.suppliersuom
					FROM tenderitems
					LEFT JOIN stockmaster
					ON tenderitems.stockid=stockmaster.stockid
					LEFT JOIN purchdata
					ON tenderitems.stockid=purchdata.stockid
					AND purchdata.supplierno='".$_POST['SupplierID']."'
					LEFT JOIN tenders
					ON tenders.tenderid=tenderitems.tenderid
					WHERE tenderitems.tenderid='" . $myrow[0] . "'";
		$ItemResult=DB_query($ItemSQL, $db);
		echo '<tr><th>' . stripslashes($_SESSION['CompanyRecord']['coyname']) . '<br />' . _('Item Code') . '</th>';
		echo '<th>' . _('Item Description') . '</th>';
		echo '<th>' . $Supplier . '<br />' . _('Item Code') . '</th>';
		echo '<th>' . _('Quantity') . '<br />' . _('Required') . '</th>';
		echo '<th>' . stripslashes($_SESSION['CompanyRecord']['coyname']) . '<br />' . _('Units of Measure') . '</th>';
		echo '<th>' . _('Required By') . '</th>';
		echo '<th>' . _('Quantity') . '<br />' . _('Offered') . '</th>';
		echo '<th>' . $Supplier . '<br />' . _('Units of Measure') . '</th>';
		echo '<th>' . _('Currency') . '</th>';
		echo '<th>' . $Supplier . '<br />' . _('Price') . '</th>';
		echo '<th>' . _('Delivery By') . '</th>';
		while ($MyItemRow=DB_fetch_array($ItemResult)) {
			echo '<tr><td>' . $MyItemRow['stockid'] . '</td>';
			echo '<td>' . $MyItemRow['description'] . '</td>';
			echo '<input type="hidden" name="ItemDescription'. $MyItemRow['stockid'] . '" value="' . $MyItemRow['description'] . '" />';
			echo '<td>' . $MyItemRow['suppliers_partno'] . '</td>';
			echo '<td class="number">' . number_format($MyItemRow['quantity'], $MyItemRow['decimalplaces']) . '</td>';
			echo '<td>' . $MyItemRow['units'] . '</td>';
			echo '<td>' . ConvertSQLDate($MyItemRow['requiredbydate']) . '</td>';
			if ($MyItemRow['suppliersuom']=='') {
				$MyItemRow['suppliersuom']=$MyItemRow['units'];
			}
			echo '<td><input type="text" class="number" size="10" name="Quantity'. $MyItemRow['stockid'] . '" value="' .
				number_format($MyItemRow['quantity'], $MyItemRow['decimalplaces']) . '" /></td>';
			echo '<input type="hidden" name="UOM'. $MyItemRow['stockid'] . '" value="' . $MyItemRow['units'] . '" />';
			echo '<input type="hidden" name="DecimalPlaces'. $MyItemRow['stockid'] . '" value="' . $MyItemRow['decimalplaces'] . '" />';
			echo '<td>' . $MyItemRow['suppliersuom'] . '</td>';
			echo '<td>' . $myrow[1] . '</td>';
			echo '<td><input type="text" class="number" size="10" name="Price'. $MyItemRow['stockid'] . '" value="0.00" /></td>';
			echo '<td><input type="text" class="date" alt="' .$_SESSION['DefaultDateFormat'] .'" name="RequiredByDate'. $MyItemRow['stockid'] . '" size="11" value="' .
				ConvertSQLDate($MyItemRow['requiredbydate']) . '" /></td>';
		}
		echo '</form>';
	}
	echo '</table>';
}

if (isset($_POST['Search'])){  /*ie seach for stock items */
	echo '<form method="post" action="' . $_SERVER['PHP_SELF'] . '">';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	echo '<p class="page_title_text"><img src="' . $rootpath . '/css/' . $theme . '/images/supplier.png" title="' .
		_('Tenders') . '" alt="" />' . ' ' . _('Select items to offer from').' '.$Supplier .'</p>';

	if ($_POST['Keywords'] and $_POST['StockCode']) {
		prnMsg( _('Stock description keywords have been used in preference to the Stock code extract entered'), 'info' );
	}
	if ($_POST['Keywords']) {
		//insert wildcard characters in spaces
		$SearchString = '%' . str_replace(' ', '%', $_POST['Keywords']) . '%';

		if ($_POST['StockCat']=='All'){
			$sql = "SELECT stockmaster.stockid,
					stockmaster.description,
					stockmaster.units
				FROM stockmaster INNER JOIN stockcategory
				ON stockmaster.categoryid=stockcategory.categoryid
				WHERE stockmaster.mbflag!='D'
				AND stockmaster.mbflag!='A'
				AND stockmaster.mbflag!='K'
				and stockmaster.discontinued!=1
				AND stockmaster.description " . LIKE . " '$SearchString'
				ORDER BY stockmaster.stockid";
		} else {
			$sql = "SELECT stockmaster.stockid,
					stockmaster.description,
					stockmaster.units
				FROM stockmaster INNER JOIN stockcategory
				ON stockmaster.categoryid=stockcategory.categoryid
				WHERE stockmaster.mbflag!='D'
				AND stockmaster.mbflag!='A'
				AND stockmaster.mbflag!='K'
				and stockmaster.discontinued!=1
				AND stockmaster.description " . LIKE . " '$SearchString'
				AND stockmaster.categoryid='" . $_POST['StockCat'] . "'
				ORDER BY stockmaster.stockid";
		}

	} elseif ($_POST['StockCode']){

		$_POST['StockCode'] = '%' . $_POST['StockCode'] . '%';

		if ($_POST['StockCat']=='All'){
			$sql = "SELECT stockmaster.stockid,
					stockmaster.description,
					stockmaster.units
				FROM stockmaster INNER JOIN stockcategory
				ON stockmaster.categoryid=stockcategory.categoryid
				WHERE stockmaster.mbflag!='D'
				AND stockmaster.mbflag!='A'
				AND stockmaster.mbflag!='K'
				and stockmaster.discontinued!=1
				AND stockmaster.stockid " . LIKE . " '" . $_POST['StockCode'] . "'
				ORDER BY stockmaster.stockid";
		} else {
			$sql = "SELECT stockmaster.stockid,
					stockmaster.description,
					stockmaster.units
				FROM stockmaster INNER JOIN stockcategory
				ON stockmaster.categoryid=stockcategory.categoryid
				WHERE stockmaster.mbflag!='D'
				AND stockmaster.mbflag!='A'
				AND stockmaster.mbflag!='K'
				and stockmaster.discontinued!=1
				AND stockmaster.stockid " . LIKE . " '" . $_POST['StockCode'] . "'
				AND stockmaster.categoryid='" . $_POST['StockCat'] . "'
				ORDER BY stockmaster.stockid";
		}

	} else {
		if ($_POST['StockCat']=='All'){
			$sql = "SELECT stockmaster.stockid,
					stockmaster.description,
					stockmaster.units
				FROM stockmaster INNER JOIN stockcategory
				ON stockmaster.categoryid=stockcategory.categoryid
				WHERE stockmaster.mbflag!='D'
				AND stockmaster.mbflag!='A'
				AND stockmaster.mbflag!='K'
				and stockmaster.discontinued!=1
				ORDER BY stockmaster.stockid";
		} else {
			$sql = "SELECT stockmaster.stockid,
					stockmaster.description,
					stockmaster.units
				FROM stockmaster INNER JOIN stockcategory
				ON stockmaster.categoryid=stockcategory.categoryid
				WHERE stockmaster.mbflag!='D'
				AND stockmaster.mbflag!='A'
				AND stockmaster.mbflag!='K'
				and stockmaster.discontinued!=1
				AND stockmaster.categoryid='" . $_POST['StockCat'] . "'
				ORDER BY stockmaster.stockid";
		}
	}

	$ErrMsg = _('There is a problem selecting the part records to display because');
	$DbgMsg = _('The SQL statement that failed was');
	$SearchResult = DB_query($sql,$db,$ErrMsg,$DbgMsg);

	if (DB_num_rows($SearchResult)==0 && $debug==1){
		prnMsg( _('There are no products to display matching the criteria provided'),'warn');
	}
	if (DB_num_rows($SearchResult)==1){

		$myrow=DB_fetch_array($SearchResult);
		$_GET['NewItem'] = $myrow['stockid'];
		DB_data_seek($SearchResult,0);
	}

	if (isset($SearchResult)) {

		echo "<table cellpadding=1 colspan=7>";

		$tableheader = "<tr>
			<th>" . _('Code')  . "</th>
			<th>" . _('Description') . "</th>
			<th>" . _('Units') . "</th>
			<th>" . _('Image') . "</th>
			<th>" . _('Quantity') . "</th>
			<th>" . _('Price') .' ('.$Currency.")</th>
			</tr>";
		echo $tableheader;

		$j = 1;
		$k=0; //row colour counter
		$PartsDisplayed=0;
		while ($myrow=DB_fetch_array($SearchResult)) {

			if ($k==1){
				echo '<tr class="EvenTableRows">';
				$k=0;
			} else {
				echo '<tr class="OddTableRows">';
				$k=1;
			}

			$filename = $myrow['stockid'] . '.jpg';
			if (file_exists( $_SESSION['part_pics_dir'] . '/' . $filename) ) {

				$ImageSource = '<img src="'.$rootpath . '/' . $_SESSION['part_pics_dir'] . '/' . $myrow['stockid'] .
					'.jpg" width="50" height="50">';

			} else {
				$ImageSource = '<i>'._('No Image').'</i>';
			}

			$uomsql="SELECT conversionfactor,
						suppliersuom,
						unitsofmeasure.unitname
					FROM purchdata
					LEFT JOIN unitsofmeasure
					ON purchdata.suppliersuom=unitsofmeasure.unitid
					WHERE supplierno='".$_POST['SupplierID']."'
					AND stockid='".$myrow['stockid']."'";

			$uomresult=DB_query($uomsql, $db);
			if (DB_num_rows($uomresult)>0) {
				$uomrow=DB_fetch_array($uomresult);
				if (strlen($uomrow['suppliersuom'])>0) {
					$uom=$uomrow['unitname'];
				} else {
					$uom=$myrow['units'];
				}
			} else {
				$uom=$myrow['units'];
			}
			echo "<td>".$myrow['stockid']."</td>
					<td>".$myrow['description']."</td>
					<td>".$uom."</td>
					<td>".$ImageSource."</td>
					<td><input class='number' type='text' size=6 value=0 name='qty".$myrow['stockid']."'></td>
					<td><input class='number' type='text' size=12 value=0 name='price".$myrow['stockid']."'></td>
					<input type='hidden' size=6 value=".$uom." name='uom".$myrow['stockid']."'>
					</tr>";

			$PartsDisplayed++;
			if ($PartsDisplayed == $Maximum_Number_Of_Parts_To_Show){
				break;
			}
#end of page full new headings if
		}
#end of while loop
		echo '</table>';
		if ($PartsDisplayed == $Maximum_Number_Of_Parts_To_Show){

	/*$Maximum_Number_Of_Parts_To_Show defined in config.php */

			prnMsg( _('Only the first') . ' ' . $Maximum_Number_Of_Parts_To_Show . ' ' . _('can be displayed') . '. ' .
				_('Please restrict your search to only the parts required'),'info');
		}
		echo '<a name="end"></a><br /><div class="centre"><input type="submit" name="NewItem" value="Add to Offer"></div>';
	}#end if SearchResults to show
	echo '<input type="hidden" name="TenderType" value='.$_POST['TenderType'].'>';
	echo '<input type="hidden" name="SupplierID" value='.$_POST['SupplierID'].'>';

	echo '</form>';

} //end of if search

include('includes/footer.inc');

?>