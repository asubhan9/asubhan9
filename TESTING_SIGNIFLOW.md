# Testing Signiflow Integration - Step by Step

## Problem: No Errors in Logs

If you're not seeing any Signiflow errors in the debug log or order notes, it means **the code might not be running at all**. Here's how to diagnose and fix:

---

## Step 1: Verify Plugin is Active

1. Go to **WordPress Admin → Plugins**
2. Find "EasyRent Signiflow Integration (FullWorkflow)"
3. Make sure it's **Activated** (not just installed)

---

## Step 2: Enable Debug Logging

1. Open `wp-config.php` in your WordPress root directory
2. Find this line: `/* That's all, stop editing! Happy publishing. */`
3. **Before** that line, add:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```
4. Save the file

---

## Step 3: Check Plugin is Loading

After enabling debug logging, refresh any WordPress admin page. Then check `wp-content/debug.log` for:
```
EasyRent Signiflow Integration: Plugin loaded
```

If you see this, the plugin is active. If not, the plugin file might not be in the right location.

---

## Step 4: Test Manually (Easiest Method)

1. Go to **WooCommerce → EasyRent Contracts**
2. Scroll down to **"Test Integration"** section
3. Enter an order ID (any order that was placed)
4. Click **"Test Signiflow Integration"**
5. Check:
   - **Order Notes** (WooCommerce → Orders → [That Order] → Order Notes)
   - **Debug Log** (`wp-content/debug.log`)

You should see:
- `EasyRent Signiflow: Function called for order [ID]`
- Either success message or error message

---

## Step 5: Check Why Hook Might Not Fire

The integration uses these hooks:
- `woocommerce_payment_complete` - Fires when payment completes
- `woocommerce_order_status_processing` - Fires when order moves to "Processing"
- `woocommerce_order_status_completed` - Fires when order moves to "Completed"

**Common reasons hooks don't fire:**
- Payment gateway doesn't trigger `woocommerce_payment_complete`
- Order status never changes to "Processing" or "Completed"
- Order is created but payment never completes

**Solution:** Use the manual test button (Step 4) to bypass hooks and test directly.

---

## Step 6: Verify API Key is Set

1. Go to **WooCommerce → EasyRent Contracts**
2. Check that **Signiflow API Key** field has a value
3. If empty, add your API key and save

---

## Step 7: What to Look For in Debug Log

After testing, search the debug log for:
- `EasyRent Signiflow` - All plugin-related entries
- `Function called for order` - Confirms function executed
- `Response Code:` - HTTP status from API
- `Response Body:` - Full API response
- Any error messages

---

## Step 8: If Still No Logs Appear

If you still don't see any "EasyRent Signiflow" entries in the debug log:

1. **Check file location:**
   - Plugin should be in: `wp-content/plugins/easyrent-signiflow-integration/easyrent-signiflow-integration.php`
   - Or: `wp-content/plugins/easyrent-signiflow-integration.php` (if single file)

2. **Check PHP errors:**
   - Look for PHP fatal errors in debug.log
   - Check if plugin file has syntax errors

3. **Check WordPress error log:**
   - Some hosts use different log locations
   - Check cPanel error logs or server error logs

4. **Verify WooCommerce is active:**
   - The plugin requires WooCommerce
   - Make sure WooCommerce is installed and activated

---

## Quick Diagnostic Checklist

- [ ] Plugin is activated in WordPress
- [ ] WP_DEBUG is enabled in wp-config.php
- [ ] Can see "Plugin loaded" message in debug.log
- [ ] API key is configured in settings
- [ ] Tested manually using test button
- [ ] Checked order notes for messages
- [ ] Checked debug.log for "EasyRent Signiflow" entries

---

## Next Steps After Finding Errors

Once you see errors in the logs:

1. **Copy the error message** from debug.log
2. **Check the API response** - Look for `Response Body:` in logs
3. **Verify Signiflow workflow settings:**
   - Emails enabled in workflow
   - PDFs attached to workflow
   - Placeholder user configured correctly
4. **Contact Signiflow support** if API returns errors
