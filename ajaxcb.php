<?php

include 'comuni.php';

if($_POST["action"] == "getprov") {
    $regId = $_POST["regione"];
    echo "<select name='openitaly4wp_provincia' onChange=\"ajaxViewComuni('$regId',this[this.selectedIndex].value,'#divComune');\">";
    echo "<option value=''>Scegli la provincia...</option>";
    foreach($italyDb[$regId] as $provId => $value) {
	echo "<option value=\"".$provId."\">$provId</option>";
    }
    echo "</select>";
}

if($_POST["action"] == "getcomuni") {
    $regId = $_POST["regione"];
    $provId = $_POST["provincia"];
    echo "<select name='openitaly4wp_comune'>";
    echo "<option value=''>Scegli il comune...</option>";
    foreach($italyDb[$regId][$provId] as $value => $comId) {
	echo "<option value=\"".$comId."\">$comId</option>";
    }
    echo "</select>";
}

?>
