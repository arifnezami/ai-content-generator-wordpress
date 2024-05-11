jQuery(document).ready(function ($) {
    $('#content-generator-generate').click(function () {
        var title = $('#content-generator-title').val();

        if (title === '') {
            alert('Please enter a title');
            return;
        }

        $.post(ContentGenerator.ajaxurl, {
            action: 'content_generator_generate_content',
            title: title
        }, function (response) {
            if (response.success) {
                $('#content-generator-output').html('<h3>Generated Content:</h3><p>' + response.data.content + '</p>');
                $('#content-generator-insert').data('content', response.data.content);
            } else {
                alert('Error: ' + response.data);
            }
        });
    });

    $('#content-generator-insert').click(function () {
        var content = $(this).data('content');
        if (!content) {
            alert('Please generate content first.');
            return;
        }

        wp.data.dispatch('core/editor').insertBlocks(
            wp.blocks.createBlock('core/paragraph', { content: content })
        );
    });
});
