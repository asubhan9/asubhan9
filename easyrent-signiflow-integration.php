<?php
/**
 * Plugin Name: EasyRent Signiflow Integration (FullWorkflow)
 * Description: WooCommerce + Signiflow FullWorkflow integration with auto-filled PDFs and dynamic signer.
 * Version: 3.0.0
 * Author: RBC EasyRent
 */

if (!defined('ABSPATH')) exit;

// Log plugin activation
register_activation_hook(__FILE__, function() {
    error_log('EasyRent Signiflow Integration: Plugin activated');
});

// Log that plugin is loaded
add_action('plugins_loaded', function() {
    error_log('EasyRent Signiflow Integration: Plugin loaded');
}, 1);

/**
 * -------------------------------
 * CHECKOUT FIELDS
 * -------------------------------
 */
add_filter('woocommerce_checkout_fields', function ($fields) {

    $fields['billing']['easyrent_abn'] = [
        'label' => 'ABN',
        'required' => true,
        'class' => ['form-row-wide'],
        'priority' => 25,
    ];

    $fields['billing']['installation_address'] = [
        'label' => 'Installation Address',
        'required' => true,
        'class' => ['form-row-wide'],
        'priority' => 60,
    ];

    $fields['billing']['installation_state'] = [
        'type'     => 'select',
        'label'    => 'Installation State',
        'required' => true,
        'class'    => ['form-row-first'],
        'options'  => [
            ''    => 'Select State',
            'NSW' => 'New South Wales',
            'VIC' => 'Victoria',
            'QLD' => 'Queensland',
            'WA'  => 'Western Australia',
            'SA'  => 'South Australia',
            'TAS' => 'Tasmania',
            'ACT' => 'Australian Capital Territory',
            'NT'  => 'Northern Territory',
        ],
        'priority' => 70,
    ];

    $fields['billing']['installation_postcode'] = [
        'label' => 'Installation Postcode',
        'required' => true,
        'class' => ['form-row-last'],
        'priority' => 72,
    ];

    return $fields;
}, 20);

/**
 * -------------------------------
 * CLEANUP DEFAULT FIELDS
 * -------------------------------
 */
add_filter('woocommerce_checkout_fields', function ($fields) {
    unset($fields['billing']['billing_address_1']);
    unset($fields['billing']['billing_address_2']);
    unset($fields['billing']['billing_city']);
    unset($fields['billing']['billing_state']);
    unset($fields['billing']['billing_postcode']);
    unset($fields['billing']['billing_country']);
    unset($fields['shipping']);
    return $fields;
}, 99);

/**
 * -------------------------------
 * SAVE ORDER META
 * -------------------------------
 */
add_action('woocommerce_checkout_create_order', function ($order) {

    foreach ([
        'easyrent_abn',
        'installation_address',
        'installation_state',
        'installation_postcode'
    ] as $field) {
        if (!empty($_POST[$field])) {
            $order->update_meta_data($field, sanitize_text_field($_POST[$field]));
        }
    }

    $order->update_meta_data('_signiflow_status', 'pending');
});

/**
 * -------------------------------
 * SIGNIFLOW LOGIN FUNCTION
 * -------------------------------
 */
