/**
 * Admin JavaScript for Automatic Product Sync Scheduler
 *
 * Handles user interactions on the scheduler admin page including manual sync
 * trigger, status refresh, and queue clearing with real-time updates.
 *
 * @since      1.0.0
 * @package    WooOdooIntegration
 * @subpackage WooOdooIntegration/admin/js
 * @author     Ridwan Arifandi <orangerdigiart@gmail.com>
 */

(function ($) {
	"use strict";

	/**
	 * Scheduler Admin Management
	 */
	var SchedulerAdmin = {
		/**
		 * Initialize the scheduler admin functionality
		 */
		init: function () {
			this.bindEvents();
			this.startStatusPolling();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			// Manual sync trigger
			$("#trigger-sync").on("click", this.triggerSync.bind(this));

			// Clear sync queue
			$("#clear-queue").on("click", this.clearQueue.bind(this));

			// Refresh status manually
			$("#refresh-status").on("click", this.refreshStatus.bind(this));
		},

		/**
		 * Start automatic status polling for in-progress syncs
		 */
		startStatusPolling: function () {
			// Check if sync is in progress
			var $statusContainer = $("#sync-status-container");
			if ($statusContainer.find(".sync-status.in-progress").length > 0) {
				// Poll every 30 seconds
				this.statusPollingInterval = setInterval(
					this.refreshStatus.bind(this),
					30000,
				);
			}
		},

		/**
		 * Stop status polling
		 */
		stopStatusPolling: function () {
			if (this.statusPollingInterval) {
				clearInterval(this.statusPollingInterval);
				this.statusPollingInterval = null;
			}
		},

		/**
		 * Trigger manual sync
		 */
		triggerSync: function (e) {
			e.preventDefault();

			if (!confirm(wooOdooScheduler.strings.confirm_trigger)) {
				return;
			}

			var $button = $(e.target);
			var $feedback = $("#manual-controls-feedback");

			// Disable button and show loading
			$button.prop("disabled", true).text(wooOdooScheduler.strings.processing);
			$feedback.empty();

			$.ajax({
				url: wooOdooScheduler.ajax_url,
				type: "POST",
				data: {
					action: "woo_odoo_trigger_auto_sync",
					nonce: wooOdooScheduler.nonce,
				},
				success: function (response) {
					if (response.success) {
						$feedback.html(
							'<div class="notice-success"><p>' + response.data + "</p></div>",
						);

						// Refresh status after a short delay
						setTimeout(function () {
							SchedulerAdmin.refreshStatus();
						}, 2000);

						// Start status polling
						SchedulerAdmin.startStatusPolling();
					} else {
						$feedback.html(
							'<div class="notice-error"><p>' +
								(response.data || wooOdooScheduler.strings.error) +
								"</p></div>",
						);
						$button
							.prop("disabled", false)
							.text(wooOdooScheduler.strings.trigger_sync || "Start Sync Now");
					}
				},
				error: function () {
					$feedback.html(
						'<div class="notice-error"><p>' +
							wooOdooScheduler.strings.error +
							"</p></div>",
					);
					$button
						.prop("disabled", false)
						.text(wooOdooScheduler.strings.trigger_sync || "Start Sync Now");
				},
			});
		},

		/**
		 * Clear sync queue
		 */
		clearQueue: function (e) {
			e.preventDefault();

			if (!confirm(wooOdooScheduler.strings.confirm_clear)) {
				return;
			}

			var $button = $(e.target);
			var $feedback = $("#manual-controls-feedback");

			// Disable button and show loading
			$button.prop("disabled", true).text(wooOdooScheduler.strings.processing);
			$feedback.empty();

			$.ajax({
				url: wooOdooScheduler.ajax_url,
				type: "POST",
				data: {
					action: "woo_odoo_clear_sync_queue",
					nonce: wooOdooScheduler.nonce,
				},
				success: function (response) {
					if (response.success) {
						$feedback.html(
							'<div class="notice-success"><p>' + response.data + "</p></div>",
						);

						// Refresh status
						SchedulerAdmin.refreshStatus();

						// Stop status polling
						SchedulerAdmin.stopStatusPolling();
					} else {
						$feedback.html(
							'<div class="notice-error"><p>' +
								(response.data || wooOdooScheduler.strings.error) +
								"</p></div>",
						);
						$button.prop("disabled", false).text("Cancel Current Sync");
					}
				},
				error: function () {
					$feedback.html(
						'<div class="notice-error"><p>' +
							wooOdooScheduler.strings.error +
							"</p></div>",
					);
					$button.prop("disabled", false).text("Cancel Current Sync");
				},
			});
		},

		/**
		 * Refresh sync status display
		 */
		refreshStatus: function (e) {
			if (e) {
				e.preventDefault();
			}

			var $button = $("#refresh-status");
			var originalText = $button.text();

			// Show loading state
			$button.prop("disabled", true).text(wooOdooScheduler.strings.processing);

			$.ajax({
				url: wooOdooScheduler.ajax_url,
				type: "POST",
				data: {
					action: "woo_odoo_get_sync_status",
					nonce: wooOdooScheduler.nonce,
				},
				success: function (response) {
					if (response.success) {
						// Update status display
						$("#sync-status-container").html(response.data.html);

						// Update button states based on status
						SchedulerAdmin.updateButtonStates(response.data.status);

						// Handle status polling
						if (
							response.data.status &&
							response.data.status.status === "in_progress"
						) {
							SchedulerAdmin.startStatusPolling();
						} else {
							SchedulerAdmin.stopStatusPolling();
						}
					} else {
						console.error("Failed to refresh status:", response.data);
					}
				},
				error: function () {
					console.error("AJAX error while refreshing status");
				},
				complete: function () {
					// Restore button state
					$button.prop("disabled", false).text(originalText);
				},
			});
		},

		/**
		 * Update button states based on current status
		 *
		 * @param {Object|null} status Current sync status
		 */
		updateButtonStates: function (status) {
			var $triggerBtn = $("#trigger-sync");
			var $clearBtn = $("#clear-queue");

			if (status && status.status === "in_progress") {
				// Sync in progress - disable trigger, enable clear
				$triggerBtn.prop("disabled", true).text("Sync in Progress");
				$clearBtn.prop("disabled", false).text("Cancel Current Sync");
			} else {
				// No sync in progress - enable trigger, disable clear
				$triggerBtn.prop("disabled", false).text("Start Sync Now");
				$clearBtn.prop("disabled", true).text("Cancel Current Sync");
			}
		},

		/**
		 * Show notification message
		 *
		 * @param {string} message The message to display
		 * @param {string} type    The type of notification (success, error, info)
		 */
		showNotification: function (message, type) {
			type = type || "info";
			var $feedback = $("#manual-controls-feedback");

			var noticeClass = "notice-" + type;
			var $notice = $(
				'<div class="' + noticeClass + '"><p>' + message + "</p></div>",
			);

			$feedback.html($notice);

			// Auto-hide success/info messages after 5 seconds
			if (type === "success" || type === "info") {
				setTimeout(function () {
					$notice.fadeOut();
				}, 5000);
			}
		},

		/**
		 * Handle page visibility changes to pause/resume polling
		 */
		handleVisibilityChange: function () {
			if (document.hidden) {
				// Page is hidden, stop polling to save resources
				this.wasPolling = !!this.statusPollingInterval;
				this.stopStatusPolling();
			} else if (this.wasPolling) {
				// Page is visible again, resume polling if it was active
				this.startStatusPolling();
				this.wasPolling = false;
			}
		},
	};

	/**
	 * Progress Bar Animation
	 */
	var ProgressBar = {
		/**
		 * Initialize progress bar animations
		 */
		init: function () {
			this.animateProgressBars();
		},

		/**
		 * Animate progress bars with smooth transitions
		 */
		animateProgressBars: function () {
			$(".progress-bar-fill").each(function () {
				var $fill = $(this);
				var targetWidth = $fill.css("width");

				// Start from 0 and animate to target width
				$fill.css("width", "0%").animate(
					{
						width: targetWidth,
					},
					1000,
					"ease-in-out",
				);
			});
		},
	};

	/**
	 * Configuration Form Enhancement
	 */
	var ConfigForm = {
		/**
		 * Initialize form enhancements
		 */
		init: function () {
			this.addValidation();
			this.addHelpTooltips();
		},

		/**
		 * Add client-side validation to configuration form
		 */
		addValidation: function () {
			$("form").on("submit", function (e) {
				var $chunkSize = $("#chunk_size");
				var $chunkInterval = $("#chunk_interval");

				var chunkSize = parseInt($chunkSize.val());
				var chunkInterval = parseInt($chunkInterval.val());

				var errors = [];

				if (chunkSize < 1 || chunkSize > 50) {
					errors.push("Chunk size must be between 1 and 50");
					$chunkSize.focus();
				}

				if (chunkInterval < 1 || chunkInterval > 60) {
					errors.push("Chunk interval must be between 1 and 60 minutes");
					$chunkInterval.focus();
				}

				if (errors.length > 0) {
					e.preventDefault();
					alert("Please fix the following errors:\n\n" + errors.join("\n"));
					return false;
				}
			});
		},

		/**
		 * Add helpful tooltips to configuration options
		 */
		addHelpTooltips: function () {
			// Add tooltips using WordPress admin styles
			$("#chunk_size").after(
				'<span class="help-tooltip" title="Smaller chunks are safer but take longer. Larger chunks are faster but may cause timeouts.">?</span>',
			);
			$("#chunk_interval").after(
				'<span class="help-tooltip" title="Shorter intervals sync faster but may overwhelm your server. Longer intervals are gentler.">?</span>',
			);

			// Style the help tooltips
			$("<style>")
				.text(
					".help-tooltip { background: #666; color: white; border-radius: 50%; width: 18px; height: 18px; display: inline-block; text-align: center; line-height: 18px; font-size: 12px; margin-left: 5px; cursor: help; }",
				)
				.appendTo("head");
		},
	};

	/**
	 * Initialize everything when document is ready
	 */
	$(document).ready(function () {
		SchedulerAdmin.init();
		ProgressBar.init();
		ConfigForm.init();

		// Handle page visibility changes
		$(document).on(
			"visibilitychange",
			SchedulerAdmin.handleVisibilityChange.bind(SchedulerAdmin),
		);

		// Handle page unload to clean up intervals
		$(window).on("beforeunload", function () {
			SchedulerAdmin.stopStatusPolling();
		});
	});
})(jQuery);
