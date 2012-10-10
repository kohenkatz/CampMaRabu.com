<?php
$activepage = $_SERVER["SCRIPT_NAME"];

$pages = array(
	'/index.php' => 'Home',
	'/about.php' => 'About Us',
	'/gallery/' => 'Gallery',
	'/itinerary.php' => 'Itinerary',
	'/forms.php' => 'Prices and Application',
	'/contact.php' => 'Contact Us'
);

?><nav id="topmenu">
	<ul><?php foreach ($pages as $url => $name) {
		$active = (strpos($activepage, $url) === 0) ? 'class="active"' : '';
		if ($url == '/index.php') { $url = '/'; }
		?>
		<li class="menubutton"><a href="<?=$url?>" <?=$active?>><?=$name?></a></li>
	<?php } ?></ul>
</nav>	