/**
 * Email Confirmation JavaScript
 * File: assets/js/email-confirmation.js
 */
jQuery(document).ready(function($) {
    
    /**
     * Initialize email confirmation field
     */
    function initializeEmailConfirmation() {
        const emailField = $('#email_address');
        
        if (emailField.length && !$('#confirm_email_address').length) {
            const confirmFieldHTML = `
                <div class="form-group__row form-group__row--inline-confirm_email">
                    <div class="confirm_email_address-col">
                        <label for="confirm_email_address">
                            Confirm Email Address <span style="color: red;">*</span>:
                        </label>
                        <input type="email" 
                            id="confirm_email_address" 
                            name="confirm_email_address" 
                            class="form-control" 
                            placeholder="Re-enter your email address" 
                            required>
                    </div>
                    <div id="email-match-status" class="email-status"></div>
                </div>
            `;
            
            // Insert the confirm field as a sibling to the email field within the same .form-row
            emailField.closest('.form-group__row').after(confirmFieldHTML);
        }
    }
    
    /**
     * Check if email addresses match
     */
    function checkEmailMatch() {
        const email = $('#email_address').val().trim();
        const confirmEmail = $('#confirm_email_address').val().trim();
        const statusDiv = $('#email-match-status');
        const submitBtn = $('input[type="submit"], button[type="submit"]');
        
        // Clear status if either field is empty
        if (!email || !confirmEmail) {
            statusDiv.html('').removeClass('match error');
            submitBtn.prop('disabled', false);
            return;
        }
        
        // Check if emails match
        if (email === confirmEmail) {
            statusDiv.html('✓ Email addresses match')
                    .removeClass('error')
                    .addClass('match');
            submitBtn.prop('disabled', false);
        } else {
            statusDiv.html('✗ Email addresses do not match')
                    .removeClass('match')
                    .addClass('error');
            submitBtn.prop('disabled', true);
        }
    }
    
    /**
     * Validate email format
     */
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    /**
     * Handle form submission validation
     */
    function handleFormSubmission(e) {
        const email = $('#email_address').val().trim();
        const confirmEmail = $('#confirm_email_address').val().trim();
        
        // Check if both emails are provided
        if (!email || !confirmEmail) {
            alert('Please fill in both email fields.');
            e.preventDefault();
            return false;
        }
        
        // Check if emails match
        if (email !== confirmEmail) {
            alert('Email addresses do not match. Please check and try again.');
            $('#confirm_email_address').focus().select();
            e.preventDefault();
            return false;
        }
        
        // Basic email format validation
        if (!isValidEmail(email)) {
            alert('Please enter a valid email address.');
            $('#email_address').focus().select();
            e.preventDefault();
            return false;
        }
        
        return true;
    }
    
    /**
     * Prevent paste in confirmation field
     */
    function handleConfirmEmailPaste(e) {
        e.preventDefault();
        alert('Please type your email address manually to ensure accuracy.');
    }
    
    // Initialize email confirmation
    initializeEmailConfirmation();
    
    // Bind events for real-time validation
    $(document).on('input keyup', '#email_address, #confirm_email_address', checkEmailMatch);
    
    // Form submission validation
    $('form').on('submit', handleFormSubmission);
    
    // Prevent paste in confirmation field (optional security measure)
    $(document).on('paste', '#confirm_email_address', handleConfirmEmailPaste);
    
});