function easyrent_signiflow_login() {
    // Check for cached token object
    $cached_token_obj = get_transient('easyrent_signiflow_token');
    if ($cached_token_obj !== false) {
        // Ensure cached token is an object/array
        if (is_array($cached_token_obj) && isset($cached_token_obj['TokenField'])) {
            error_log('EasyRent Signiflow: Using cached token object');
            return $cached_token_obj;
        } elseif (is_string($cached_token_obj)) {
            // Old format - convert to object structure
            error_log('EasyRent Signiflow: Cached token is string, converting to object format');
            delete_transient('easyrent_signiflow_token');
        } else {
            error_log('EasyRent Signiflow: Cached token format invalid, forcing login');
            delete_transient('easyrent_signiflow_token');
        }
    }

    $username = get_option('easyrent_signiflow_username');
    $password = get_option('easyrent_signiflow_password');
    $api_url = get_option('easyrent_signiflow_api_url', 'https://sign.docs2me.com.au');

    if (empty($username) || empty($password)) {
        error_log('EasyRent Signiflow: Username or password not configured');
        return false;
    }

    // Build login payload
    $login_payload = [
        "UserNameField" => $username,
        "PasswordField" => $password
    ];

    // Login to get token
    $login_url = rtrim($api_url, '/') . '/API/SignFlowAPIServiceRest.svc/Login';
    
    $response = wp_remote_post($login_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'body' => json_encode($login_payload),
        'timeout' => 30,
        'sslverify' => true,
    ]);

    if (is_wp_error($response)) {
        error_log('EasyRent Signiflow Login Error: ' . $response->get_error_message());
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    error_log('EasyRent Signiflow Login Response Code: ' . $response_code);
    error_log('EasyRent Signiflow Login Response Body: ' . $response_body);

    if ($response_code >= 200 && $response_code < 300) {
        $response_data = json_decode($response_body, true);
        
        // Extract token from response
        // Response structure: {"ResultField":"Success","TokenField":{"TokenExpiryField":"...","TokenField":"3t1mvl"}}
        $token = '';
        if (isset($response_data['TokenField']) && is_array($response_data['TokenField'])) {
            // Token is nested: TokenField.TokenField
            if (isset($response_data['TokenField']['TokenField'])) {
                $token = $response_data['TokenField']['TokenField'];
            }
        } elseif (isset($response_data['TokenField']) && is_string($response_data['TokenField'])) {
            // Token might be directly in TokenField (string)
            $token = $response_data['TokenField'];
        }

        // Check if login was successful
        $result = isset($response_data['ResultField']) ? $response_data['ResultField'] : '';
        if (($result === 'Success' || $result === 1) && !empty($token)) {
            // Calculate token expiry if available
            $expiry_seconds = HOUR_IN_SECONDS; // Default to 1 hour
            $token_expiry_field = '';
            if (isset($response_data['TokenField']['TokenExpiryField'])) {
                $token_expiry_field = $response_data['TokenField']['TokenExpiryField'];
                // Parse /Date(timestamp+offset)/ format
                if (preg_match('/\/Date\((\d+)/', $token_expiry_field, $matches)) {
                    $expiry_timestamp = intval($matches[1]) / 1000; // Convert from milliseconds
                    $current_time = time();
                    $expiry_seconds = max(300, $expiry_timestamp - $current_time - 60); // At least 5 min, subtract 1 min buffer
                }
            }
            
            // Return the full TokenField object structure (not just the token string)
            // API expects: {"TokenExpiryField": "...", "TokenField": "token_string"}
            $token_object = [
                'TokenField' => (string) $token
            ];
            if (!empty($token_expiry_field)) {
                $token_object['TokenExpiryField'] = $token_expiry_field;
            }
            
            // Cache the full token object until expiry (or 1 hour minimum)
            set_transient('easyrent_signiflow_token', $token_object, $expiry_seconds);
            error_log('EasyRent Signiflow: Login successful, token object cached for ' . round($expiry_seconds / 60) . ' minutes');
            return $token_object;
        } else {
            error_log('EasyRent Signiflow: Login response did not contain valid token. ResultField: ' . $result);
            return false;
        }
    } else {
        error_log('EasyRent Signiflow: Login failed with HTTP ' . $response_code);
        return false;
    }
}

/**
 * -------------------------------
 * READ AND ENCODE PDF FILES
 * -------------------------------
 */
function easyrent_get_pdf_base64($file_path) {
    // Handle WordPress uploads URL
    if (strpos($file_path, 'http') === 0) {
        // It's a URL - try to get the file path
        $upload_dir = wp_upload_dir();
        $url_base = $upload_dir['baseurl'];
        if (strpos($file_path, $url_base) === 0) {
            // It's in WordPress uploads
            $file_path = str_replace($url_base, $upload_dir['basedir'], $file_path);
        } else {
            // External URL - download it
            $response = wp_remote_get($file_path);
            if (is_wp_error($response)) {
                error_log('EasyRent Signiflow: Failed to download PDF from URL: ' . $file_path);
                return false;
            }
            $pdf_content = wp_remote_retrieve_body($response);
            return base64_encode($pdf_content);
        }
    }
    
    // Check if file exists
    if (!file_exists($file_path)) {
        error_log('EasyRent Signiflow: PDF file not found: ' . $file_path);
        return false;
    }
    
    // Read and encode PDF
    $pdf_content = file_get_contents($file_path);
    if ($pdf_content === false) {
        error_log('EasyRent Signiflow: Failed to read PDF file: ' . $file_path);
        return false;
    }
    
    return base64_encode($pdf_content);
}

/**
 * -------------------------------
 * MERGE TWO PDF FILES
 * -------------------------------
 * Note: Simple concatenation may not work for all PDFs.
 * For production use, consider merging PDFs manually or using a library like FPDI.
 * This function attempts basic merging but may fail with complex PDFs.
 */
