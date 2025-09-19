/**
 * PDF Export JavaScript
 * File: assets/js/pdf-export.js
 */
(function ($) {
  "use strict";
  // PDF Export Handler
  const PDFExportHandler = {
    init: function () {
      console.log('PDFExportHandler initialized');
      console.log('wpsPdfExport object:', wpsPdfExport);
      this.bindEvents();
    },
    bindEvents: function () {
      $(document).on(
        "click",
        "#export-permits-pdf",
        this.handleExportClick.bind(this)
      );
    },
    handleExportClick: function (e) {
      e.preventDefault();
      console.log('Export button clicked');
      
      const $button = $(e.currentTarget);
      const $status = $("#pdf-export-status");
      
      // Prevent multiple clicks
      if ($button.prop("disabled")) {
        console.log('Button already disabled, returning');
        return;
      }
      
      // Get email address - adjust selector based on your form
      const emailAddress = $("#email_address").val() || $("#user_email").val() || $("input[name='email_address']").val();
      
      if (!emailAddress) {
        console.error('Email address not found');
        $status.html(this.createStatusMessage("error", "Email address is required"));
        return;
      }
      
      console.log('Email address:', emailAddress);
      
      this.showLoadingState($button, $status);
      this.submitExportRequest($button, $status, emailAddress);
    },
    
    showLoadingState: function ($button, $status) {
      console.log('Setting loading state');
      // Update button
      $button.prop("disabled", true);
      $button
        .find(".wps-icon")
        .removeClass("dashicons-pdf")
        .addClass("dashicons-update");
      $button.find(".wps-btn-text").text(wpsPdfExport.strings.generating || 'Generating...');
      
      // Update status
      $status.html(
        this.createStatusMessage("processing", wpsPdfExport.strings.processing || 'Processing...')
      );
    },
    
    submitExportRequest: function ($button, $status, emailAddress) {
      console.log('Submitting AJAX request');
      
      const requestData = {
        action: 'export_permits_pdf',
        nonce: wpsPdfExport.nonce,
        email_address: emailAddress
      };
      
      console.log('Request data:', requestData);
      console.log('AJAX URL:', wpsPdfExport.ajaxUrl);
      
      $.ajax({
        url: wpsPdfExport.ajaxUrl,
        method: 'POST',
        data: requestData,
        dataType: 'json', // Explicitly expect JSON
        timeout: 30000, // 30 seconds timeout
        beforeSend: function(xhr) {
          console.log('AJAX request starting...');
        },
        success: (response) => {
          console.log('AJAX success response:', response);
          
          if (response && response.success) {
            console.log('PDF generation successful:', response.data);
            
            // Trigger download
            if (response.data.download_url) {
              console.log('Opening download URL:', response.data.download_url);
              window.open(response.data.download_url, '_blank');
            }
            
            this.resetButtonState($button, $status, response.data.message || 'PDF generated successfully');
          } else {
            console.error('PDF generation failed:', response);
            const errorMessage = response && response.data ? response.data : 'Unknown error occurred';
            this.showError($button, $status, errorMessage);
          }
        },
        error: (xhr, status, error) => {
          console.error('AJAX error:', {
            xhr: xhr,
            status: status,
            error: error,
            responseText: xhr.responseText,
            responseJSON: xhr.responseJSON
          });
          
          let errorMessage = 'Connection error occurred';
          
          if (xhr.responseText) {
            try {
              const response = JSON.parse(xhr.responseText);
              errorMessage = response.data || response.message || errorMessage;
            } catch (e) {
              console.error('Failed to parse error response as JSON:', e);
              console.log('Raw response text:', xhr.responseText);
              errorMessage = 'Server returned invalid response: ' + xhr.responseText.substring(0, 100);
            }
          }
          
          this.showError($button, $status, errorMessage);
        },
        complete: function() {
          console.log('AJAX request completed');
        }
      });
    },
    
    showError: function($button, $status, message) {
      console.error('Showing error:', message);
      $status.html(this.createStatusMessage("error", message));
      this.resetButtonState($button, $status);
    },
    
    resetButtonState: function ($button, $status, message = null) {
      console.log('Resetting button state');
      
      // Reset button
      $button.prop("disabled", false);
      $button
        .find(".wps-icon")
        .removeClass("dashicons-update")
        .addClass("dashicons-pdf");
      $button.find(".wps-btn-text").text(wpsPdfExport.strings.exportBtn || 'Export PDF');
      
      // Show success message if provided
      if (message) {
        $status.html(this.createStatusMessage("success", message));
        
        // Clear status after delay
        setTimeout(() => {
          $status.empty();
        }, 5000);
      }
    },
    
    createStatusMessage: function (type, message) {
      return `<span class="wps-status-${type}">${message}</span>`;
    },
  };
  
  // Initialize when document is ready
  $(document).ready(function () {
    PDFExportHandler.init();
  });
  
})(jQuery);