jQuery(document).ready(function ($) {
  $("#cm-monetize-btn").on("click", function (e) {
    e.preventDefault();

    var $btn = $(this);
    var $btnText = $("#cm-btn-text");
    var $btnLoader = $("#cm-btn-loader");

    $btn.prop("disabled", true);
    $btnText.hide();
    $btnLoader.show();

    var data = {
      action: "monetize_content",
      nonce: cm_ajax.nonce,
      current_url: cm_ajax.current_url,
    };

    $.post(cm_ajax.ajax_url, data)
      .done(function (response) {
        if (response.success) {
          showNotification(cm_ajax.success_text, "success");
          if (response.data.api_response) {
            console.log("API Response:", response.data.api_response);
          }
        } else {
          var errorMsg =
            response.data && response.data.message
              ? response.data.message
              : cm_ajax.error_text;
          showNotification(errorMsg, "error");
          console.error("Monetization Error:", response.data);
        }
      })
      .fail(function (xhr, status, error) {
        showNotification("Network error: " + error, "error");
        console.error("AJAX Error:", status, error);
      })
      .always(function () {
        $btn.prop("disabled", false);
        $btnText.show();
        $btnLoader.hide();
      });
  });

  function showNotification(message, type) {
    $(".cm-notification").remove();

    var notificationClass =
      type === "success" ? "cm-notification-success" : "cm-notification-error";
    var icon = type === "success" ? "✅" : "❌";

    var $notification = $("<div>", {
      class: "cm-notification " + notificationClass,
      html: '<span style="margin-right: 8px;">' + icon + "</span>" + message,
    });

    $notification.css({
      position: "fixed",
      top: "20px",
      right: "20px",
      background: type === "success" ? "#d4edda" : "#f8d7da",
      color: type === "success" ? "#155724" : "#721c24",
      border: "1px solid " + (type === "success" ? "#c3e6cb" : "#f5c6cb"),
      padding: "12px 20px",
      "border-radius": "6px",
      "box-shadow": "0 4px 12px rgba(0,0,0,0.15)",
      "z-index": "10000",
      "font-family":
        '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
      "font-size": "14px",
      "max-width": "400px",
      "word-wrap": "break-word",
      animation: "cmSlideIn 0.3s ease-out",
    });

    if (!$("#cm-notification-styles").length) {
      $('<style id="cm-notification-styles">')
        .text(
          `
                  @keyframes cmSlideIn {
                      from {
                          opacity: 0;
                          transform: translateX(100%);
                      }
                      to {
                          opacity: 1;
                          transform: translateX(0);
                      }
                  }
                  @keyframes cmSlideOut {
                      from {
                          opacity: 1;
                          transform: translateX(0);
                      }
                      to {
                          opacity: 0;
                          transform: translateX(100%);
                      }
                  }
              `
        )
        .appendTo("head");
    }

    $("body").append($notification);

    setTimeout(function () {
      $notification.css("animation", "cmSlideOut 0.3s ease-in forwards");
      setTimeout(function () {
        $notification.remove();
      }, 300);
    }, 5000);

    $notification.on("click", function () {
      $(this).css("animation", "cmSlideOut 0.3s ease-in forwards");
      setTimeout(function () {
        $notification.remove();
      }, 300);
    });
  }

  $("#cm-monetize-btn").attr(
    "title",
    "Click to send this page content to your monetization API"
  );

  $(document).on("keydown", function (e) {
    if (e.key === "Escape") {
      $(".cm-notification").each(function () {
        $(this).click();
      });
    }
  });
});
