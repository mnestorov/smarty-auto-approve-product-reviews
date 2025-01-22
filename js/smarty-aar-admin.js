jQuery(document).ready(function($) {
    // Handle tab switching
    $(".smarty-aar-nav-tab").click(function (e) {
        e.preventDefault();
        $(".smarty-aar-nav-tab").removeClass("smarty-aar-nav-tab-active");
        $(this).addClass("smarty-aar-nav-tab-active");

        $(".smarty-aar-tab-content").removeClass("active");
        $($(this).attr("href")).addClass("active");
    });

    // Load README.md
    $("#smarty-aar-load-readme-btn").click(function () {
        const $content = $("#smarty-aar-readme-content");
        $content.html("<p>Loading...</p>");

        $.ajax({
            url: smartyAutoApproveReviews.ajaxUrl,
            type: "POST",
            data: {
                action: "smarty_aar_load_readme",
                nonce: smartyAutoApproveReviews.nonce,
            },
            success: function (response) {
                console.log(response);
                if (response.success) {
                    $content.html(response.data);
                } else {
                    $content.html("<p>Error loading README.md</p>");
                }
            },
        });
    });

    // Load CHANGELOG.md
    $("#smarty-aar-load-changelog-btn").click(function () {
        const $content = $("#smarty-aar-changelog-content");
        $content.html("<p>Loading...</p>");

        $.ajax({
            url: smartyAutoApproveReviews.ajaxUrl,
            type: "POST",
            data: {
                action: "smarty_aar_load_changelog",
                nonce: smartyAutoApproveReviews.nonce,
            },
            success: function (response) {
                console.log(response);
                if (response.success) {
                    $content.html(response.data);
                } else {
                    $content.html("<p>Error loading CHANGELOG.md</p>");
                }
            },
        });
    });
});