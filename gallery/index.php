<?PHP
error_reporting(E_ALL ^ E_NOTICE);
#==============================================================================================
# Copyright 2009 Scott McCandless (smccandl@gmail.com)
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
# http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#==============================================================================================

#----------------------------------------------------------------------------
# CONFIGURATION
#----------------------------------------------------------------------------
require_once("config.php");
require_once("lang/$SITE_LANGUAGE.php");
$action = "gallery.php";	   # Name of the page that displays galleries
$FILTER = $_REQUEST['filter'];
$OPEN=0;
$TRUNCATE_FROM = 25; # Should be around 25, depending on font and thumbsize
$TRUNCATE_TO   = 22; # Should be $TRUNCATE_FROM minus 3

// Temporary Filter
$FILTER = "Summer";

#----------------------------------------------------------------------------
# Check for required variables from config file
#----------------------------------------------------------------------------
if (!isset($GDATA_TOKEN, $PICASAWEB_USER, $IMGMAX, $THUMBSIZE, $USE_LIGHTBOX, $REQUIRE_FILTER, $STANDALONE_MODE, $IMAGES_PER_PAGE)) {

        echo "<h1>" . $LANG_MISSING_VAR_H1 . "</h1><h3>" . $LANG_MISSING_VAR_H3 . "</h3>";
        exit;
}

/* #----------------------------------------------------------------------------
# Check if the user agent is iphone
#----------------------------------------------------------------------------
if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone') || strpos($_SERVER['HTTP_USER_AGENT'], 'iPod')) {
        $IMGMAX      = "320";
        $THUMBSIZE   = "144";
        $meta_tag    = "<meta name=\"viewport\" content=\"width=500\" />" . "\n";
        $content_div = "<div name='content' style='width: 500px'>" . "\n";
} else {
        $meta_tag = "";
        $content_div = "<div name='content'>" . "\n";
}
 */
#----------------------------------------------------------------------------
# VARIABLES
#----------------------------------------------------------------------------
if ($REQUIRE_FILTER != "FALSE") {
	if ((!isset($FILTER)) || ($FILTER == "")) {
		die($LANG_PERM_FILTER);
	}
}

#----------------------------------------------------------------------------
# Request URL for Album list
#----------------------------------------------------------------------------
$file = "http://picasaweb.google.com/data/feed/api/user/" . $PICASAWEB_USER . "?kind=album" . "&thumbsize=" . $THUMBSIZE;

#----------------------------------------------------------------------------
# Curl code to store XML data from PWA in a variable
#----------------------------------------------------------------------------
$ch = curl_init();
$timeout = 0; // set to zero for no timeout
curl_setopt($ch, CURLOPT_URL, $file);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

# Display only public albums if PUBLIC_ONLY=TRUE in config.php
if ($PUBLIC_ONLY == "FALSE") {
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: AuthSub token="' . $GDATA_TOKEN . '"'
        ));
}

$addressData = curl_exec($ch);
curl_close($ch);

#----------------------------------------------------------------------------
# Parse the XML data into an array
#----------------------------------------------------------------------------
$p = xml_parser_create();
xml_parse_into_struct($p, $addressData, $vals, $index);
xml_parser_free($p);

#----------------------------------------------------------------------------
# Output headers if required
#----------------------------------------------------------------------------
if ($STANDALONE_MODE == "TRUE") {
	require('../inc/header.html');
}