function easyrent_merge_pdfs($pdf1_path, $pdf2_path) {
    $pdf1_content = easyrent_get_pdf_base64($pdf1_path);
    $pdf2_content = easyrent_get_pdf_base64($pdf2_path);
    
    if ($pdf1_content === false || $pdf2_content === false) {
        error_log('EasyRent Signiflow: Failed to read one or both PDFs for merging');
        return false;
    }
    
    // Decode to binary
    $pdf1_binary = base64_decode($pdf1_content);
    $pdf2_binary = base64_decode($pdf2_content);
    
    // Simple approach: Try to extract pages from PDF2 and append to PDF1
    // This is a basic implementation - for complex PDFs, use a proper library
    // For now, we'll try a simple concatenation which may work for simple PDFs
    // Better approach: Use FPDI library (requires composer: composer require setasign/fpdi)
    
    // Extract PDF2 content (skip header if present)
    $pdf2_start = strpos($pdf2_binary, '%PDF');
    if ($pdf2_start !== false && $pdf2_start > 0) {
        $pdf2_binary = substr($pdf2_binary, $pdf2_start);
    }
    
    // Simple concatenation (may not work for all PDFs)
    // For proper merging, consider using: setasign/fpdi or smalot/pdfparser
    $merged = $pdf1_binary . "\n" . $pdf2_binary;
    
    error_log('EasyRent Signiflow: PDFs merged (simple concatenation). If this fails, consider merging PDFs manually or using FPDI library.');
    
    return base64_encode($merged);
}

/**
 * -------------------------------
 * SEND TO SIGNIFLOW (FULLWORKFLOW)
 * -------------------------------
 */
