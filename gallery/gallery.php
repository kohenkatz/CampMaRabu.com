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
$action = "gallery.php";          # Name of the page that displays galleries
$back_link = "index.php";	  # Name of the file that displays all albums
$image_count=0;
$picasa_title="NULL";
$OPEN=0;
$TZ10 = $THUMBSIZE + 10;
$TRUNCATE_FROM = 21; # Should be around 22, depending on font and thumbsize
$TRUNCATE_TO   = 18; # Should be $TRUNCATE_FROM minus 3

#----------------------------------------------------------------------------
# Grab album data from URL
#----------------------------------------------------------------------------
$ALBUM = urldecode($_REQUEST['album']);

# Reformat the album title for display
list($ALBUM_TITLE,$tags) = explode('_',$ALBUM);

#----------------------------------------------------------------------------
# Check for required variables from config file
#----------------------------------------------------------------------------
if (!isset($GDATA_TOKEN, $PICASAWEB_USER, $IMGMAX, $THUMBSIZE, $USE_LIGHTBOX, $REQUIRE_FILTER, $STANDALONE_MODE, $IMAGES_PER_PAGE)) {

	echo "<h1>" . $LANG_MISSING_VAR_H1 . "</h1><h3>" . $LANG_MISSING_VAR_H3 . "</h3>";
	exit;
}

#----------------------------------------------------------------------------
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

