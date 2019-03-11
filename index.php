<?php

require_once 'src/includes/acf-takeout.php';
$takeout = new acf_takeout();

?><!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="x-ua-compatible" content="ie=edge">
		<title>ACF Takeout 🥡 Hot and fresh modules, less time in the kitchen</title>
		<meta name="description" content="">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="lib/style.css">
		<link href="https://fonts.googleapis.com/css?family=Fira+Sans:400,700" rel="stylesheet">
	</head>
	<body>
		<?php echo $takeout->get_notices(); ?>
		<figure id="logo" class="h1">ACF Takeout</figure>
		<form class="site-main section content-width" method="POST">
			<h1 class="section-header">Order Online:</h1>
			<section class="horiz x2">
				<?php echo $takeout->get_name_inputs(); ?>
			</section>
			<?php echo $takeout->get_modules_as_checkboxes(); ?>
			<button type="submit">Place Order</button>
		</form>
		<script type="text/javascript" src="lib/js/scripts.min.js"></script>
	</body>
</html>
