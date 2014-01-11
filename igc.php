<?php
// Bootstrap autoloader

require_once('Zend/Loader/Autoloader.php');
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('Zend_');
$autoloader->registerNamespace('Shaw_');

// @see http://carrier.csi.cam.ac.uk/forsterlewis/soaring/igc_file_format/igc_format_2008.html#link_4.1
// @see http://phpdoc.org/docs/latest/for-users/basic-usage.html
// @see http://beautifyphp.sourceforge.net/docs/
// @see https://developers.google.com/maps/documentation/javascript/reference#Polyline

date_default_timezone_set('Europe/Berlin');

require_once('IgcFile.php');
$igc = IgcFile::factory(file_get_contents('287A3632.IGC'));

$fix = $igc->fixes[0];
$scale = 10.0; // 1px = 100m
$xo = $fix->x; // easting
$yo = $fix->y; // northing
$xp = 938 / 2;
$yp = 398 / 2;

require_once('Viewport.php');
$doc = new Viewport(938, 398);
$doc->center = $igc->fixes[0];
$doc->scale = 10.0;
$doc->path = $igc->fixes;

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="">
<meta name="author" content="">

<link href="/assets/css/bootstrap.min.css" rel="stylesheet">
<link href="/assets/css/fileuploader.css" rel="stylesheet">
<style type="text/css">
body {
	padding-top: 60px; padding-bottom: 40px;
}
</style>

</head>

<body>

	<div class="navbar navbar-fixed-top">
		<div class="navbar-inner">
			<div class="container">
				<a class="brand" href="#">IGC viewer</a>
			</div>
		</div>
	</div>

	<!-- Modal for Upload dialog -->
	<div class="modal hide" id="file-uploader-modal1">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"
				onclick="javascript:$('.editable-picture').trigger('cancel');">×</button>
			<h3>Image</h3>
			<p>Please specify an image to use</p>
		</div>
		<div class="modal-body">
			<!-- Upload file OR Select URL -->
			<div id="file-uploader-demo1"></div>
			<div class="divider">
				<span>OR</span>
			</div>
			<div class="form-inline">
				<label>Image Url :</label> <input id="imageUrl1" type="text"
					class="span12" />
			</div>
		</div>
		<div class="modal-footer">
			<a onclick="javascript:$('.editable-picture').trigger('cancel');"
				class="btn" data-dismiss="modal">Cancel</a> <a
				onclick="javascript:updatePicture($('#imageUrl1').val());$('.editable-picture').trigger('save');"
				data-dismiss="modal" class="btn btn-primary">Save</a>
		</div>
	</div>

	<div class="container">

		<form class="well form-inline"></form>

		<!-- Example row of columns -->
		<div class="row">
			<div class="span12">
			
				<div style="width: <?php echo $doc->width; ?>px; border: 1px solid grey; height: <?php echo $doc->height; ?>px; margin: 20px 0;">
					<?php 
						echo $doc->render();
					?>
				</div>
				
			</div>
		</div>

		<hr>

		<footer>
			<p>&copy; Tonant 2012</p>
		</footer>

	</div>
	<!-- /container -->

	<!-- Placed at the end of the document so the pages load faster -->
	<script src="/assets/js/jquery-1.7.2.min.js"></script>
	<script src="/assets/js/bootstrap.min.js"></script>
	<script src="/assets/js/fileuploader.js"></script>
	<script src="/assets/js/highcharts.js"></script>

	<script>
    /*
    $(function(){
    	/***********************************
    	 * Image uploader
    	 * /
    	var uploader = new qq.FileUploader({
            element: document.getElementById('file-uploader-demo1'),
            action: '<?php echo $this->url('index', 'upload'); ?>',
            debug: true,
            onComplete: function(id, fileName, responseJSON){
                var url = responseJSON.url;
                updatePicture(url);
                $('.editable-picture').trigger('save');
                $('#file-uploader-modal1').modal('hide');
            }
        }); 
    });
    */
    </script>

</body>
</html>