#----------------------------------------------------------------------------
# Iterate over the array and extract the info we want
#----------------------------------------------------------------------------
foreach ($vals as $val) {

	if ($OPEN != 1) {

		if ($val["tag"] == "ENTRY") {

			if ($val["type"] == "open") {

				$OPEN = 1;

			}
		}

	} else {

	   switch ($val["tag"]) {

			case "ENTRY":
				if ($val["type"] == "close") {
					$OPEN=0;
				}
				break;
			case "MEDIA:THUMBNAIL":
				$thumb = trim($val["attributes"]["URL"] . "\n");
				break;	
			case "MEDIA:DESCRIPTION":
				$desc = trim($val["value"] . "\n");
				break;
                        case "MEDIA:TITLE":
                                $title = trim($val["value"]);
                                break;
                        case "LINK":
				if ($val["attributes"]["REL"] == "alternate") {
                                	$href = trim($val["attributes"]["HREF"]);
				}
                                break;
                        case "GPHOTO:NUMPHOTOS":
                                $num = trim($val["value"]);
                                break;
			case "GPHOTO:LOCATION":
                                $loc = trim($val["value"]);
                                break;
			case "PUBLISHED":
                                $published = trim($val["value"]);
				$published = substr($published,0,10);	
                                break;
			case "GPHOTO:ACCESS":
				$access = trim($val["value"]);
				if ($access == "protected") { $daccess = "Private"; }
				else { $daccess = "Public"; }
				break;
			case "GPHOTO:NAME":
				$picasa_name = trim($val["value"]);
				break;
	   }
        }

	#----------------------------------------------------------------------------
	# Once we have all the pieces of info we want, dump the output
	#----------------------------------------------------------------------------
	
	if (isset($thumb) && isset($title) && isset($href) && isset($num) && isset($published)) {
		
		if ($FILTER != "") {
				$pos = strlen(strpos($title,$FILTER));
				$box = strlen(strpos($title,"Drop Box"));
				if ($pos > 0) { 
					$pos = 0; 
				#} else if (($box > 0) && ($SHOW_DROP_BOX == "TRUE")) {	# Added to allow user to control whether
				#	$pos = 0;					# drop box appears in gallery list
				} else { 
					$pos = 1; 
				}
		} else {
				$pos = strlen(strpos($title,"_hide"));
		}
		
		if ($pos == 0) {

                        $thumbwidth = 210;
			$twstyle="width: " . $galdatasize . "px;";
                        list($disp_name,$tags) = explode('_',$title);

			# --------------------------------------------------------------------
			# Added via issue 7, known problem: long names can break div layout
			# --------------------------------------------------------------------
			if ((strlen($disp_name) > $TRUNCATE_FROM) && ($TRUNCATE_ALBUM_NAME == "TRUE")) {
                                $disp_name = substr($disp_name,0,$TRUNCATE_TO) . "...";
                        }
                        $album_count++;
			$total_images = $total_images + $num;
                        $out .= "<div class='thumbnail' style='width: " . $thumbwidth . "px;'>\n";
                        $out .= "<div class='thumbimage' style='width: " . $THUMBSIZE . "px;' id='album$album_count'>\n";
                        $out .= "<a class='overlay' href='" . $action . "?album=$picasa_name'><img class='pwaimg' alt='$disp_name' src='$thumb'></img>";

			# ------------------------------------------------
			# Overlay album details on thumbnail if requested
			# ------------------------------------------------
			if ($SHOW_ALBUM_DETAILS == "TRUE") {
				if ($desc != "") {
					if (strlen($desc) > 120) {
						$desc = substr($desc,0,117) . "...";
					}
                                        $out .= "<span>";
					$out .= "<p class='overlaypg'>$desc</p>";
					if ($loc != "") {
						$out .= "<p class='overlaystats'>$LANG_WHERE: $loc</p>";
					}
					$out .= "<p class='overlaystats' style='padding-top: 5px;'>$LANG_ACCESS: $daccess</p>";
					$out .= "</span>\n";
				}
                        }	
			$out .= "</a>";
                        $out .= "</div>\n";
                        $out .= "<div class='galdata' style='$twstyle'>\n";
                        $out .= "<p class='titlepg'><a class='album_link' href='" .$action . "?album=$picasa_name'>$disp_name</a></p>\n";
                        //$out .= "<p class='titlestats'>$published, $num $LANG_IMAGES</p>\n";
						$out .= "<p class='titlestats'>$num $LANG_IMAGES</p>\n";
                        $out .= "</div>";
                        $out .= "</div>\n";

                }
                #----------------------------------
                # Reset the variables
                #----------------------------------
                unset($thumb);
		unset($title);

        }
}
echo "<h2>Summer Highlights Photo Gallery</h2><div style=\"float:right; margin-top:-26px;\"><a href=\"#videos\">View Highlight Videos Below &#x25BC;</a></div><br/>";
//echo "<div id='title'><span style='font-size: 18px; font-weight: bold;'>$FILTER $LANG_GALLERY</span><span style='font-size: 14px; color: #B0B0B0; margin-left: 10px;'>$total_images $LANG_PHOTOS_IN $album_count $LANG_ALBUMS</span></div>\n";
echo $out;
echo "<div id='pwafooter'>$LANG_GENERATED <a href='http://code.google.com/p/pwaplusphp/'>PWA+PHP</a> v" . $THIS_VERSION . ". ";

echo "</div>";

?>
<br style="clear:both">
<h2 id="videos">Summer Highlights Video Gallery</h2>
<h3 id="video2011" style="margin-top: 4px">Summer 2011</h3>
<iframe width="640" height="360" src="http://www.youtube-nocookie.com/embed/SJnbsR-TLnY?rel=0" frameborder="0" allowfullscreen></iframe>
<?php

#----------------------------------------------------------------------------
# Output footer if required
#----------------------------------------------------------------------------
if ($STANDALONE_MODE == "TRUE") {
	require('../inc/footer.html');
}

?>
