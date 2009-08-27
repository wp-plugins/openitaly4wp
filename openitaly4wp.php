<?php
/**
 * @package OpenItaly4WP
 * @author Michele Pinassi
 * @version 0.0.5
 */
/*
Plugin Name: Openitaly4WP
Plugin URI: http://www.openitaly.net/wp
Description: This plugin allows to display latest, preferred, localized resources from OpenItaly.net
Author: Michele Pinassi
Version: 0.0.5
Author URI: http://www.zerozone.it
*/

include 'IXR_Library.inc.php';

function stripallslashes($string) {
    while(strchr($string,'\\')) {
	$string = stripslashes($string);
    }
    return $string;
}

function getEntryURL($entryTitle,$entryComune) {
    $entryTitle = stripallslashes($entryTitle);
    $entryComune = stripallslashes($entryComune);
    if(strlen($entryComune) > 0) {
	return urlencode($entryTitle." - ".$entryComune);
    } else {
	return urlencode($entryTitle);
    }
}

function isSelected($value,$match) {
    if($value == $match) return "selected";
}

function fiXML($xmlData) {
    return str_replace("&", "&amp;", $xmlData);
}

// WP Options
add_option("openitaly4wp_username", '', '', 'yes');
add_option("openitaly4wp_password", '', '', 'yes');
add_option("openitaly4wp_regione" , '', '', 'yes');
add_option("openitaly4wp_provincia", '', '', 'yes');
add_option("openitaly4wp_comune", '', '', 'yes');
add_option("openitaly4wp_type", '', '', 'yes');
add_option("openitaly4wp_show", 'random', '', 'yes');
add_option("openitaly4wp_title", 'Alcune risorse in %COMUNE%', '', 'yes');
add_option("openitaly4wp_results", '5', '', 'yes');
add_option("openitaly4wp_css", '0', '', 'yes');
add_option("openitaly4wp_csstitle", 'openitaly4wp-title', '', 'yes');
add_option("openitaly4wp_csssidebar", 'openitaly4wp-box', '', 'yes');

add_action('admin_menu', 'openitaly4wp_plugin_menu');
add_action('plugins_loaded', 'openitaly4wp_widget_init');

function openitaly4wp_version() {
    return "0.0.5";
}

// WP Actions  
if(get_option('openitaly4wp_css') == "1")  {
    add_action('wp_head','openitaly4wp_css');
}

function openitaly4wp_widget_init() {
  register_sidebar_widget('openitaly4wp', 'openitaly4wp_widget');
}

function openitaly4wp_css() {
    echo '<link type="text/css" rel="stylesheet" href="' . get_bloginfo('wpurl') .'/wp-content/plugins/openitaly4wp/openitaly4wp.css" />' . "\n";
}

function openitaly4wp_path($file=null) {
	return path_join(WP_PLUGIN_URL,basename(dirname(__FILE__))) .'/'. $file;
}


function openitaly4wp_plugin_menu() {
  add_options_page('openitaly4wp Plugin Options', 'openitaly4wp', 
    8, __FILE__, 'openitaly4wp_plugin_options');
}

function openitaly4wp_itemdiv($xmlItem) {
    echo "<div class='openitaly4wp-item'>";
    if($xmlItem->class == "EVENT") {    
        echo "<img src='".openitaly4wp_path("img/iconEvent.png")."' style='width:12px; height:12px; vertical-align: middle;'>";
    } else if($xmlItem->class == "GENERAL") {   
	echo "<img src='".openitaly4wp_path("img/iconInfo.png")."' style='width:12px; height:12px; vertical-align: middle;'>";	    
    } else {   
	echo "<img src='".openitaly4wp_path("img/iconResource.png")."' style='width:12px; height:12px; vertical-align: middle;'>";
    }
    echo "<a href='http://www.openitaly.net/entry/".getEntryURL($xmlItem->title,$xmlItem->comune)."' target=_new>".$xmlItem->title."</a><br><em>";
    if(strlen($xmlItem->address) > 0) {
	echo $xmlItem->address.", ";
    }
    if(strcmp($xmlItem->comune,$xmlItem->provincia) == 0) {
	echo $xmlItem->comune.", ";
    } else {    
	echo $xmlItem->comune.", ".$xmlItem->provincia.", ";
    }
    echo $xmlItem->regione."</em></div><br>";
}

