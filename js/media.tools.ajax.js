/**
 * Media Tools Admin Ajax Handler
 * @author Chris Olbekson
 *
 * @package Media Tools WordPress plugin
 * @subpackage javascript
 *
 * @version 1.1
 *
 */

!function($) {

    $(function() {

        var opts = { lines:9, length:17, width:6, radius:12, rotate:24, color:'#000',
                speed:0.9, trail:45, shadow: true, hwaccel:false, className:'spinner', zIndex:2e9, top:30, left: 20 };

        var form = $('#export-filters');
        var filters = form.find('.export-filters');
        filters.hide();
        form.find('input:radio').change(function () {
            filters.slideUp('fast');
            switch ($(this).val()) {
                case 'post':
                    $('#post-filters').slideDown();
                    break;
                case 'page':
                    $('#page-filters').slideDown();
                    break;
            }
        });

        $.fn.serializeObject = function() {
            var o = {};
            var a = this.serializeArray();
            $.each(a, function() {
                if (o[this.name] !== undefined ) {

                if(!o[this.name].push) {
                    o[this.name] = [o[this.name]];
                }
                o[this.name].push(this.value || '');
            } else {
                o[this.name] = this.value || '';
            }
            });
        return o;

        };
            var bar = $("#media-progress");
            var barPercent = $("#media-progress-percent");
            var mt_count = 1;

            form.submit(function(e) {
            form.slideUp();

                var target = document.getElementById('featured-ajax-response');
                var spinner = new Spinner(opts).spin(target);
                $(target).show();

            $("#convert-choose").replaceWith( "<h3 id='converting'>Running......Please be patient. This could take a while.</h3>" );
            bar.progressbar();
            barPercent.html( "0%" );
            var obj  = form.serializeObject(e);
                $.ajax({
                    type: "POST",
                    url: ajaxurl,
                    dataType: 'json',
                    data: {
                        action: "convert-featured",
                        args: obj

                    },
                     success: function(data) {

                     var mt_total  = data.length;

                     var obj = form.serializeObject(e);
                     var arg = obj['choose-tool'];
                         $.each(data, function(i, data) {
                         process(data, arg, mt_total );

                         })
                    }

                });

                        function process(data, obj, mt_total ) {
                            $.ajax({
                                type:"POST",
                                url:ajaxurl,
                                data:{
                                    action:"process-data",
                                    args: obj,
                                    ids: data
                                },
                                success:function (response) {
                                    progressStatus(response, mt_total );
                                    $("#featured-ajax-response").append("<pre style='white-space: normal'>" + response + "</pre>");
                                    $("#converting").replaceWith("<h2>Results</h2>");

                                },
                                complete:function (response) {

                                     ajaxDone(response);

                                }
                            });
                         }
                        function ajaxDone(data) {
                            $.ajax({
                                type: "POST",
                                url: ajaxurl,
                                data: {
                                    action: "ajax-done"
                                },
                                success: function(done) {
                                    spinner.stop();
                                    $("#my-message").html("<p><strong>Image processing complete. </strong></p>");
                                    $("#my-message").show();
                                }
                            })
                        }

                return false;

            });

        function progressStatus(response, mt_total) {

            bar.progressbar("value", ( mt_count / mt_total ) * 100);
            barPercent.html(Math.round(( mt_count / mt_total ) * 1000) / 10 + "%");

            mt_count = mt_count + 1;
        }



        $("select#choose-tool").change(function(e) {
               var tool = '';
            $("select#choose-tool option:selected").each(function(e) {
                 tool += $(this).text();
            });
            $("#submit").val(tool);

        }).trigger("change");
    });

}(jQuery);