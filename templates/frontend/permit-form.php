<?php
/**
 * Permit Form for tenants - Two Supporting Document Inputs
 * File: templates/frontend/permit-form.php
 */

if (!defined('ABSPATH')) {
    exit;
}

try {
    $categories = WPS_Database::get_all_categories(true);
} catch (Exception $e) {
    $categories = array();
    error_log('WPS: Error loading categories - ' . $e->getMessage());
}

// Get current date for form defaults
$current_date = current_time('Y-m-d');
$max_date = date('Y-m-d', strtotime('+5 years'));

?>

<div id="work-permit-form-container" class="wps-form-container">
    <form id="work-permit-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('submit_work_permit', 'nonce'); ?>
        
        <div class="form-header">
            <img 
                src="<?php echo esc_url('/wp-content/plugins/work-permit-system/assets/logo/lkk-mall-logo-red-rgb.png'); ?>" 
                alt="<?php echo esc_attr('LKK Mall logo in red'); ?>" 
                class="<?php echo esc_attr('brand-logo'); ?>"
                loading="<?php echo esc_attr('lazy'); ?>"
                decoding="<?php echo esc_attr('async'); ?>">
        </div>
        <div class="permit-columns" >
            <div class="permit-heading" aria-labelledby="tenant-info-heading">
                <h2><?php esc_html_e('Work Permit Application', 'work-permit-system'); ?></h2>
            </div>
            <div class="tenant-column">
                <div class="form-group form-group_col2">
                    <div class="form-group__row form-group__row--inline">
                        <label for="tenant"><?php _e('Name:', 'work-permit-system'); ?></label>
                        <input type="text" id="tenant" name="tenant" aria-describedby="tenant-help">
                    </div>

                    <div class="form-group__row form-group__row--inline">
                        <label for="phone_number"><?php _e('Phone Number:', 'work-permit-system'); ?></label>
                        <input 
                            type="text" 
                            id="phone_number" 
                            name="phone_number" 
                            maxlength="11" 
                            pattern="\d{11}" 
                            oninput="this.value = this.value.replace(/[^0-9]/g, '')" 
                            placeholder="Enter 11-digit number" 
                            required
                            aria-describedby="phone-help"
                        >
                    </div>

                    <div class="form-group__row form-group__row--inline">
                        <label for="email_address"><?php _e('Email Address:', 'work-permit-system'); ?></label>
                        <input type="text" id="email_address" name="email_address"  aria-describedby="email-help">
                    </div>

                    <div class="form-group__row ">
                            <label for="requester_position"><?php _e('Position:', 'work-permit-system'); ?></label>
                            <input type="text" id="requester_position" name="requester_position" aria-describedby="position-help">
                    </div> 

                    <div class="form-group__row form-group__row--inline">
                        <label for="issued_to"><?php _e('Issued To:', 'work-permit-system'); ?></label>
                        <input type="text" id="issued_to" name="issued_to" aria-describedby="issued-to-help" placeholder="Contractor Name/Supplier Name" >
                    </div>

                </div>

                <div class="form-group form-group_col1">

                    <div class="form-group__row form-group__row--inline">
                        <label for="work_area"><?php _e('Work Area:', 'work-permit-system'); ?></label>
                        <input type="text" id="work_area" name="work_area" placeholder="Location/Tenant" aria-describedby="work-area-help">
                    </div>

                    <fieldset class="form-group__row form-group__row--stacked">
                        <div id="repeater-wrapper" class="form-group__row form-group__row--stacked">
                            <div class="form-group__row form-group__row--inline">
                                <label for="personnel_list_1"><?php _e('List of Personnel/Workers', 'work-permit-system'); ?></label>
                            </div>
                        </div>
                        
                        <div class="form-group__row form-group__row--inline">
                            <!-- <button type="button" id="repeater-wrapper_add-btn" class="repeater-wrapper_button"><?php esc_html_e('Add More', 'work-permit-system'); ?></button> -->
                            <!-- <button type="button" id="repeater-wrapper_remove-btn" class="repeater-wrapper_button"><?php esc_html_e('Remove Last', 'work-permit-system'); ?></button> -->

                            <button type="button" id="repeater-wrapper_add-btn" class="repeater-wrapper_button">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 448 512"><path fill="currentColor" d="M256 64c0-17.7-14.3-32-32-32s-32 14.3-32 32v160H32c-17.7 0-32 14.3-32 32s14.3 32 32 32h160v160c0 17.7 14.3 32 32 32s32-14.3 32-32V288h160c17.7 0 32-14.3 32-32s-14.3-32-32-32H256z"></path></svg>
                            </button>
                            <button type="button" id="repeater-wrapper_remove-btn" class="repeater-wrapper_button">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 448 512"><path fill="currentColor" d="M0 256c0-17.7 14.3-32 32-32h384c17.7 0 32 14.3 32 32s-14.3 32-32 32H32c-17.7 0-32-14.3-32-32"/></svg>
                            </button>

                        </div>
                    </fieldset>
                </div>


                <div class="form-group">

                    <div class="form-group__row ">
                            <label for="personnel_extra_info"><?php _e('Additional Personnel Documents ', 'work-permit-system'); ?></label>
                            <small id="personnel-docs-help" class="form-help-text"><?php _e('(Optional - PDF, DOCX, DOC - Max 5MB)', 'work-permit-system'); ?></small>
                            <input type="file" id="personnel_extra_info" name="personnel_extra_info" accept=".pdf,.docx,.doc" aria-describedby="personnel-docs-help">
                        </div>
                    </div>

                    <fieldset class="form-group__row form-group__row-checkbox">
                        <legend><?php _e('Requestor Type:', 'work-permit-system'); ?></legend>
                        <label>
                        <input type="checkbox" name="requestor_type[]" value="In-house Crew/Contractor" >
                        <span><?php _e('In-house Crew/Contractor', 'work-permit-system'); ?></span>
                        </label>
                        <label>
                        <input type="checkbox" name="requestor_type[]" value="Tenant's Personnel">
                        <span><?php _e("Tenant's Personnel", 'work-permit-system'); ?></span>
                        </label>
                        <label>
                        <input type="checkbox" name="requestor_type[]" value="Tenant's Contractor/Supplier">
                        <span><?php _e("Tenant's Contractor/Supplier", 'work-permit-system'); ?></span>
                        </label>
                    </fieldset>
                    
                    <fieldset class="form-group__row form-group__row-checkbox">
                        <legend><?php _e('Issued For:', 'work-permit-system'); ?></legend>

                        <?php foreach ($categories as $category): ?>
                            <label class="category-checkbox">
                                <?php if($category->category_name === "Others"): ?>        
                                    <input type="checkbox" name="issued_for[]" value="Others" id="other-checkbox">
                                <?php else: ?>
                                    <input type="checkbox" name="issued_for[]" value="<?php echo esc_attr($category->category_name); ?>">
                                <?php endif; ?>
                                <span class="category-name"><?php echo esc_html($category->category_name); ?></span>
                            </label>
                        <?php endforeach; ?>
                        
                        <!-- Other input container -->
                        <div class="other-input-container" id="other-input-container" style="display: none;">
                            <input type="text" 
                                    id="other-input" 
                                    name="other_specify" 
                                    placeholder="<?php _e('Please specify...', 'work-permit-system'); ?>"
                                    >
                        </div>
                        
                    </fieldset>
                    
                    <div class="form-row label-full">
                        <div class="form-group__row form-group__row--stacked">
                            <label for="tenant_field"><?php _e('Details: (Please specify equipments/materials, etc.)', 'work-permit-system'); ?> 
                                <small class="form-notes"><?php _e('If you have a long personnel list more than 6 or a detailed description, you may attach supporting documents below.','work-permit-system') ?></small>
                            </label>
                            <textarea id="tenant_field" name="tenant_field" rows="5" cols="50" required 
                                placeholder="<?php _e('Please describe the work you plan to perform...', 'work-permit-system'); ?>" aria-describedby="work-description-help"></textarea>
                        </div>
                    </div>

                    <div class="form-row input-full">
                        <div class="form-group__row ">
                            <label for="details_extra_info"><?php _e('Additional Details Documents', 'work-permit-system'); ?></label>
                            <small id="details-docs-help" class="form-help-text"><?php _e('(Optional – PDF, DOCX, DOC – Max 5MB)', 'work-permit-system'); ?></small>
                            <input type="file" id="details_extra_info" name="details_extra_info" accept=".pdf,.docx,.doc" aria-describedby="details-docs-help">
                        </div>
                    </div>

                    <fieldset class="form-group__row form-group__row-datetime">
                        <legend><?php _e('Requested Time and Date:', 'work-permit-system'); ?></legend>
                        <div class="form-group__row form-group__row--inline">
                            <label for="requested_start_date"><?php _e('Date from:', 'work-permit-system'); ?></label>
                            <input type="date" id="requested_start_date" name="requested_start_date" min="2000-01-01" max="<?php echo esc_attr($max_date); ?>" value="<?php echo esc_attr($current_date); ?>">
                        </div>
                        <div class="form-group__row form-group__row--inline">
                            <label for="requested_end_date"><?php _e('Date to:', 'work-permit-system'); ?></label>
                            <input type="date" id="requested_end_date" name="requested_end_date" min="2000-01-01" max="<?php echo esc_attr($max_date); ?>" value="<?php echo esc_attr($current_date); ?>">
                        </div>
                        <div class="form-group__row form-group__row--inline">
                            <label for="requested_start_time"><?php _e('Time from:', 'work-permit-system'); ?></label>
                            <input type="time" id="requested_start_time" name="requested_start_time">
                        </div>
                        <div class="form-group__row form-group__row--inline">
                            <label for="requested_end_time"><?php _e('Time to:', 'work-permit-system'); ?></label>
                            <input type="time" id="requested_end_time" name="requested_end_time">
                        </div>
                    </fieldset>

                    <div class="form-group form-group__row-submit">
                        <div class="form-row">
                            <div class="form-group__row ">
                                <button type="submit" id="submit-permit"><?php _e('Submit', 'work-permit-system'); ?></button>
                            </div>
                        </div>
                    </div>
                            
                </div>


            </div>

            
        </div>
        
        
        <!-- <div id="form-message"></div> -->
         <div id="form-message-modal" class="form-message-popup" style="display: none;">
            <div class="form-message-popup-overlay"></div>
            <div class="form-message-popup-content">
                <div class="form-message-popup-header">
                    <h3 id="form-message-title">Notification</h3>
                    <button type="button" class="form-message-popup-close" aria-label="Close">&times;</button>
                </div>
                <div class="form-message-popup-body">
                    <!-- <div id="form-message-icon"></div> -->
                    <div id="form-message-text"></div>
                </div>
                <div class="form-message-popup-footer">
                    <button type="button" class="form-message-popup-btn" id="form-message-ok-btn">OK</button>
                </div>
            </div>
        </div>
    </form>
</div>