function openitaly4wp_widget($args) {
    extract($args);
    
    $title = get_option('openitaly4wp_title');
    $type = get_option('openitaly4wp_type');
    $comune = get_option('openitaly4wp_comune');
    $provincia = get_option('openitaly4wp_provincia');
    $regione = get_option('openitaly4wp_regione');
    $username = get_option('openitaly4wp_username');
    $password = get_option('openitaly4wp_password');
    $show = get_option('openitaly4wp_show');
    $results = get_option('openitaly4wp_results');
    $csstitle = get_option('openitaly4wp_csstitle');
    $csssidebar = get_option('openitaly4wp_csssidebar');

    $title = str_ireplace(array('%COMUNE%','%PROVINCIA%','%REGIONE%','%USERNAME%'),array($comune,$provincia,$regione,$username),$title);

    echo "<div class='$csssidebar'><!-- BOX -->";

    echo "<div class='$csstitle'>$title</div>";

    $query[] = "regione:$regione";
    if(strlen($provincia) > 0) {
	$query[] = " provincia:$provincia";
    }
    if(strlen($comune) > 0) {
	$query[] = " comune:$comune";
    }
    $query[] = "class:$type";
    $query[] = "maxres:$results";
    $sessId = "";

    error_reporting(E_ERROR | E_PARSE);
    
    $client = new IXR_Client('http://www.openitaly.net/xmlrpc');
    $client->debug = false;
    if($client->query('oi.getInfo', '','openitaly4wp',openitaly4wp_version(),get_option('blogname'))) {
	$xmlData = simplexml_load_string(fiXML(html_entity_decode($client->getResponse())));
	$sessId = (string)$xmlData->id;
    }
    // Adesso esegui la query a seconda di cosa visualizzare...
    if($show == 'topten') {
	if($client->query('oi.getTopTen',$sessId)) {
	    $xmlData = simplexml_load_string(fiXML(html_entity_decode($client->getResponse())));
	    if($xmlData) {
		// TODO: Show data
		foreach($xmlData->entry as $xmlItem) {
		    openitaly4wp_itemdiv($xmlItem);
		}			    
	    }
	} else {
	    print "Errore: ".$client->getErrorCode()." : ".$client->getErrorMessage();
	}
    } else if($show == 'lastadd') {
	if($type == 'EVENT') {
	    $query = $client->query('oi.getLastEvt',$sessId);
	} else {
	    $query = $client->query('oi.getLastRes',$sessId);
	}
	if($query) {
	    $xmlData = simplexml_load_string(fiXML(html_entity_decode($client->getResponse())));
	    if($xmlData) {
		foreach($xmlData->entry as $xmlItem) {
		    openitaly4wp_itemdiv($xmlItem);
		}
	    }
	} else {
	    print "Errore: ".$client->getErrorCode()." : ".$client->getErrorMessage();
	}
    } else if($show == 'bookmark') {
	// Login and show data
	$query = $client->query('oi.doLogin',$sessId,$username,$password);
	if($query) {
	    $xmlData = simplexml_load_string(fiXML(html_entity_decode($client->getResponse())));
	    if(($xmlData)&&(intval((string)$xmlData->code) == 201)) {
		// Now retrieve preferred
		$query = $client->query('oi.getBookmarks',$sessId,'');
		if($query) {
	    	    $xmlData = simplexml_load_string(fiXML(html_entity_decode($client->getResponse())));
	    	    echo "<!-- ".$xmlData->value." -->";
		    foreach($xmlData->entry as $xmlItem) {
			openitaly4wp_itemdiv($xmlItem);
		    }
		} else {
		    print "Errore: ".$client->getErrorCode()." : ".$client->getErrorMessage();
		}
	    } else {
		print "Errore autenticazione";
	    }	
	} else {
	    print "Errore: ".$client->getErrorCode()." : ".$client->getErrorMessage();
	}
    } else if($show == 'myopinions') {
	// Login and show data
	$query = $client->query('oi.doLogin',$sessId,$username,$password);
	if($query) {
	    $xmlData = simplexml_load_string(fiXML(html_entity_decode($client->getResponse())));
	    if(($xmlData)&&(intval((string)$xmlData->code) == 201)) {
		// Now retrieve preferred
		$query = $client->query('oi.getLastOpinions',$sessId,$results,$username);
		if($query) {
	    	    $xmlData = simplexml_load_string(fiXML(html_entity_decode($client->getResponse())));
	    	    echo "<!-- ".$xmlData->value." -->";
		    foreach($xmlData->opinion as $xmlItem) {
			echo "<div class='openitaly4wp-comment'>\"".$xmlItem->message."\"</div> ha detto <strong>".$xmlItem->userId."</strong> approposito di ";
			openitaly4wp_itemdiv($xmlItem);
		    }
		} else {
		    print "Errore: ".$client->getErrorCode()." : ".$client->getErrorMessage();
		}
	    } else {
		print "Errore autenticazione";
	    }	
	} else {
	    print "Errore: ".$client->getErrorCode()." : ".$client->getErrorMessage();
	}
    } else {
	if($client->query('oi.doSearch',$sessId,$query[0],$query[1],$query[2],$query[3],$query[4])) {
	    $xmlData = simplexml_load_string(fiXML(html_entity_decode($client->getResponse())));
	    if($xmlData) {
		foreach($xmlData->entry as $xmlItem) {
		    openitaly4wp_itemdiv($xmlItem);
		}
	    }
	} else {
	    print "Errore: ".$client->getErrorCode()." : ".$client->getErrorMessage();
	}
    }   
    error_reporting(E_ERROR | E_WARNING | E_PARSE);

    echo "<div class='openitaly4wp-footer'><small><a href='http://www.openitaly.net/wp'><b>Openitaly4WP</b></a> v".openitaly4wp_version()." - Powered by <a href='http://www.openitaly.net' target=_new><img src='".openitaly4wp_path("img/iconLogo.png")."' style='vertical-align: middle; border: 0px;'>&nbsp;Openitaly.net</a></small></div>";
    echo "</div><!-- /BOX -->";
}
    

