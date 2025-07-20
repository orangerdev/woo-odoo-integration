/**
 * Admin-specific JavaScript functionality for WooCommerce Odoo Integration
 *
 * This file contains JavaScript functions for admin area functionality,
 * including manual customer sync operations.
 *
 * @since      1.0.0
 * @package    Woo_Odoo_Integration
 * @subpackage Woo_Odoo_Integration/admin/js
 * @author     Ridwan Arifandi <orangerdigiart@gmail.com>
 */

(function ($) {
	"use strict";

	/**
	 * Initialize admin functionality when DOM is ready
	 */
	$(document).ready(function () {
		// Handle manual customer sync button
		$("#woo-odoo-resync-customer").on("click", function (e) {
			e.preventDefault();

			var button = $(this);
			var customerId = button.data("customer-id");

			// Disable button and show loading state
			button.prop("disabled", true);
			button.text(woo_odoo_admin.syncing_text);

			// Perform AJAX request
			$.ajax({
				url: woo_odoo_admin.ajax_url,
				type: "POST",
				data: {
					action: "woo_odoo_manual_customer_sync",
					customer_id: customerId,
					nonce: woo_odoo_admin.nonce,
				},
				success: function (response) {
					if (response.success) {
						// Show success message
						button.after(
							'<div class="notice notice-success inline"><p>' +
								response.data.message +
								"</p></div>",
						);

						// Update UUID display if provided
						if (response.data.uuid) {
							var uuidCell = button.closest("table").find("code");
							if (uuidCell.length === 0) {
								// Create new UUID display
								var statusCell = button.closest("table").find("td:first");
								statusCell.html(
									"<code>" +
										response.data.uuid +
										"</code>" +
										'<p class="description" style="color: green;">' +
										woo_odoo_admin.synced_text +
										"</p>",
								);
							} else {
								// Update existing UUID
								uuidCell.text(response.data.uuid);
							}
						}
					} else {
						// Show error message
						button.after(
							'<div class="notice notice-error inline"><p>' +
								response.data +
								"</p></div>",
						);
					}
				},
				error: function (xhr, status, error) {
					// Show generic error message
					button.after(
						'<div class="notice notice-error inline"><p>' +
							woo_odoo_admin.error_text +
							"</p></div>",
					);
					console.error("WooOdoo Sync Error:", error);
				},
				complete: function () {
					// Re-enable button and restore original text
					button.prop("disabled", false);
					button.text(woo_odoo_admin.resync_text);

					// Remove notices after 5 seconds
					setTimeout(function () {
						$(".notice.inline").fadeOut(500, function () {
							$(this).remove();
						});
					}, 5000);
				},
			});
		});

		// Handle bulk customer sync (if implemented)
		$("#woo-odoo-bulk-sync-customers").on("click", function (e) {
			e.preventDefault();

			var button = $(this);
			var selectedCustomers = [];

			// Get selected customers from checkboxes
			$('input[name="users[]"]:checked').each(function () {
				selectedCustomers.push($(this).val());
			});

			if (selectedCustomers.length === 0) {
				alert(woo_odoo_admin.no_customers_selected);
				return;
			}

			// Confirm bulk sync
			if (
				!confirm(
					woo_odoo_admin.bulk_sync_confirm.replace(
						"%d",
						selectedCustomers.length,
					),
				)
			) {
				return;
			}

			// Disable button and show loading state
			button.prop("disabled", true);
			button.text(woo_odoo_admin.bulk_syncing_text);

			// Process customers one by one
			syncCustomersBatch(selectedCustomers, 0, button);
		});
	});

	/**
	 * Sync customers in batches to avoid overwhelming the server
	 *
	 * @param {Array} customerIds Array of customer IDs to sync
	 * @param {number} currentIndex Current processing index
	 * @param {jQuery} button The sync button element
	 */
	function syncCustomersBatch(customerIds, currentIndex, button) {
		if (currentIndex >= customerIds.length) {
			// All customers processed
			button.prop("disabled", false);
			button.text(woo_odoo_admin.bulk_sync_text);
			alert(woo_odoo_admin.bulk_sync_complete);
			return;
		}

		var customerId = customerIds[currentIndex];
		var progress = Math.round(((currentIndex + 1) / customerIds.length) * 100);

		// Update button text with progress
		button.text(woo_odoo_admin.bulk_syncing_text + " (" + progress + "%)");

		$.ajax({
			url: woo_odoo_admin.ajax_url,
			type: "POST",
			data: {
				action: "woo_odoo_manual_customer_sync",
				customer_id: customerId,
				nonce: woo_odoo_admin.nonce,
			},
			success: function (response) {
				console.log("Customer " + customerId + " sync result:", response);
			},
			error: function (xhr, status, error) {
				console.error("Customer " + customerId + " sync error:", error);
			},
			complete: function () {
				// Process next customer after a short delay
				setTimeout(function () {
					syncCustomersBatch(customerIds, currentIndex + 1, button);
				}, 500);
			},
		});
	}

	/**
	 * Initialize admin notices functionality
	 */
	$(document).on("click", ".woo-odoo-notice .notice-dismiss", function () {
		var notice = $(this).closest(".woo-odoo-notice");
		var noticeId = notice.data("notice-id");

		// Save dismissed state
		$.ajax({
			url: woo_odoo_admin.ajax_url,
			type: "POST",
			data: {
				action: "woo_odoo_dismiss_notice",
				notice_id: noticeId,
				nonce: woo_odoo_admin.nonce,
			},
		});
	});
})(jQuery);
