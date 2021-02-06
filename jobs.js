jQuery(document).ready(function ($) {

    var paged = 2;

    $(window).on("scroll", function() {
        var scrollHeight = $(document).height();
        var scrollPosition = $(window).height() + $(window).scrollTop();
        if ((scrollHeight - scrollPosition) / scrollHeight === 0) {
                console.log('hii');
                $.ajax({
                url: "https://kamgoko.com/fabrice/blank/wp-admin/admin-ajax.php",
                data: {action: "job", action_type: "get_offer_cards_more", page: paged},
                type: "POST",
                dataType: "json",
                cache: false,
                beforeSend: function () {
                    /* body... */
                    $('#get_more_spinner').css('display', "table");
                },
                complete: function (response) {
                    $.each(response.responseJSON, function(index, el){
                        $('#jobs_offer_listq').append(el);
                    });

                    paged++;
                    $('#get_more_spinner').css('display', "none");
                }
            });
        }
    });
    
    $(document).on("click", ".job-offer-row", function (e) {
        var url = $(this).attr("data-href");
        var that = $(this);
        var ajax_request = {url: url, type: "GET"};

        $("#job_single").removeClass("d-none");

        $.get(url, function (data) {
            $("#job_single iframe").attr("src", url).removeClass("d-none");


        })
                .done(function () {

                })
                .fail(function () {
                    $("#job_single iframe").attr("src", "").addClass("d-none");
                    $("#job_single").addClass("d-none");
                })
                .always(function () {

                });


    });

    $(document).on("click", "#iframe_back_btn", function (e) {
        $("#job_single iframe").attr("src", "");
        $("#job_single").addClass("d-none");
    });

    $("#click_file").click(function () {
        $("#filili").click();
    });


});



/*
 $("#home_app_content").animate({"bottom": "0"}, "fast", function () {
 $("#home_app_content_iframe").attr("src", href);
 });*/