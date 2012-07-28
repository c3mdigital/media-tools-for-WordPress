/**
 * Created with JetBrains PhpStorm.
 * User: chris
 * Date: 7/24/12
 * Time: 8:25 PM
 * To change this template use File | Settings | File Templates.
 */


!function($) {

    $(function() {

        $("form#export-filters").submit(function() {
            $("form#export-filters").hide();
            $("#convert-title").replaceWith( "<h3 id='converting'>Converting Images</h3>" );
            $("#ajax-spinner").show();
            var obj  = $(this).serializeArray();
                $.ajax({
                    type: "POST",
                    url: ajaxurl,
                    data: {
                        action: "convert-featured",
                        args: obj

                    },
                    success: function(data) {
                        $("#featured-ajax-response").html("<pre style='white-space: normal'>" + data + "</pre>");
                        $("#converting").replaceWith("<h3>Results</h3>");
                        $("#ajax-spinner").hide();
                    }

                });
                return false;
        })


    });

}(jQuery);