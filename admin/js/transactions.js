jQuery(document).ready(function ($) {
  $("#filter-button").on("click", function () {
    const data = {
      action: "filter_transactions",
      nonce: '<?php echo wp_create_nonce( "filter_transactions_nonce" ); ?>',
      mode: $("#mode").val(),
      form_id: $("#form_id").val(),
      start_date: $("#start_date").val(),
      end_date: $("#end_date").val(),
    };

    $.post(ajaxurl, data, function (response) {
      if (response.success) {
        $("#transactions-table").html(response.data);
      } else {
        alert("Failed to load transactions.");
      }
    });
  });
});
