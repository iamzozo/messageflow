<form action="" name="file-upload" enctype="multipart/form-data" method="post">
		
	<input type="hidden" name="project_id" value="<?php $id ?>" />
	<input type="hidden" name="action" value="file_upload" />
	
	<div id="fileupload">
		<input type="file" name="userfile" value="" multiple />
		Drop files or click here to upload
	</div>
</form>
<div class="loading"></div>
<div id="fileholder">

</div>