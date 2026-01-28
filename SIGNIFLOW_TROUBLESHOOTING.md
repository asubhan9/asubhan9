# Signiflow Integration Troubleshooting Guide

## Issue: Emails Not Being Sent

### Most Common Causes:

1. **Workflow Email Settings in Signiflow Dashboard**
   - Even though `SendWorkflowEmailsField` is set to `true` in the API, emails must also be enabled in the Signiflow workflow settings
   - Go to your Signiflow dashboard → Workflows → Your Workflow → Settings
   - Ensure "Send Email Notifications" is enabled
   - Check email templates are configured

2. **Missing PDF Documents**
   - The FullWorkflow API requires PDF documents to be attached
   - If PDFs are pre-uploaded in a workflow, you need to provide the `WorkflowIDField` in the API call
   - If creating new workflows, PDFs must be uploaded via the API or attached to the workflow

3. **Placeholder User Replacement**
   - If your workflow has a placeholder user, ensure:
     - The placeholder email matches what you're replacing
     - The API has permission to modify workflow users
     - The user structure matches exactly (ActionField, SignatureTypeField, etc.)

4. **API Response Issues**
   - Check WordPress debug log: `wp-content/debug.log`
   - Look for "EasyRent Signiflow Response" entries
   - Verify the API returns `ResultField: 1` for success
   - Check for error messages in `ResultFieldMessage`

### How to Debug:

1. **Enable WordPress Debugging**
   Add to `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. **Check Order Notes**
   - Go to WooCommerce → Orders
   - Open the order that should have triggered Signiflow
   - Check the order notes for Signiflow status messages

3. **Check API Response**
   - Look in debug.log for the full API response
   - Verify HTTP status code is 200-299
   - Check if `ResultField` is 1 (success) or 0 (failure)

4. **Test API Directly**
   Use curl or Postman to test the API:
   ```bash
   curl -X POST https://sign.docs2me.com.au/api/SignFlowAPIServiceRest.svc/FullWorkflow \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_API_KEY" \
     -d @test-payload.json
   ```

### Plugin Improvements in v2.1.0:

✅ Enhanced error handling with detailed logging  
✅ Response validation and status checking  
✅ Order notes for tracking Signiflow status  
✅ Settings for Workflow ID (for pre-uploaded PDFs)  
✅ Webhook handler for signing callbacks  
✅ Better debugging information in settings page  

### Next Steps:

1. **Verify Signiflow Workflow Configuration**
   - Log into Signiflow dashboard
   - Check workflow email settings
   - Verify PDFs are attached
   - Confirm placeholder user can be replaced

2. **Check API Key Permissions**
   - Ensure API key has FullWorkflow permissions
   - Verify API key is active and not expired

3. **Test with Debug Mode**
   - Enable WP_DEBUG
   - Place a test order
   - Check debug.log for full API request/response

4. **Contact Signiflow Support**
   - If API returns success but emails still don't send
   - Provide them with:
     - API response from debug log
     - Workflow ID
     - Document ID (if available)
     - Customer email address

### Common API Response Codes:

- `ResultField: 1` = Success
- `ResultField: 0` = Failure (check `ResultFieldMessage`)
- HTTP 200-299 = Request successful
- HTTP 400 = Bad request (check payload structure)
- HTTP 401 = Unauthorized (check API key)
- HTTP 500 = Server error (contact Signiflow support)
