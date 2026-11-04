/* آواتار اختصاصی — صفحه پروفایل کاربر */
jQuery(function ($) {
	var frame;
	var T = window.WSLProfile || {};
	$('#wsl-avatar-select').on('click', function (e) {
		e.preventDefault();
		if (frame) { frame.open(); return; }
		frame = wp.media({ title: T.title, button: { text: T.button }, multiple: false, library: { type: 'image' } });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			var url = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
			$('#wsl_avatar_id').val(a.id);
			$('#wsl-avatar-preview').empty().append($('<img>', { src: url, alt: '' }));
			$('#wsl-avatar-remove').show();
		});
		frame.open();
	});
	$('#wsl-avatar-remove').on('click', function (e) {
		e.preventDefault();
		$('#wsl_avatar_id').val('');
		$('#wsl-avatar-preview').empty().append($('<span>').text(T.none));
		$(this).hide();
	});
});