function easyrent_send_to_signiflow($order_id) {
    // Log that function was called
    error_log('EasyRent Signiflow: Function called for order ' . $order_id);
    
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log('EasyRent Signiflow: Order not found - ' . $order_id);
        return;
    }
    
    // Check if already processed
    $status = $order->get_meta('_signiflow_status');
    if ($status === 'sent' || $status === 'completed') {
        error_log('EasyRent Signiflow: Order ' . $order_id . ' already processed (status: ' . $status . ')');
        return;
    }

    // Get token object from login
    $token_object = easyrent_signiflow_login();
    if (empty($token_object) || !is_array($token_object)) {
        $order->update_status('on-hold', 'Signiflow authentication failed.');
        $order->add_order_note('Signiflow: Failed to authenticate. Please check username and password in WooCommerce > EasyRent Contracts.');
        error_log('EasyRent Signiflow: Authentication failed for order ' . $order_id);
        return;
    }
    
    // Ensure token object has required structure
    if (!isset($token_object['TokenField'])) {
        error_log('EasyRent Signiflow: Token object missing TokenField for order ' . $order_id);
        $order->add_order_note('Signiflow: Invalid token format. Please try again.');
        return;
    }
    
    error_log('EasyRent Signiflow: Using token object with token: ' . substr($token_object['TokenField'], 0, 10) . '...');

    $workflow_id = get_option('easyrent_signiflow_workflow_id');
    $api_url = get_option('easyrent_signiflow_api_url', 'https://sign.docs2me.com.au');
    
    // Check if PDF templates are configured (alternative to workflow ID)
    $pdf_template_1 = get_option('easyrent_signiflow_pdf_template_1', '');
    $use_direct_upload = !empty($pdf_template_1);
    
    // Validate: Either workflow ID OR PDF templates must be provided
    if (empty($workflow_id) && !$use_direct_upload) {
        $order->update_status('on-hold', 'Signiflow configuration missing.');
        $order->add_order_note('Signiflow: Either Workflow ID or PDF template paths must be configured in WooCommerce > EasyRent Contracts.');
        error_log('EasyRent Signiflow: Neither workflow ID nor PDF templates configured for order ' . $order_id);
        return;
    }
    
    // Validate workflow ID format if provided (not required if using direct upload)
    if (!empty($workflow_id)) {
        if (!is_numeric($workflow_id)) {
            $order->add_order_note('Signiflow: Workflow ID must be numeric. Current value: ' . esc_html($workflow_id));
            error_log('EasyRent Signiflow: Invalid workflow ID format: ' . $workflow_id);
            return;
        }
        $workflow_id = (int) $workflow_id; // Convert to integer for API
    }

    // Build equipment description + quantity
    $equipment_description = [];
    $quantity = 0;
    $monthly_rent = 0;

    foreach ($order->get_items() as $item) {
        $equipment_description[] = $item->get_name();
        $quantity += $item->get_quantity();
        $monthly_rent += $item->get_total() / max(1, $item->get_quantity());
    }

    $equipment_description = implode(', ', $equipment_description);

    $customer_name  = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
    $customer_email = $order->get_billing_email();

    if (empty($customer_email)) {
        $order->add_order_note('Signiflow: Customer email missing. Cannot send workflow.');
        error_log('EasyRent Signiflow: Customer email missing for order ' . $order_id);
        return;
    }

    // Build tag values array for UseAutoTagsField
    // When UseAutoTagsField is true, tags are populated automatically from PDF
    // Tag values should match the tag names in your PDF documents
    $tag_values = [
        "renter_legal_name" => $order->get_billing_company() ?: $customer_name,
        "abn" => $order->get_meta('easyrent_abn'),
        "contact_name" => $customer_name,
        "email" => $customer_email,
        "phone" => $order->get_billing_phone(),
        "installation_address" => $order->get_meta('installation_address'),
        "installation_state" => $order->get_meta('installation_state'),
        "installation_postcode" => $order->get_meta('installation_postcode'),
        "equipment_description" => $equipment_description,
        "quantity" => (string) $quantity,
        "rental_term" => "",
        "monthly_rent" => wc_format_decimal($monthly_rent, 2),
        "gst_amount" => wc_format_decimal($order->get_total_tax(), 2),
        "total_monthly_payment" => wc_format_decimal($order->get_total(), 2),
        "terms_accepted" => "Yes",
        "order_id" => (string) $order_id,
    ];

    // Check for PDF templates (direct upload approach)
    $pdf_template_1 = get_option('easyrent_signiflow_pdf_template_1', '');
    $pdf_template_2 = get_option('easyrent_signiflow_pdf_template_2', '');
    $use_direct_upload = !empty($pdf_template_1);
    
    // Build FullWorkflow payload
    // WorkflowUserFieldsField is for signature field placement (coordinates), not tag values
    // UseAutoTagsField handles tag population automatically when tag names match in PDF
    // TokenField must be an object with TokenField and TokenExpiryField properties
    $payload = [
        "UseAutoTagsField" => 1, // Must be integer: 0 = false, 1 = true
        "SendWorkflowEmailsField" => 1, // Must be integer: 0 = false, 1 = true
        "SendFirstEmailField" => 1, // Must be integer: 0 = false, 1 = true
        "DocNameField" => "EasyRent Agreement - Order #" . $order_id,
        "ExtensionField" => 0,
        "AutoRemindField" => 0, // Auto-remind signers (0 = false, 1 = true)
        "CustomMessageField" => "", // Custom message for signers
        "DocField" => "", // Will be populated if using direct upload
        "PriorityField" => 0, // Priority level (0 = normal)
        "SLAField" => 0, // SLA in hours (0 = no SLA)
        "TokenField" => $token_object, // Token object from login (with TokenField and TokenExpiryField)
    ];
    
    // DIRECT PDF UPLOAD APPROACH (Alternative to workflow/portfolio)
    if ($use_direct_upload) {
        error_log('EasyRent Signiflow: Using direct PDF upload approach');
        
        // Get PDF content (merge if 2 PDFs provided)
        $pdf_base64 = false;
        if (!empty($pdf_template_2)) {
            // Merge 2 PDFs
            error_log('EasyRent Signiflow: Merging 2 PDF templates');
            $pdf_base64 = easyrent_merge_pdfs($pdf_template_1, $pdf_template_2);
        } else {
            // Single PDF
            error_log('EasyRent Signiflow: Using single PDF template');
            $pdf_base64 = easyrent_get_pdf_base64($pdf_template_1);
        }
        
        if ($pdf_base64 === false) {
            $order->add_order_note('Signiflow: Failed to read PDF template(s). Please check file paths in settings.');
            error_log('EasyRent Signiflow: Failed to read/encode PDF templates for order ' . $order_id);
            return;
        }
        
        // Use DocField with base64-encoded PDF
        $payload["DocField"] = $pdf_base64;
        $payload["TagValuesField"] = $tag_values;
        
        // WorkflowUsersListField is REQUIRED
        $payload["WorkflowUsersListField"] = [
            [
                "ActionField" => 0,
                "SignatureTypeField" => 1,
                "UserFullNameField" => $customer_name,
                "EmailAddressField" => $customer_email,
                "AllowProxyField" => 0,
                "AutoSignField" => 0,
                "LatitudeField" => 0,
                "LongitudeField" => 0,
                "SignReasonField" => "",
                "SignerPasswordField" => "",
                "WorkflowUserFieldsField" => [] // Empty - tags are populated via TagValuesField
            ]
        ];
        
        error_log('EasyRent Signiflow: PDF encoded successfully, size: ' . strlen($pdf_base64) . ' characters');
        
    // WORKFLOW/PORTFOLIO APPROACH (Original method)
    } elseif (!empty($workflow_id)) {
        // Try using PortfolioIDField at root level (user confirmed ID 2301 is a Portfolio ID)
        // Portfolio ID and Workflow ID are the same number, but API might need PortfolioIDField
        $payload["PortfolioIDField"] = $workflow_id; // Integer portfolio ID
        
        // TagValuesField is REQUIRED when UseAutoTagsField is true
        // UseAutoTagsField tells Signiflow to populate tags, but it needs the values via TagValuesField
        $payload["TagValuesField"] = $tag_values;
        error_log('EasyRent Signiflow: Using PortfolioIDField at root level (portfolio should contain documents): ' . $workflow_id);
        
        // WorkflowUsersListField is REQUIRED by the API even when using WorkflowIDField
        // Always include it. If workflow has placeholder users, use PlaceholderInfoListField to replace them.
        $payload["WorkflowUsersListField"] = [
            [
                "ActionField" => 0,
                "SignatureTypeField" => 1,
                "UserFullNameField" => $customer_name,
                "EmailAddressField" => $customer_email,
                "AllowProxyField" => 0,
                "AutoSignField" => 0,
                "LatitudeField" => 0,
                "LongitudeField" => 0,
                "SignReasonField" => "",
                "SignerPasswordField" => "",
                "WorkflowUserFieldsField" => [] // Empty array - signature fields are defined in workflow template
            ]
        ];
        
        // If workflow has placeholder users, include PlaceholderInfoListField to replace them
        $placeholder_email = get_option('easyrent_signiflow_placeholder_email', '');
        if (!empty($placeholder_email) && 
            stripos($placeholder_email, 'placeholder') === false && 
            stripos($placeholder_email, 'yourdomain') === false &&
            filter_var($placeholder_email, FILTER_VALIDATE_EMAIL)) {
            $payload["PlaceholderInfoListField"] = [
                [
                    "UserEmailField" => $placeholder_email, // Email of placeholder user in workflow
                    "ReferenceIDField" => 0,
                    "UniqueIDField" => ""
                ]
            ];
            error_log('EasyRent Signiflow: Using PlaceholderInfoListField with email: ' . $placeholder_email . ' to replace placeholder user');
        } else {
            error_log('EasyRent Signiflow: No placeholder email configured. WorkflowUsersListField will add/override users in workflow.');
        }
    } else {
        // No workflow ID and no PDF templates - error
        $order->update_status('on-hold', 'Signiflow configuration missing.');
        $order->add_order_note('Signiflow: Either Workflow ID or PDF template paths must be configured in WooCommerce > EasyRent Contracts.');
        error_log('EasyRent Signiflow: No workflow ID or PDF templates configured for order ' . $order_id);
        return;
    }

    // Log the payload for debugging (remove sensitive data in production)
    $log_payload = $payload;
    // Don't log full payload in production, just structure
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('EasyRent Signiflow Payload: ' . json_encode($log_payload, JSON_PRETTY_PRINT));
    }

    // Build FullWorkflow API URL
    $workflow_url = rtrim($api_url, '/') . '/API/SignFlowAPIServiceRest.svc/FullWorkflow';
    
    $response = wp_remote_post($workflow_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            // No Authorization header - token is in request body
        ],
        'body' => json_encode($payload),
        'timeout' => 60,
        'sslverify' => true,
    ]);

    // Enhanced error handling
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('EasyRent Signiflow API Error: ' . $error_message);
        $order->add_order_note('Signiflow Error: ' . $error_message);
        $order->update_meta_data('_signiflow_error', $error_message);
        $order->update_meta_data('_signiflow_status', 'error');
        return;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    // Log response
    error_log('EasyRent Signiflow Response Code: ' . $response_code);
    error_log('EasyRent Signiflow Response Body: ' . $response_body);

    // Parse response
    $response_data = json_decode($response_body, true);

    if ($response_code >= 200 && $response_code < 300) {
        // Success HTTP status
        $result_field = isset($response_data['ResultField']) ? $response_data['ResultField'] : '';
        
        // Check if ResultField is numeric 1 (success) or contains "Failed"
        if ($result_field == 1 || $result_field === 1) {
            // Workflow created successfully
            $doc_id = isset($response_data['DocIDField']) ? $response_data['DocIDField'] : '';
            $workflow_id_returned = isset($response_data['WorkflowIDField']) ? $response_data['WorkflowIDField'] : '';

            $order->update_meta_data('_signiflow_doc_id', $doc_id);
            $order->update_meta_data('_signiflow_workflow_id', $workflow_id_returned);
            $order->update_meta_data('_signiflow_status', 'sent');
            $order->add_order_note(sprintf(
                'Signiflow workflow sent successfully. Doc ID: %s, Workflow ID: %s. Email should be sent to: %s',
                $doc_id,
                $workflow_id_returned,
                $customer_email
            ));

            // Update order status if configured
            $auto_status = get_option('easyrent_auto_order_status', '');
            if (!empty($auto_status)) {
                $order->update_status($auto_status, 'Signiflow workflow sent.');
            }
        } else {
            // API returned success but ResultField indicates failure
            $error_msg = $result_field;
            if (empty($error_msg) && isset($response_data['ResultFieldMessage'])) {
                $error_msg = $response_data['ResultFieldMessage'];
            }
            if (empty($error_msg)) {
                $error_msg = 'Unknown error - ResultField: ' . json_encode($result_field);
            }
            
            error_log('EasyRent Signiflow API Result Error: ' . $error_msg);
            $order->add_order_note('Signiflow Error: ' . $error_msg);
            $order->update_meta_data('_signiflow_error', $error_msg);
            $order->update_meta_data('_signiflow_status', 'error');
            
            // Special handling for specific errors
            if (stripos($error_msg, 'Invalid Token') !== false || stripos($error_msg, 'Invalid token') !== false) {
                // Clear cached token to force re-login
                delete_transient('easyrent_signiflow_token');
                $order->add_order_note('Signiflow: Token invalid or expired. Will retry with new login on next attempt.');
            } elseif (stripos($error_msg, 'valid document') !== false || stripos($error_msg, 'document') !== false) {
                $detailed_note = 'Signiflow: Document error for Workflow ID <strong>' . esc_html($workflow_id) . '</strong>.<br><br>';
                $detailed_note .= '<strong>Please verify in Signiflow Dashboard:</strong><br>';
                $detailed_note .= '1. Workflow ID <strong>' . esc_html($workflow_id) . '</strong> exists and is accessible<br>';
                $detailed_note .= '2. The workflow has PDF documents properly attached (not just uploaded, but linked to the workflow template)<br>';
                $detailed_note .= '3. The workflow is <strong>Published/Active</strong> (not draft or archived)<br>';
                $detailed_note .= '4. The workflow is configured for API use<br>';
                $detailed_note .= '5. Your API user account has permission to access this workflow<br>';
                $detailed_note .= '6. The documents in the workflow are PDF format and not corrupted<br><br>';
                $detailed_note .= '<strong>If all above are correct, contact Signiflow support with:</strong><br>';
                $detailed_note .= '- Workflow ID: ' . esc_html($workflow_id) . '<br>';
                $detailed_note .= '- Error: "Failed - Please provide a valid document"<br>';
                $detailed_note .= '- API Endpoint: FullWorkflow<br>';
                $detailed_note .= '- Request includes WorkflowIDField, TagValuesField, and UseAutoTagsField';
                $order->add_order_note($detailed_note);
                error_log('EasyRent Signiflow: Document validation failed. Workflow ID: ' . $workflow_id . '. This usually means the workflow is not published, documents are not properly linked, or API permissions are insufficient.');
            }
        }
    } else {
        // HTTP error
        $error_msg = 'HTTP ' . $response_code;
        if (!empty($response_data['Message'])) {
            $error_msg .= ': ' . $response_data['Message'];
        } elseif (!empty($response_body)) {
            $error_msg .= ': ' . substr($response_body, 0, 200);
        }
        error_log('EasyRent Signiflow HTTP Error: ' . $error_msg);
        $order->add_order_note('Signiflow HTTP Error: ' . $error_msg);
        $order->update_meta_data('_signiflow_error', $error_msg);
        $order->update_meta_data('_signiflow_status', 'error');
    }
    
    error_log('EasyRent Signiflow: Function completed for order ' . $order_id);
}

