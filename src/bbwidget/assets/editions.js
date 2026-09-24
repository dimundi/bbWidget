jQuery(function ($) {
    $('.bbw-color').wpColorPicker();
    let frame;
    $('#bbw-logo-select').on('click', function () {
        if (!frame) {
            frame = wp.media({ title: 'Logo edycji', button: { text: 'Wybierz logo' }, library: { type: 'image' }, multiple: false });
            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                $('#bbw-logo').val(attachment.id);
                const url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                $('#bbw-logo-preview').empty().append($('<img>', { src: url, alt: attachment.alt || '', width: 150 }).css('height', 'auto'));
            });
        }
        frame.open();
    });
    $('#bbw-logo-remove').on('click', function () {
        $('#bbw-logo').val('');
        $('#bbw-logo-preview').empty();
    });
});