#----------------------------------------------------------------------------
# VARIABLES FOR PAGINATION
#----------------------------------------------------------------------------
if ($IMAGES_PER_PAGE == 0) {

	$file = "http://picasaweb.google.com/data/feed/api/user/" . $PICASAWEB_USER . "/album/" . $ALBUM . "?kind=photo&thumbsize=" . $THUMBSIZE . "&imgmax=" . $IMGMAX;

} else {

	$page = $_GET['page'];
	if (!(isset($page))) {
		$page = 1;
	}
	if ($page > 1) {
		$start_image_index = (($page - 1) * $IMAGES_PER_PAGE) + 1;
	} else {
		$start_image_index = 1;
	}

	$file = "http://picasaweb.google.com/data/feed/api/user/" . $PICASAWEB_USER . "/album/" . $ALBUM . "?kind=photo&thumbsize=" . $THUMBSIZE . "c&imgmax=" . $IMGMAX . "&max-results=" . $IMAGES_PER_PAGE . "&start-index=" . $start_image_index;

}

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
unset($thumb);
unset($title);
unset($href);
unset($path);
unset($url);
foreach ($vals as $val) {

        if ($OPEN != 1) {

	   switch ($val["tag"]) {

		case "ENTRY":
                     if ($val["type"] == "open") {
                         $OPEN=1;
                     }
                     break;

		case "TITLE":
                     if ($picasa_title == "NULL") {
                         $picasa_title = $val["value"];
                     }

		 case "GPHOTO:NUMPHOTOS":
                     # Fix for Issue 12
                     if (!is_numeric($numphotos)) {
                         $numphotos = $val["value"];
                     }
                     break;
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
                        case "MEDIA:CONTENT":
                                $href = $val["attributes"]["URL"];
				$orig_href = str_replace("s$IMGMAX","d",$href);
				$filename = basename($href);
                                $imght = $val["attributes"]["HEIGHT"];
                                $imgwd = $val["attributes"]["WIDTH"];
                                break;
                        case "SUMMARY":
                                $text = $val["value"];
                                break;
                        case "GPHOTO:ID":
                                if (!isset($STOP_FLAG)) {
                                        $gphotoid = trim($val["value"]);
                                }
                                break;
	   }
        }

        #----------------------------------------------------------------------------
        # Once we have all the pieces of info we want, dump the output
        #----------------------------------------------------------------------------
        if (isset($thumb) && isset($href) && isset($gphotoid)) {

		# Grab the album title once
                if ($STOP_FLAG != 1) {
			list($AT,$tags) = explode('_',$picasa_title);
			$AT = str_replace("\"", "", $AT);
                        $AT = str_replace("'", "",$AT);
                        echo "<div id='title'><h2>$AT</h2></div><p><a class='back_to_list' href='" . $back_link . "'>...$LANG_BACK</a></p>\n";
                        $STOP_FLAG=1;
                }

		# Set image caption
		if ($text != "") {
                        #$text = addslashes($text);
                        $caption = $text;
                } else {
                        $caption = $AT . " - " . $filename;
                }

		# Keep count of images
                $count++;

		# Hide Videos
                $vidpos = stripos($href, "googlevideo");

                if (($vidpos == "") || ($HIDE_VIDEO == "FALSE")) {

		# See if we're using Lightbox
                echo "<div class='thumbnail' style='width: " . $TZ10 . ";'>";
                if ($USE_LIGHTBOX == "TRUE") {

			if ((strlen($caption) > $TRUNCATE_FROM) && ($TRUNCATE_ALBUM_NAME == "TRUE")) {
				if ($text != "") {
                                	$short_caption = substr($caption,0,$TRUNCATE_TO) . "...";
				} else {
					$short_caption = $filename;
				}
                        }
			echo "<a href=\"$href\" class=\"lightbox\" rel=\"lightbox[this]\" title='$caption' alt='$caption'><img class='pwaimg' src='$thumb' alt='$caption'></img></a>\n";

                } else {

                        $newhref="window.open('$href', 'mywindow','scrollbars=0, width=$imgwd,height=$imght');";
                        echo "<a href='#' onclick=\"$newhref\"><img src='$thumb' alt=''></img></a>\n";

                }

		echo "<div class='thumbimage' style='width: " . $TZ10 . "px;' id='album$album_count'>\n";
		if ($SHOW_IMG_CAPTION == "HOVER") {

                                echo "<a class='options' href='$orig_href'><span style='width: " . $TZ10 . "px;'><div class='exif'>$short_caption</div>";

                } else if ($SHOW_IMG_CAPTION == "ALWAYS") {
			echo "<p>";
			echo "<div class='exif'>$short_caption</div>";
			if ($PERMIT_IMG_DOWNLOAD == "TRUE") {
				echo "<div class='dlimg'><a alt='Save $filename' title='Save $filename' href='$orig_href'><img border=0 style='padding-left: 10px;' src='images/disk_bw.png' /></a></div>";
			}
			echo "</p>";
		} else {
			echo "<p>&nbsp;</p>";
		}

		if (($PERMIT_IMG_DOWNLOAD == "TRUE") && ($SHOW_IMG_CAPTION == "HOVER")) {
                        echo "<div class='dlimg'><img border=0 style='padding-left: 10px;' src='images/disk_bw.png' /></div>";
			echo "</span></a>";
                } else if (($PERMIT_IMG_DOWNLOAD == "FALSE") && ($SHOW_IMG_CAPTION == "HOVER")) {
			echo "</span></a>";
		}

		echo "</div>";
                echo "</div>";
		} # end hide video

                #----------------------------------
                # Reset the variables
                #----------------------------------
                unset($thumb);
                unset($picasa_title);
                unset($href);
                unset($path);
                unset($url);
		unset($text);

        }
}

#----------------------------------------------------------------------------
# Show output for pagination
#----------------------------------------------------------------------------
if ($IMAGES_PER_PAGE != 0) {

	echo "<div id='pages'>";
	$paginate = ($numphotos/$IMAGES_PER_PAGE) + 1;
	echo "$LANG_PAGE: ";

	# List pages
	for ($i=1; $i<$paginate; $i++) {

		$link_image_index=($i - 1) * ($IMAGES_PER_PAGE + 1);
		$href = $action . "?album=$ALBUM&page=$i";

		# Show current page
		if ($i == $page) {
			echo "<span class='current_page'>$i </span>";
		} else {
			echo "<a class='page_link' href='$href'>$i</a> ";
		}
	}

	echo "</div>";

}

unset($picasa_title);

#----------------------------------------------------------------------------
# Output footer if required
#----------------------------------------------------------------------------
if ($STANDALONE_MODE == "TRUE") {

	require('../inc/footer.html');
}
?>