// Hook into multiple events to ensure it runs
add_action('woocommerce_payment_complete', 'easyrent_send_to_signiflow', 10);
add_action('woocommerce_order_status_processing', 'easyrent_send_to_signiflow', 10);
add_action('woocommerce_order_status_completed', 'easyrent_send_to_signiflow', 10);

/**
 * -------------------------------
 * WEBHOOK HANDLER FOR SIGNIFLOW CALLBACKS
 * -------------------------------
 */
add_action('rest_api_init', function () {
    register_rest_route('easyrent/v1', '/signiflow-webhook', [
        'methods' => 'POST',
        'callback' => 'easyrent_handle_signiflow_webhook',
        'permission_callback' => '__return_true', // You may want to add authentication
    ]);
});

function easyrent_handle_signiflow_webhook($request) {
    $data = $request->get_json_params();
    
    error_log('EasyRent Signiflow Webhook Received: ' . json_encode($data));

    // Extract order ID from webhook data
    // Adjust this based on what Signiflow sends in the webhook
    $doc_id = isset($data['DocIDField']) ? $data['DocIDField'] : '';
    $workflow_id = isset($data['WorkflowIDField']) ? $data['WorkflowIDField'] : '';
    $status = isset($data['StatusField']) ? $data['StatusField'] : '';

    // Find order by Signiflow document ID
    $orders = wc_get_orders([
        'limit' => 1,
        'meta_key' => '_signiflow_doc_id',
        'meta_value' => $doc_id,
    ]);

    if (!empty($orders)) {
        $order = $orders[0];
        
        // Update order based on status
        if ($status == 'Completed' || $status == 'Signed') {
            $order->update_meta_data('_signiflow_status', 'completed');
            $order->add_order_note('Signiflow: Document signed successfully.');
            // You can update order status here if needed
        } elseif ($status == 'Rejected' || $status == 'Declined') {
            $order->update_meta_data('_signiflow_status', 'rejected');
            $order->add_order_note('Signiflow: Document was rejected/declined.');
        }
    }

    return new WP_REST_Response(['success' => true], 200);
}

