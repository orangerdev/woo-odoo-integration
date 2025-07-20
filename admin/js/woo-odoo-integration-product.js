/**
 * WooCommerce Odoo Integration - Product Management JavaScript
 *
 * Handles AJAX functionality for single product stock synchronization
 * and provides user feedback during sync operations.
 *
 * @since      1.0.0
 * @package    Woo_Odoo_Integration
 * @subpackage Woo_Odoo_Integration/admin/js
 * @author     Ridwan Arifandi <orangerdigiart@gmail.com>
 */

(function ($) {
	"use strict";

	/**
	 * Product sync functionality
	 */
	var ProductSync = {
		/**
		 * Initialize product sync functionality
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			$(document).on(
				"click",
				".woo-odoo-sync-single-product",
				this.handleSingleProductSync,
			);
		},

		/**
		 * Handle single product sync button click
		 *
		 * @param {Event} e Click event
		 */
		handleSingleProductSync: function (e) {
			e.preventDefault();

			var $button = $(this);
			var productId = $button.data("product-id");
			var sku = $button.data("sku");

			if (!productId) {
				ProductSync.showNotice(
					"error",
					woo_odoo_product.messages.invalid_product_id,
				);
				return;
			}

			// Disable button and show loading state
			ProductSync.setButtonLoading($button, true);

			// Perform AJAX request
			$.ajax({
				url: woo_odoo_product.ajax_url,
				type: "POST",
				data: {
					action: "woo_odoo_sync_single_product",
					product_id: productId,
					nonce: woo_odoo_product.nonce,
				},
				success: function (response) {
					if (response.success) {
						ProductSync.showNotice("success", response.data.message);

						// Optionally refresh the page to show updated stock
						if (woo_odoo_product.refresh_on_sync) {
							setTimeout(function () {
								location.reload();
							}, 2000);
						}
					} else {
						var errorMessage =
							response.data && response.data.message
								? response.data.message
								: woo_odoo_product.messages.sync_failed;
						ProductSync.showNotice("error", errorMessage);
					}
				},
				error: function (xhr, status, error) {
					console.error("AJAX Error:", status, error);
					ProductSync.showNotice("error", woo_odoo_product.messages.ajax_error);
				},
				complete: function () {
					// Re-enable button
					ProductSync.setButtonLoading($button, false);
				},
			});
		},

		/**
		 * Set button loading state
		 *
		 * @param {jQuery} $button Button element
		 * @param {boolean} loading Loading state
		 */
		setButtonLoading: function ($button, loading) {
			if (loading) {
				$button
					.prop("disabled", true)
					.addClass("updating-message")
					.data("original-text", $button.text())
					.text(woo_odoo_product.messages.syncing);
			} else {
				$button
					.prop("disabled", false)
					.removeClass("updating-message")
					.text(
						$button.data("original-text") ||
							woo_odoo_product.messages.sync_button,
					);
			}
		},

		/**
		 * Show admin notice
		 *
		 * @param {string} type Notice type (success, error, warning, info)
		 * @param {string} message Notice message
		 */
		showNotice: function (type, message) {
			var noticeClass = "notice notice-" + type + " is-dismissible";
			var noticeHtml =
				'<div class="' +
				noticeClass +
				'">' +
				"<p><strong>" +
				woo_odoo_product.plugin_name +
				":</strong> " +
				message +
				"</p>" +
				'<button type="button" class="notice-dismiss">' +
				'<span class="screen-reader-text">' +
				woo_odoo_product.messages.dismiss +
				"</span>" +
				"</button>" +
				"</div>";

			// Remove existing notices from this plugin
			$(".notice")
				.filter(function () {
					return (
						$(this).find("p strong").text() ===
						woo_odoo_product.plugin_name + ":"
					);
				})
				.remove();

			// Add new notice
			var $notice = $(noticeHtml);
			if ($(".wrap h1").length) {
				$notice.insertAfter(".wrap h1");
			} else {
				$notice.prependTo(".wrap");
			}

			// Handle dismiss button
			$notice.on("click", ".notice-dismiss", function () {
				$notice.fadeOut(300, function () {
					$(this).remove();
				});
			});

			// Auto-dismiss success notices after 5 seconds
			if (type === "success") {
				setTimeout(function () {
					$notice.fadeOut(300, function () {
						$(this).remove();
					});
				}, 5000);
			}
		},
	};

	/**
	 * Bulk actions enhancement
	 */
	var BulkActions = {
		/**
		 * Initialize bulk actions enhancement
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			$("#doaction, #doaction2").on("click", this.handleBulkAction);
		},

		/**
		 * Handle bulk action submission with confirmation
		 *
		 * @param {Event} e Click event
		 */
		handleBulkAction: function (e) {
			var $button = $(this);
			var actionSelect =
				$button.attr("id") === "doaction"
					? "#bulk-action-selector-top"
					: "#bulk-action-selector-bottom";
			var selectedAction = $(actionSelect).val();

			if (selectedAction === "woo_odoo_sync_product_stock") {
				var selectedProducts = $('input[name="post[]"]:checked').length;

				if (selectedProducts === 0) {
					e.preventDefault();
					ProductSync.showNotice(
						"error",
						woo_odoo_product.messages.no_products_selected,
					);
					return false;
				}

				var confirmMessage =
					woo_odoo_product.messages.confirm_bulk_sync.replace(
						"%d",
						selectedProducts,
					);

				if (!confirm(confirmMessage)) {
					e.preventDefault();
					return false;
				}

				// Show loading state
				BulkActions.setFormLoading(true);
			}
		},

		/**
		 * Set form loading state
		 *
		 * @param {boolean} loading Loading state
		 */
		setFormLoading: function (loading) {
			if (loading) {
				$("#doaction, #doaction2")
					.prop("disabled", true)
					.addClass("updating-message");
				$('input[name="post[]"]').prop("disabled", true);
			} else {
				$("#doaction, #doaction2")
					.prop("disabled", false)
					.removeClass("updating-message");
				$('input[name="post[]"]').prop("disabled", false);
			}
		},
	};

	/**
	 * Initialize when document is ready
	 */
	$(document).ready(function () {
		ProductSync.init();
		BulkActions.init();
	});
})(jQuery);
