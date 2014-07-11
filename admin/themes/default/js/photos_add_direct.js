$(function(){
	$("#uploadWarningsSummary a.showInfo").click(function() {
		$("#uploadWarningsSummary").hide();
		$("#uploadWarnings").show();
		return false;
	});

	$("#showPermissions").click(function() {
		$(this).parent(".showFieldset").hide();
		$("#permissions").show();
		return false;
	});

	$("#uploader").pluploadQueue({
		// General settings
		// runtimes : 'html5,flash,silverlight,html4',
		runtimes : 'html5',

		// url : '../upload.php',
		url : 'ws.php?method=pwg.images.upload&format=json',

		// User can upload no more then 20 files in one go (sets multiple_queues to false)
		max_file_count: 100,

		chunk_size: '500kb',

		filters : {
			// Maximum file size
			max_file_size : '1000mb',
			// Specify what files to browse for
			mime_types: [
				{title : "Image files", extensions : "jpeg,jpg,gif,png"},
				{title : "Zip files", extensions : "zip"}
			]
		},

		// Rename files by clicking on their titles
		// rename: true,

		// Sort files
		sortable: true,

		// Enable ability to drag'n'drop files onto the widget (currently only HTML5 supports that)
		dragdrop: true,

		preinit: {
			Init: function (up, info) {
				jQuery('#uploader_container').removeAttr("title"); //remove the "using runtime" text
			}
		},

		init : {
			BeforeUpload: function(up, file) {
				console.log('[BeforeUpload]', file);

				// no more change on category/level
				$("select[name=level]").attr("disabled", "disabled");

				// You can override settings before the file is uploaded
				// up.setOption('url', 'upload.php?id=' + file.id);
				up.setOption(
					'multipart_params',
					{
						category : $("select[name=category] option:selected").val(),
						level : jQuery("select[name=level] option:selected").val(),
						pwg_token : pwg_token
						// name : file.name
					}
				);
			},

			FileUploaded: function(up, file, info) {
				// Called when file has finished uploading
				console.log('[FileUploaded] File:', file, "Info:", info);

				var data = jQuery.parseJSON(info.response);

				$("#uploadedPhotos").parent("fieldset").show();

				html = '<a href="admin.php?page=photo-'+data.result.image_id+'" target="_blank">';
				html += '<img src="'+data.result.src+'" class="thumbnail" title="'+data.result.name+'">';
				html += '</a> ';

				jQuery("#uploadedPhotos").prepend(html);

				// do not remove file, or it will reset the progress bar :-/
				// up.removeFile(file);
				uploadedPhotos.push(parseInt(data.result.image_id));
				uploadCategory = data.result.category;
			},

			UploadComplete: function(up, files) {
				// Called when all files are either uploaded or failed
				console.log('[UploadComplete]');

				$(".selectAlbum, .selectFiles, #permissions, .showFieldset").hide();

				$(".infos").append('<ul><li>'+sprintf(photosUploaded_label, uploadedPhotos.length)+'</li></ul>');

				html = sprintf(
					albumSummary_label,
					'<a href="admin.php?page=album-'+uploadCategory.id+'">'+uploadCategory.label+'</a>',
					parseInt(uploadCategory.nb_photos)
				);

				$(".infos ul").append('<li>'+html+'</li>');

				$(".infos").show();

				// TODO: use a new method pwg.caddie.empty +
				// pwg.caddie.add(uploadedPhotos) instead of relying on huge GET parameter
				// (and remove useless code from admin/photos_add_direct.php)

				$(".batchLink").attr("href", "admin.php?page=photos_add&section=direct&batch="+uploadedPhotos.join(","));
				$(".batchLink").html(sprintf(batch_Label, uploadedPhotos.length));

				$(".afterUploadActions").show();
			}
		}
	});
});