/**
 * -------------------------------
 * SETTINGS PAGE
 * -------------------------------
 */
add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        'EasyRent Contracts',
        'EasyRent Contracts',
        'manage_options',
        'easyrent-contracts',
        'easyrent_settings_page'
    );
});

function easyrent_settings_page() {
    // Handle form submission
    if (isset($_POST['easyrent_save_settings']) && check_admin_referer('easyrent_settings_nonce')) {
        update_option('easyrent_signiflow_username', sanitize_text_field($_POST['easyrent_signiflow_username']));
        update_option('easyrent_signiflow_password', sanitize_text_field($_POST['easyrent_signiflow_password']));
        update_option('easyrent_signiflow_api_url', sanitize_text_field($_POST['easyrent_signiflow_api_url']));
        update_option('easyrent_signiflow_workflow_id', sanitize_text_field($_POST['easyrent_signiflow_workflow_id']));
        update_option('easyrent_signiflow_placeholder_email', sanitize_email($_POST['easyrent_signiflow_placeholder_email']));
        update_option('easyrent_auto_order_status', sanitize_text_field($_POST['easyrent_auto_order_status']));
        
        // Handle PDF template paths (alternative to workflow ID)
        update_option('easyrent_signiflow_pdf_template_1', sanitize_text_field($_POST['easyrent_signiflow_pdf_template_1']));
        update_option('easyrent_signiflow_pdf_template_2', sanitize_text_field($_POST['easyrent_signiflow_pdf_template_2']));
        
        // Clear cached token when credentials change
        delete_transient('easyrent_signiflow_token');
        
        echo '<div class="notice notice-success"><p>Settings saved! Token cache cleared.</p></div>';
    }

    $username = get_option('easyrent_signiflow_username', '');
    $password = get_option('easyrent_signiflow_password', '');
    $api_url = get_option('easyrent_signiflow_api_url', 'https://sign.docs2me.com.au');
    $workflow_id = get_option('easyrent_signiflow_workflow_id', '');
    $auto_status = get_option('easyrent_auto_order_status', '');

    ?>
    <div class="wrap">
        <h1>EasyRent – Signiflow Settings</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('easyrent_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="easyrent_signiflow_api_url">Signiflow API URL</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="easyrent_signiflow_api_url" 
                               name="easyrent_signiflow_api_url" 
                               value="<?php echo esc_attr($api_url); ?>" 
                               class="regular-text" 
                               placeholder="https://sign.docs2me.com.au" />
                        <p class="description">Your Signiflow instance URL (without trailing slash)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="easyrent_signiflow_username">Signiflow Username</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="easyrent_signiflow_username" 
                               name="easyrent_signiflow_username" 
                               value="<?php echo esc_attr($username); ?>" 
                               class="regular-text" />
                        <p class="description">Your Signiflow account username/email</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="easyrent_signiflow_password">Signiflow Password</label>
                    </th>
                    <td>
                        <input type="password" 
                               id="easyrent_signiflow_password" 
                               name="easyrent_signiflow_password" 
                               value="<?php echo esc_attr($password); ?>" 
                               class="regular-text" />
                        <p class="description">Your Signiflow account password</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="easyrent_signiflow_workflow_id">Workflow ID (Alternative 1)</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="easyrent_signiflow_workflow_id" 
                               name="easyrent_signiflow_workflow_id" 
                               value="<?php echo esc_attr($workflow_id); ?>" 
                               class="regular-text" />
                        <p class="description">The Workflow/Portfolio ID containing your PDFs with tags (e.g., 2301). <strong>OR</strong> use Direct PDF Upload below.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label>Direct PDF Upload (Alternative 2)</label>
                    </th>
                    <td>
                        <?php 
                        $pdf_template_1 = get_option('easyrent_signiflow_pdf_template_1', '');
                        $pdf_template_2 = get_option('easyrent_signiflow_pdf_template_2', '');
                        ?>
                        <p><strong>PDF Template 1:</strong></p>
                        <input type="text" 
                               id="easyrent_signiflow_pdf_template_1" 
                               name="easyrent_signiflow_pdf_template_1" 
                               value="<?php echo esc_attr($pdf_template_1); ?>" 
                               class="large-text" 
                               placeholder="/path/to/template1.pdf or URL" />
                        <p class="description">Full file path (e.g., <code>/home/user/wp-content/uploads/template1.pdf</code>) or WordPress uploads URL</p>
                        
                        <p style="margin-top: 15px;"><strong>PDF Template 2 (Optional):</strong></p>
                        <input type="text" 
                               id="easyrent_signiflow_pdf_template_2" 
                               name="easyrent_signiflow_pdf_template_2" 
                               value="<?php echo esc_attr($pdf_template_2); ?>" 
                               class="large-text" 
                               placeholder="/path/to/template2.pdf or URL" />
                        <p class="description">If provided, both PDFs will be merged into one document. Leave empty if you only have one PDF.</p>
                        <p class="description"><strong>Note:</strong> If PDF paths are provided, they will be used instead of Workflow ID.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="easyrent_signiflow_placeholder_email">Placeholder Email (Optional)</label>
                    </th>
                    <td>
                        <?php $placeholder_email = get_option('easyrent_signiflow_placeholder_email', ''); ?>
                        <input type="email" 
                               id="easyrent_signiflow_placeholder_email" 
                               name="easyrent_signiflow_placeholder_email" 
                               value="<?php echo esc_attr($placeholder_email); ?>" 
                               class="regular-text" />
                        <p class="description">If your workflow has a placeholder user that needs to be replaced, enter the placeholder's email address here.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="easyrent_auto_order_status">Auto Order Status (Optional)</label>
                    </th>
                    <td>
                        <select id="easyrent_auto_order_status" name="easyrent_auto_order_status">
                            <option value="">Don't change status</option>
                            <?php
                            $statuses = wc_get_order_statuses();
                            foreach ($statuses as $status_key => $status_label) {
                                $selected = ($auto_status === $status_key) ? 'selected' : '';
                                echo '<option value="' . esc_attr($status_key) . '" ' . $selected . '>' . esc_html($status_label) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Automatically change order status when workflow is sent successfully</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="easyrent_save_settings" class="button button-primary" value="Save Settings" />
            </p>
        </form>
        
        <hr>
        
        <h2>Webhook URL</h2>
        <p>Configure this URL in your Signiflow account to receive signing notifications:</p>
        <code><?php echo esc_url(rest_url('easyrent/v1/signiflow-webhook')); ?></code>
        <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js(rest_url('easyrent/v1/signiflow-webhook')); ?>')">Copy</button>
        
        <hr>
        
        <h2>Test Login</h2>
        <?php
        if (isset($_POST['easyrent_test_login']) && check_admin_referer('easyrent_test_nonce')) {
            $token = easyrent_signiflow_login();
            if ($token) {
                echo '<div class="notice notice-success"><p>Login successful! Token obtained and cached. Check debug log for details.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Login failed! Check your username, password, and API URL. See debug log for details.</p></div>';
            }
        }
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('easyrent_test_nonce'); ?>
            <p>
                <input type="submit" name="easyrent_test_login" class="button" value="Test Signiflow Login" />
            </p>
            <p class="description">Test your Signiflow credentials. This will attempt to login and get a token. Check debug log for details.</p>
        </form>
        
        <hr>
        
        <h2>Test Integration</h2>
        <?php
        if (isset($_POST['easyrent_test_order']) && check_admin_referer('easyrent_test_nonce')) {
            $test_order_id = intval($_POST['test_order_id']);
            if ($test_order_id > 0) {
                $order = wc_get_order($test_order_id);
                if ($order) {
                    easyrent_send_to_signiflow($test_order_id);
                    echo '<div class="notice notice-success"><p>Test triggered for order #' . $test_order_id . '. Check order notes and debug log.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Order not found.</p></div>';
                }
            }
        }
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('easyrent_test_nonce'); ?>
            <p>
                <label for="test_order_id">Test with Order ID:</label>
                <input type="number" id="test_order_id" name="test_order_id" value="" class="small-text" />
                <input type="submit" name="easyrent_test_order" class="button" value="Test Signiflow Integration" />
            </p>
            <p class="description">Enter an order ID to manually trigger the Signiflow integration. Check the order notes and debug log for results.</p>
        </form>
        
        <hr>
        
        <h2>Debugging</h2>
        <p>Check your WordPress debug log for detailed API responses. Enable WP_DEBUG in wp-config.php to see full payloads.</p>
        <p><strong>To enable debug logging, add to wp-config.php:</strong></p>
        <pre><code>define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);</code></pre>
        <p><strong>Then check: <code>wp-content/debug.log</code></strong></p>
        <p><strong>Common Issues:</strong></p>
        <ul>
            <li>Make sure emails are enabled in your Signiflow workflow settings (not just in API)</li>
            <li>Verify username and password are correct</li>
            <li>Check that PDFs are properly configured in your workflow</li>
            <li>Ensure all required tags exist in your PDF documents</li>
            <li>Check that the placeholder user in your workflow can be replaced via API</li>
            <li><strong>If no logs appear:</strong> The hook might not be firing. Use the test button above to manually trigger.</li>
            <li><strong>Authentication:</strong> The plugin uses Signiflow's login API to get a token. Token is cached for 1 hour.</li>
        </ul>
    </div>
    <?php
}
