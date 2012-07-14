<form action="/upload" method="post" enctype="multipart/form-data" class="form-horizontal">
	<label class="control-label" for="fileInput">Select GPX file:</label>
	<div class="controls">
		<input class="input-file" name="files[]" id="files" type="file" multiple="true">
	</div>
	 <div class="form-actions">
		<button type="submit" class="btn btn-success"><i class="icon-upload icon-white"></i> Upload</button>
	</div>
</form>
