(function($, Drupal, drupalSettings)
{
Drupal.behaviors.chatgpt_plugin = {
    attach:function(context, settings) {
        // Initially hide the copy content button.
        $('.chatgpt-form .copybutton ').hide();

        // Show this button once we have the generated content.
        if ($('#chatgpt-result').length > 0) {
            if ($('#chatgpt-result').html().length > 0) {
                $('.chatgpt-form .copybutton ').show();
            }
        }
        
        $('.chatgpt-form').find('.copybutton').click(function() {
            var attrName = $(this).attr("name");
            if (attrName.length > 0) {
                var fieldName = attrName.replace(/_/g, '-');
                if ($('#edit-'+fieldName+'-wrapper .ck-editor__editable').contents().length > 0) {
                    var content = $('#chatgpt-result').html();
                    // A reference to the editable DOM element.
                    const domEditableElement = document.querySelector('#edit-'+fieldName+'-wrapper .ck-editor__editable');
                    // Get the ckeditor5 instance
                    const ckeditor5Instance = domEditableElement.ckeditorInstance;
                    // Use the ckeditor5 API set method.
                    ckeditor5Instance.setData(content);
                }
                else {
                    var content = $('#chatgpt-result').text();
                    $('#edit-'+fieldName+'-wrapper input').val(content);
                }

            }

        });

    }
}
} (jQuery, Drupal, drupalSettings));