function openitaly4wp_plugin_options() {

  include 'comuni.php';
  
  load_plugin_textdomain($openitaly4wp_textdomain,
     PLUGINDIR . '/' . dirname(plugin_basename(__FILE__)),
     dirname(plugin_basename(__FILE__)));

  if($_SERVER['REQUEST_METHOD'] == 'POST') {
	// Salva opzioni
	update_option('openitaly4wp_username',$_POST['openitaly4wp_username']);
	update_option('openitaly4wp_password',$_POST['openitaly4wp_password']);
	update_option('openitaly4wp_regione',$_POST['openitaly4wp_regione']);
	update_option('openitaly4wp_provincia',$_POST['openitaly4wp_provincia']);
	update_option('openitaly4wp_comune',$_POST['openitaly4wp_comune']);
        update_option('openitaly4wp_type',$_POST['openitaly4wp_type']);
	update_option('openitaly4wp_title',$_POST['openitaly4wp_title']);
	update_option('openitaly4wp_show',$_POST['openitaly4wp_show']);
	update_option('openitaly4wp_results',intval($_POST['openitaly4wp_results']));

	if($_POST['openitaly4wp_css'] == "1")
    	    update_option('openitaly4wp_css',"1");
	else {
    	    update_option('openitaly4wp_css',"0");
	}

	update_option('openitaly4wp_csstitle',$_POST['openitaly4wp_csstitle']);
	update_option('openitaly4wp_csssidebar',$_POST['openitaly4wp_csssidebar']);

	echo "<div class=\"updated\"><p><strong>"; 
	echo __('Options saved.', $openitaly4wp_textdomain );
	echo "</strong></p></div>";
    }

    $openitaly4wp_username = get_option('openitaly4wp_username');
    $openitaly4wp_password = get_option('openitaly4wp_password');
    $openitaly4wp_regione = get_option('openitaly4wp_regione');
    $openitaly4wp_provincia = get_option('openitaly4wp_provincia');
    $openitaly4wp_comune = get_option('openitaly4wp_comune');
    $openitaly4wp_type = get_option('openitaly4wp_type');
    $openitaly4wp_title = get_option('openitaly4wp_title');
    $openitaly4wp_show = get_option('openitaly4wp_show');
    $openitaly4wp_css = get_option('openitaly4wp_css');
    $openitaly4wp_csstitle = get_option('openitaly4wp_csstitle');
    $openitaly4wp_csssidebar = get_option('openitaly4wp_csssidebar');
    $openitaly4wp_results = intval(get_option('openitaly4wp_results'));

    // Include JS
    
    wp_print_scripts( array( 'sack' ));
    
    echo "<script type='text/javascript'>
    //<![CDATA[
    function ajaxViewProvince(regione,container) {
	jQuery.ajax({
	    type: 'post',
	    url: '".openitaly4wp_path("ajaxcb.php")."',
	    data: { action: 'getprov', regione: regione},
	    beforeSend: function() {
	        jQuery('#loading').show('slow');
	    }, 
	    complete: function() { 
	        jQuery('#loading').hide('fast');
	    }, 
	    success: function(html) {
		jQuery(container).html(html); 
	    }
	}); 
	return true;
    } 
                        
    function ajaxViewComuni(regione,provincia,container) {
	jQuery.ajax({
	    type: 'post',
	    url: '".openitaly4wp_path("ajaxcb.php")."',
	    data: { action: 'getcomuni', regione: regione, provincia: provincia},
	    beforeSend: function() {
	        jQuery('#loading').show('slow');
	    }, 
	    complete: function() { 
	        jQuery('#loading').hide('fast');
	    }, 
	    success: function(html) { 
		jQuery(container).html(html); 
	    }
	}); 
	return true;

    }
    //]]>
    </script>";

    // Now display the options editing screen

    echo "<div class='wrap'>";
    echo "<h2>Openitaly4WP</h2>";
    echo "<p>v".openitaly4wp_version()." &copy; <a href=\"http://www.zerozone.it\">Michele Pinassi</a></p>";

    // options form

    echo "<form name=\"form1\" method=\"post\" action=\"".str_replace( '%7E', '~', $_SERVER['REQUEST_URI']). "\">";

    echo "<hr /><p>Titolo del widget. Puoi impostare %COMUNE%,%PROVINCIA%,%REGIONE%,%USERNAME% ed inserire tag HTML.</p>
    <p>Titolo: <input type=\"text\" size=\"32\" name=\"openitaly4wp_title\" value=\"$openitaly4wp_title\" /></p>";

    echo "<hr /><p>Contenuti del widget. Cosa vuoi visualizzare ?</p>
    <p><select name='openitaly4wp_show'>
	<option value='random' ".isSelected($openitaly4wp_show,"random").">Risorse a caso</option>
	<option value='topten' ".isSelected($openitaly4wp_show,"topten").">Classifica TopTen</option>
	<option value='lastadd' ".isSelected($openitaly4wp_show,"lastadd").">Ultime aggiunte</option>
	<option value='bookmark' ".isSelected($openitaly4wp_show,"bookmark").">I miei preferiti</option>
	<option value='myopinions' ".isSelected($openitaly4wp_show,"myopinions").">Le mie recensioni</option>
    </select></p>";
    
    echo "<hr /><p>Nome utente e password dell'account di openitaly.net: necessarie solamente se vuoi far vedere le tue risorse preferite.</p>
    <p>Username: <input type=\"text\" size=\"16\" name=\"openitaly4wp_username\" value=\"$openitaly4wp_username\" /></p>
    <p>Password:<input type=\"password\" size=\"16\" name=\"openitaly4wp_password\" value=\"$openitaly4wp_password\" /></p>";

    if(strlen($openitaly4wp_username) < 1) {
	echo "<p style='padding: .5em; background-color: #fc6; color: #666;'>Non hai ancora un <b>account su Openitaly.net</b> ? La community &egrave; <b>totalmente gratuita</b> e ti offre molti vantaggi: <a href='http://www.openitaly.net/register'>clicca qua e registrati subito.</a></p>";
    }
    
    echo "<hr /><p>Scegli il contesto in cui visualizzare le risorse (La Regione &egrave; obbligatoria !)</p>
    <p><select name='openitaly4wp_regione' onChange=\"ajaxViewProvince(this[this.selectedIndex].value,'#divProvincia');\">
	<option value='$openitaly4wp_regione'>$openitaly4wp_regione</option>    
	<option value='Abruzzo'>Abruzzo</option>
	<option value='Basilicata'>Basilicata</option>
	<option value='Calabria'>Calabria</option>
	<option value='Campania'>Campania</option>
	<option value='Emilia-Romagna'>Emilia-Romagna</option>
	<option value='Friuli-Venezia Giulia'>Friuli-Venezia Giulia</option>
	<option value='Lazio'>Lazio</option>
	<option value='Liguria'>Liguria</option>
	<option value='Lombardia'>Lombardia</option>
	<option value='Marche'>Marche</option>
	<option value='Molise'>Molise</option>
	<option value='Piemonte'>Piemonte</option>
	<option value='Puglia'>Puglia</option>
	<option value='Sardegna'>Sardegna</option>
	<option value='Sicilia'>Sicilia</option>
	<option value='Toscana'>Toscana</option>
	<option value='Trentino-Alto Adige'>Trentino-Alto Adige</option>
	<option value='Umbria'>Umbria</option>
	<option value='Valle Aosta'>Valle d'Aosta</option>
	<option value='Veneto'>Veneto</option>
    </select></p>
    <p>Provincia:<div id='divProvincia'><select name='openitaly4wp_provincia' onChange=\"ajaxViewComuni('$openitaly4wp_regione',this[this.selectedIndex].value,'#divComune');\">";
    if(strlen($openitaly4wp_provincia) > 0) {
	foreach($italyDb[$openitaly4wp_regione] as $provId => $value) {
	    echo "<option value=\"".$provId."\" ".isSelected($provId,$openitaly4wp_provincia).">$provId</option>";
	}
    } else {
	echo "<option value='$openitaly4wp_provincia'>$openitaly4wp_provincia</option>";
    }
    echo "</select></div></p>
    <p>Comune<div id='divComune'><select name='openitaly4wp_comune'>";
    if(strlen($openitaly4wp_comune) > 0) {
	foreach($italyDb[$openitaly4wp_regione][$openitaly4wp_provincia] as $value => $comId) {
	    echo "<option value=\"".$comId."\" ".isSelected($comId,$openitaly4wp_comune).">$comId</option>";
	}
    } else {
	echo "<option value='$openitaly4wp_comune'>$openitaly4wp_comune</option>";
    }
    echo "</select></div></p>
    <div id='loading' style='display: none;'>
	<img src='".openitaly4wp_path("img/spinner.gif")."' style='width:12px; height:12px; vertical-align: middle;'>&nbsp;Please wait, loading data...
    </div>";

    echo "<hr /><p>Scegli il tipo di risorse che vuoi visualizzare.</p>
    <p><select name=openitaly4wp_type>
	<option value=''>Tutte</option>
	<option value='EVENT' ".isSelected($openitaly4wp_type,"EVENT").">Eventi</option>
	<option value='RESOURCE' ".isSelected($openitaly4wp_type,"RESOURCE").">Risorse</option>
	<option value='GENERAL' ".isSelected($openitaly4wp_type,"GENERAL").">Da vedere</option>
    </select></p>";

    echo "<hr /><p>Quante risorse da visualizzare ? Ricorda che pi&ugrave; risorse visualizzi, pi&ugrave; spazio occuper&agrave; il widget.</p>
    <p><select name=openitaly4wp_results>
	<option value='1' ".isSelected($openitaly4wp_results,1).">1</option>
	<option value='2' ".isSelected($openitaly4wp_results,2).">2</option>
	<option value='3' ".isSelected($openitaly4wp_results,3).">3</option>
	<option value='4' ".isSelected($openitaly4wp_results,4).">4</option>
	<option value='5' ".isSelected($openitaly4wp_results,5).">5</option>
	<option value='6' ".isSelected($openitaly4wp_results,6).">6</option>
	<option value='7' ".isSelected($openitaly4wp_results,7).">7</option>
	<option value='8' ".isSelected($openitaly4wp_results,8).">8</option>
	<option value='9' ".isSelected($openitaly4wp_results,9).">9</option>
	<option value='10' ".isSelected($openitaly4wp_results,10).">10</option>
    </select></p>";

    echo "<hr /><p>Usare CSS di Openitaly4wp ?</p>";
    echo "<input type=\"checkbox\" name=\"openitaly4wp_css\"";
    echo (($openitaly4wp_css == "1") ? " checked=\"checked\" " : "" );
    echo " value=\"1\" /></p>";

    echo "<p>Classe CSS del Titolo ? Specifica l'eventuale css del tuo tema per i titoli dei widgets</p><p><input type=\"text\" size=\"128\" name=\"openitaly4wp_csstitle\" value=\"$openitaly4wp_csstitle\" /></p>";
    echo "<p>Classe CSS della Box ? Specifica l'eventuale css del tuo tema per i widgets</p><p><input type=\"text\" size=\"128\" name=\"openitaly4wp_csssidebar\" value=\"$openitaly4wp_csssidebar\" /></p>";
    
    echo "<hr /><p class=\"submit\"><input type=\"submit\" name=\"Submit\" value=\"Salva opzioni\" /></p></form>";

    echo "<hr /></div>";
}

?>
