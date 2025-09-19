/**
 * Complete Rewritten Frontend JavaScript with Popup Message System
 * File: assets/js/frontend.js
 */
jQuery(document).ready(function($) {
    
    // ===== GLOBAL VARIABLES AND INITIALIZATION =====
    
    let inputCounter = 0; // Personnel input counter
    const MAX_INPUTS = 6;
    
    // Check if all required elements exist
    validateRequiredElements();
    
    // Initialize all functionality
    initializeAllFunctionality();
    
    // ===== ELEMENT VALIDATION =====
    
    function validateRequiredElements() {
        const requiredElements = [
            '#work-permit-form',
            '#submit-permit',
            'input[name="requestor_type[]"]',
            'input[name="issued_for[]"]'
        ];
        
        requiredElements.forEach(selector => {
            const elements = $(selector);
            (`${selector}: ${elements.length} elements found`);
            if (elements.length === 0) {
                console.warn(`Required element not found: ${selector}`);
            }
        });
    }
    
    // ===== POPUP MESSAGE SYSTEM =====
    
    /**
     * Show form message as popup
     */
    function showFormMessage(message, type = 'info', title = null) {
        const modal = $('#form-message-modal');
        const titleElement = $('#form-message-title');
        const textElement = $('#form-message-text');
        
        // Clear previous classes
        modal.removeClass('success error info warning');
        
        // Set message type
        modal.addClass(type);
        
        // Set title
        const titleMap = {
            'success': 'Success!',
            'error': 'Error',
            'info': 'Information',
            'warning': 'Warning'
        };
        titleElement.text(title || titleMap[type] || 'Notification');
        
        // Set message text (handle HTML content)
        textElement.html(message);
        
        // Show modal
        modal.show();
        
        // Focus management for accessibility
        modal.find('.form-message-popup-close').focus();
    }
    
    /**
     * Show form error message as popup
     */
    function showFormError(message) {
        showFormMessage(message, 'error', 'Submission Error');
    }
    
    /**
     * Show form success message as popup
     */
    function showFormSuccess(message) {
        showFormMessage(message, 'success', 'Success!');
    }
    
    /**
     * Show form info message as popup
     */
    function showFormInfo(message) {
        showFormMessage(message, 'info', 'Please Wait');
    }
    
    
    /**
     * Hide form message popup
     */
    function hideFormMessage() {
        const modal = $('#form-message-modal');
        modal.addClass('closing');
        
        setTimeout(() => {
            modal.hide().removeClass('closing success error info warning');
        }, 200);
    }
    
    /**
     * Initialize popup event handlers
     */
    function initializeFormMessagePopup() {
        // Close button click
        $(document).on('click', '.form-message-popup-close', function(e) {
            e.preventDefault();
            hideFormMessage();
        });
        
        // OK button click
        $(document).on('click', '#form-message-ok-btn', function(e) {
            e.preventDefault();
            hideFormMessage();
        });
        
        // Close on overlay click
        $(document).on('click', '.form-message-popup-overlay', function(e) {
            e.preventDefault();
            hideFormMessage();
        });
        
        // Close on Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#form-message-modal:visible').length > 0) {
                hideFormMessage();
            }
        });
        
        // Prevent clicks inside content from closing modal
        $(document).on('click', '.form-message-popup-content', function(e) {
            e.stopPropagation();
        });
    }
    
    // ===== "OTHERS" FUNCTIONALITY =====
    
    /**
     * Initialize "Other" functionality
     */
    function initializeOtherFunctionality() {
        // Ensure the other input is hidden on page load
        $("#other-input-container").hide();
        
        // Universal handler for all issued_for checkboxes
        $(document).on('change', 'input[name="issued_for[]"]', function() {
            const $this = $(this);
            const value = $this.val();
            const isChecked = $this.prop('checked');
            
            if (value === 'Others' && isChecked) {
                // $("#other-input-container").show();
                $("#other-input-container").css("display", "flex");
                $("#other-input").focus();
            } else if (value === 'Others' && !isChecked) {
                $("#other-input-container").hide();
                $("#other-input").val('');
            } else if (value !== 'Others' && isChecked) {
                $("#other-input-container").hide();
                $("#other-input").val('');
            }
        });
        
        // Enhanced visual feedback for "Others" input
        $('#other-input').on('input', function() {
            const value = $(this).val().trim();
            const container = $('#other-input-container');
            const maxLength = 200;
            const minLength = 3;
            
            // Clear previous validation classes
            container.removeClass('has-value validation-warning validation-error validation-success');
            container.find('.validation-message, .character-counter').remove();
            
            if (value.length > 0) {
                container.addClass('has-value');
                
                // Character counter
                const remaining = maxLength - value.length;
                let counterClass = '';
                let counterText = `${value.length}/${maxLength} characters`;
                
                if (remaining < 20) {
                    counterClass = 'warning';
                }
                if (remaining < 0) {
                    counterClass = 'error';
                    counterText += ` (${Math.abs(remaining)} over limit)`;
                }
                
                const counter = $(`<div class="character-counter ${counterClass}">${counterText}</div>`);
                container.append(counter);
                
                // Validation feedback
                if (value.length < minLength) {
                    container.addClass('validation-warning');
                    const message = $('<div class="validation-message warning">Please provide more detail (minimum 3 characters)</div>');
                    container.append(message);
                } else if (value.length > maxLength) {
                    container.addClass('validation-error');
                    const message = $('<div class="validation-message error">Description too long (maximum 200 characters)</div>');
                    container.append(message);
                } else {
                    container.addClass('validation-success');
                    const message = $('<div class="validation-message success">Good description provided</div>');
                    container.append(message);
                }
            }
        });
    }
    
    // ===== SINGLE SELECTION BEHAVIOR =====
    
    /**
     * Handle single selection for checkbox groups
     */
    function initializeSingleSelection() {
        function handleSingleSelection(groupName) {
            $(`input[name="${groupName}"]`).on('change', function() {
                if (this.checked) {
                    $(`input[name="${groupName}"]`).not(this).prop('checked', false);
                }
            });
        }
        
        handleSingleSelection('issued_for[]');
        handleSingleSelection('requestor_type[]');
        
        // Visual feedback for single selection
        $('input[name="issued_for[]"], input[name="requestor_type[]"]').on('change', function() {
            const groupName = $(this).attr('name');
            const $group = $(`input[name="${groupName}"]`);
            const $labels = $group.closest('label');
            
            $labels.removeClass('single-selection-active');
            
            if (this.checked) {
                $(this).closest('label').addClass('single-selection-active');
            }
        });
    }
    
    // ===== FILE INPUT HANDLING =====
    
    /**
     * Initialize file input functionality
     */
    function initializeFileInputs() {
        
        const personnelInput = $('#personnel_extra_info');
        const detailsInput = $('#details_extra_info');
        
        if (personnelInput.length) {
            personnelInput.attr('title', 'Upload personnel supporting documents (PDF, DOCX, DOC - Max 5MB)');
        }
        
        if (detailsInput.length) {
            detailsInput.attr('title', 'Upload details supporting documents (PDF, DOCX, DOC - Max 5MB)');
        }
        
        // Handle file input changes
        $('#personnel_extra_info').on('change', function() {
            handleFileInputChange(this, 'personnel');
        });
        
        $('#details_extra_info').on('change', function() {
            handleFileInputChange(this, 'details');
        });
    }
    
    /**
     * Generic file input change handler
     */
    function handleFileInputChange(fileInput, type) {
        const file = fileInput.files[0];
        
        if (file) {
            const validation = validateSupportingDocument(file);
            if (!validation.isValid) {
                showFormError(`${type} document: ${validation.message}`);
                fileInput.value = '';
                return;
            }
            
            displaySelectedFileInfo(file, type);
        } else {
            clearFileInfo(type);
        }
    }
    
    /**
     * Validate supporting document
     */
    function validateSupportingDocument(file) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ];
        
        if (file.size > maxSize) {
            return {
                isValid: false,
                message: 'File is too large. Maximum size allowed is 5MB.'
            };
        }
        
        if (!allowedTypes.includes(file.type)) {
            return {
                isValid: false,
                message: 'Invalid file type. Only PDF, DOCX, DOC, JPG, PNG, GIF, and WEBP files are allowed.'
            };
        }
        
        if (!isValidFilename(file.name)) {
            return {
                isValid: false,
                message: 'Invalid filename. Please avoid special characters and ensure the filename matches the file type.'
            };
        }
        
        return { isValid: true };
    }
    
    /**
     * Validate filename for security
     */
    function isValidFilename(filename) {
        const dangerousPatterns = [
            /\.php$/i,
            /\.js$/i,
            /\.exe$/i,
            /\.bat$/i,
            /\.sh$/i,
            /\..\./,
            /<script/i,
            /javascript:/i
        ];
        
        return !dangerousPatterns.some(pattern => pattern.test(filename));
    }
    
    /**
     * Display selected file information
     */
    function displaySelectedFileInfo(file, type) {
        const fileSize = formatFileSize(file.size);
        const fileName = file.name;
        const containerId = `selected-file-info-${type}`;
        const inputId = `${type}_extra_info`;
        
        let fileInfoHtml = `
            <div class="file-info-container" id="${containerId}">
                <div class="file-info-item">
                    <strong>Selected ${type} File:</strong> ${escapeHtml(fileName)}
                </div>
                <div class="file-info-item">
                    <strong>Size:</strong> ${fileSize}
                </div>
                <div class="file-info-item">
                    <strong>Type:</strong> ${getFileTypeDisplay(file.type)}
                </div>
            </div>
        `;
        
        $(`#${containerId}`).remove();
        $(`#${inputId}`).after(fileInfoHtml);
    }
    
    /**
     * Clear file information display
     */
    function clearFileInfo(type) {
        $(`#selected-file-info-${type}`).remove();
    }
    
    // ===== PERSONNEL LIST MANAGEMENT =====
    
    /**
     * Initialize personnel list functionality
     */
    function initializePersonnelList() {
        $('#repeater-wrapper_add-btn').on('click', function() {
            if (inputCounter >= MAX_INPUTS) return;
            
            inputCounter++;
            
            const newInput = $('<input>', {
                type: 'text',
                id: `personnel_list_${inputCounter}`,
                name: 'personnel_list[]',
                class: 'form-group__input',
                placeholder: `Personnel ${inputCounter}`
            });
            
            const inputWrapper = $('<div>', {
                class: 'form-group__row form-group__row--inline personnel-input-wrapper'
            });
            
            inputWrapper.append(newInput);

            $('#repeater-wrapper').append(inputWrapper);
            
            updatePersonnelButtons();
        });
        
        $('#repeater-wrapper_remove-btn').on('click', function() {
            if (inputCounter <= 0) return;
            
            $('#repeater-wrapper .personnel-input-wrapper').last().remove();
            inputCounter--;
            updatePersonnelButtons();
        });
        
        updatePersonnelButtons();
    }
    
    /**
     * Update personnel button states
     */
    function updatePersonnelButtons() {
        const addBtn = $('#repeater-wrapper_add-btn');
        const removeBtn = $('#repeater-wrapper_remove-btn');
        
        if (inputCounter >= MAX_INPUTS) {
            addBtn.prop('disabled', true).text('Maximum Reached');
        } else {
            addBtn.prop('disabled', false).html('<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 448 512"><path fill="currentColor" d="M256 64c0-17.7-14.3-32-32-32s-32 14.3-32 32v160H32c-17.7 0-32 14.3-32 32s14.3 32 32 32h160v160c0 17.7 14.3 32 32 32s32-14.3 32-32V288h160c17.7 0 32-14.3 32-32s-14.3-32-32-32H256z"></path></svg>');
        }
        
        removeBtn.prop('disabled', inputCounter <= 0);
    }
    
    // ===== FORM VALIDATION =====
    
    /**
     * Validate single selection checkbox groups
     */
    function validateSingleSelectionGroup(groupName, fieldLabel) {
        const selectedValues = $(`input[name='${groupName}']:checked`);
        
        if (selectedValues.length === 0) {
            showFormError(`Please select an option for "${fieldLabel}".`);
            return null;
        }
        
        if (selectedValues.length > 1) {
            showFormError(`Please select only one option for "${fieldLabel}".`);
            return null;
        }
        
        return selectedValues.map(function() {
            return $(this).val();
        }).get();
    }
    
    /**
     * Validate "Others" specification
     */
    function validateOtherSpecification(issuedFor) {
        if (issuedFor && issuedFor.length > 0 && issuedFor[0] === 'Others') {
            const otherSpecify = $("#other-input").val().trim();
            if (!otherSpecify) {
                showFormError('Please specify what "Others" refers to in the text field.');
                $("#other-input").focus();
                return null;
            }
            if (otherSpecify.length < 3) {
                showFormError('Please provide a more detailed description for "Others" work type (minimum 3 characters).');
                $("#other-input").focus();
                return null;
            }
            return ["Others"];
        }
        return issuedFor;
    }
    
    /**
     * Collect personnel list
     */
    function collectPersonnelList() {
        const personnelList = [];
        $('input[name="personnel_list[]"]').each(function() {
            const value = $(this).val().trim();
            if (value) {
                personnelList.push(value);
            }
        });
        
        return personnelList;
    }
    
    /**
     * Validate supporting documents for submission
     */
    function validateSupportingDocumentsForSubmission() {
        const personnelInput = $("#personnel_extra_info")[0];
        const detailsInput = $("#details_extra_info")[0];
        
        if (personnelInput && personnelInput.files && personnelInput.files.length > 0) {
            const file = personnelInput.files[0];
            const validation = validateSupportingDocument(file);
            
            if (!validation.isValid) {
                showFormError('Personnel document: ' + validation.message);
                return false;
            }
        }
        
        if (detailsInput && detailsInput.files && detailsInput.files.length > 0) {
            const file = detailsInput.files[0];
            const validation = validateSupportingDocument(file);
            
            if (!validation.isValid) {
                showFormError('Details document: ' + validation.message);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validate all form fields
     */
    function validateFormFields() {
        ('Validating form fields...');
        
        // Check basic required fields
        const requiredFields = [
            'email_address', 'phone_number', 'issued_to', 'tenant', 
            'work_area', 'tenant_field', 'requested_start_date', 
            'requested_start_time', 'requested_end_date', 'requested_end_time',
            'requester_position'
        ];
        
        for (let field of requiredFields) {
            const value = $(`#${field}`).val();
            if (!value || value.trim() === '') {
                showFormError(`Please fill in the required field: ${field.replace('_', ' ')}`);
                $(`#${field}`).focus();
                return { isValid: false };
            }
        }
        
        // Validate email format
        const email = $('#email_address').val().trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showFormError('Please enter a valid email address.');
            $('#email_address').focus();
            return { isValid: false };
        }
        
        // Validate requestor type
        const requestorType = validateSingleSelectionGroup('requestor_type[]', 'Requestor Type');
        if (!requestorType) return { isValid: false };
        
        // Validate issued for
        let issuedFor = validateSingleSelectionGroup('issued_for[]', 'Work Type (Issued For)');
        if (!issuedFor) return { isValid: false };
        
        // Validate "Others" specification
        issuedFor = validateOtherSpecification(issuedFor);
        if (!issuedFor) return { isValid: false };
        
        // Validate personnel list
        const personnelList = collectPersonnelList();
        if (!personnelList) return { isValid: false };
        
        return {
            isValid: true,
            data: {
                requestorType: requestorType[0],
                issuedFor: issuedFor[0],
                personnelList
            }
        };
    }
    
    // ===== FORM SUBMISSION =====
    
    /**
     * Initialize form submission
     */
    function initializeFormSubmission() {
        $("#work-permit-form").on("submit", function(e) {
            e.preventDefault();
            
            // Validate form fields
            const validation = validateFormFields();
            if (!validation.isValid) {
                console.log('Form validation failed');
                return;
            }
            
            // Validate supporting documents
            if (!validateSupportingDocumentsForSubmission()) {
                console.log('Document validation failed');
                return;
            }
            
            // Prepare and submit form data
            const formData = prepareFormData(validation.data);
            
            
            submitFormData(formData);
        });
    }
    
    /**
     * Prepare form data for submission
     */
    function prepareFormData(validatedData) {
        const formData = new FormData();
        
        // Add WordPress AJAX action and nonce
        formData.append('action', 'submit_work_permit');
        formData.append('nonce', $('#nonce').val() || $('input[name="nonce"]').val());
        
        // Add basic form fields
        formData.append('email_address', $('#email_address').val().trim());
        formData.append('phone_number', $('#phone_number').val().trim());
        formData.append('issued_to', $('#issued_to').val().trim());
        formData.append('tenant', $('#tenant').val().trim());
        formData.append('work_area', $('#work_area').val().trim());
        formData.append('tenant_field', $('#tenant_field').val().trim());
        formData.append('requester_position', $('#requester_position').val().trim());
        
        // Add date and time fields
        formData.append('requested_start_date', $('#requested_start_date').val());
        formData.append('requested_start_time', $('#requested_start_time').val());
        formData.append('requested_end_date', $('#requested_end_date').val());
        formData.append('requested_end_time', $('#requested_end_time').val());
        
        // Add validated selections
        formData.append('issued_for[]', validatedData.issuedFor);
        formData.append('requestor_type[]', validatedData.requestorType);
        
        // Add other_specify if "Other" was selected
        const otherSpecifyValue = $("#other-input").val().trim();
        if (otherSpecifyValue && validatedData.issuedFor.includes('Others')) {
            formData.append('other_specify', otherSpecifyValue);
        }
        
        // Add personnel list
        validatedData.personnelList.forEach((person, index) => {
            formData.append('personnel_list[]', person);
        });
        
        // Add supporting documents
        const personnelFileInput = $("#personnel_extra_info")[0];
        if (personnelFileInput && personnelFileInput.files && personnelFileInput.files.length > 0) {
            formData.append('personnel_extra_info', personnelFileInput.files[0]);
        }
        
        const detailsFileInput = $("#details_extra_info")[0];
        if (detailsFileInput && detailsFileInput.files && detailsFileInput.files.length > 0) {
            formData.append('details_extra_info', detailsFileInput.files[0]);
        }
        
        // Add email confirmation if present
        const confirmEmail = $('#confirm_email_address').val();
        if (confirmEmail) {
            formData.append('confirm_email_address', confirmEmail.trim());
        }
        
        return formData;
    }
    
    /**
     * Submit form data via AJAX
     */
    function submitFormData(formData) {
        const submitBtn = $("#submit-permit");
        const originalText = submitBtn.text();
        
        submitBtn.prop("disabled", true).text("Submitting...");
        
        // Show progress message as popup
        showFormInfo("Submitting your work permit application, please wait...");
        
        $.ajax({
            url: wps_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 30000,
            beforeSend: function() {
            },
            success: function(response) {
                hideFormMessage();
                setTimeout(() => {
                    handleSubmissionSuccess(response);
                }, 300);
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', { 
                    status: status, 
                    error: error, 
                    responseText: xhr.responseText, 
                    statusCode: xhr.status 
                });
                hideFormMessage();
                setTimeout(() => {
                    handleSubmissionError(xhr, status, error);
                }, 300);
            },
            complete: function() {
                submitBtn.prop("disabled", false).text(originalText);
            }
        });
    }
    
    /**
     * Handle successful form submission
     */
    function handleSubmissionSuccess(response) {
        if (response.success) {
            let message = response.data.message;
            
            if (response.data.has_supporting_documents) {
                const docCount = response.data.supporting_document_count || 1;
                message += `<br><br>Your ${docCount} supporting document(s) were successfully uploaded and will be included in the review process.`;
            }
            
            showFormSuccess(message);
            resetForm();
        } else {
            showFormError(response.data.message);
        }
    }
    
    /**
     * Handle form submission errors
     */
    function handleSubmissionError(xhr, status, error) {
        let errorMessage = 'An error occurred while submitting the form.';
        
        if (xhr.status === 0) {
            errorMessage = 'Network error: Please check your internet connection and try again.';
        } else if (xhr.status === 403) {
            errorMessage = 'Access forbidden. Please refresh the page and try again.';
        } else if (xhr.status === 404) {
            errorMessage = 'Submission endpoint not found. Please contact the administrator.';
        } else if (xhr.status === 413) {
            errorMessage = 'Your file is too large. Please upload a smaller document.';
        } else if (xhr.status === 500) {
            errorMessage = 'Server error. Please try again or contact the administrator.';
        } else if (status === 'timeout') {
            errorMessage = 'Request timed out. Please try again with a smaller file.';
        } else if (xhr.responseJSON && xhr.responseJSON.data) {
            errorMessage = xhr.responseJSON.data;
        } else if (xhr.responseText) {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = xhr.responseText;
            const errorText = tempDiv.textContent || tempDiv.innerText || '';
            
            if (errorText.includes('Fatal error') || errorText.includes('Parse error')) {
                errorMessage = 'Server configuration error. Please contact the administrator.';
            } else if (errorText.includes('nonce')) {
                errorMessage = 'Security token expired. Please refresh the page and try again.';
            }
        }
        
        showFormError(errorMessage);
    }
    
    /**
     * Reset form after successful submission
     */
    function resetForm() {
        $("#work-permit-form")[0].reset();
        $("#other-input-container").hide();
        clearFileInfo('personnel');
        clearFileInfo('details');
        
        // Reset personnel inputs
        $('.personnel-input-wrapper').remove();
        inputCounter = 0;
        updatePersonnelButtons();
    }
    
    // ===== UTILITY FUNCTIONS =====
    
    /**
     * Format file size for display
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    /**
     * Get display name for file type
     */
    function getFileTypeDisplay(mimeType) {
        const typeMap = {
            'application/pdf': 'PDF Document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word Document (DOCX)',
            'application/msword': 'Word Document (DOC)',
            'image/jpeg': 'JPEG Image',
            'image/png': 'PNG Image',
            'image/gif': 'GIF Image',
            'image/webp': 'WebP Image'
        };
        
        return typeMap[mimeType] || 'Unknown File Type';
    }
    
    /**
     * Escape HTML for safe display
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // ===== MAIN INITIALIZATION FUNCTION =====
    
    /**
     * Initialize all functionality
     */
    function initializeAllFunctionality() {
        initializeFormMessagePopup();
        initializeOtherFunctionality();
        initializeSingleSelection();
        initializeFileInputs();
        initializePersonnelList();
        initializeFormSubmission();
    }
    
});