# How to Add Tags to PDFs for Signiflow Auto-Tag Population

## Overview

Signiflow's `UseAutoTagsField` feature automatically populates PDF fields when tag names in your PDF match the tag names in your `TagValuesField`. This guide explains how to add these tags to your PDF documents.

## Tag Names Used in Your Plugin

Based on your plugin code, here are the tag names that will be populated:

1. **`renter_legal_name`** - Company name or customer name
2. **`abn`** - Australian Business Number
3. **`contact_name`** - Customer full name
4. **`email`** - Customer email address
5. **`phone`** - Customer phone number
6. **`installation_address`** - Installation street address
7. **`installation_state`** - Installation state (NSW, VIC, QLD, etc.)
8. **`installation_postcode`** - Installation postcode
9. **`equipment_description`** - Product/equipment name
10. **`quantity`** - Quantity of items
11. **`rental_term`** - Rental term (currently empty)
12. **`monthly_rent`** - Monthly rental amount
13. **`gst_amount`** - GST amount
14. **`total_monthly_payment`** - Total monthly payment
15. **`terms_accepted`** - Terms acceptance (currently "Yes")
16. **`order_id`** - WooCommerce order ID

## Methods to Add Tags to PDFs

### Method 1: Using Adobe Acrobat (Recommended)

1. **Open your PDF in Adobe Acrobat Pro** (not Reader - you need Pro for form editing)

2. **Prepare the Form:**
   - Go to **Tools** → **Prepare Form**
   - If prompted, select "Use an existing file" and choose your PDF

3. **Add Text Fields:**
   - Click **Add a Text Field** in the toolbar
   - Click where you want the field to appear in your PDF
   - A field will be created

4. **Name the Field (This is the Tag Name):**
   - Right-click the field → **Properties**
   - In the **General** tab, set the **Name** field to match one of your tag names (e.g., `renter_legal_name`, `abn`, `email`)
   - **Important:** The name must match EXACTLY (case-sensitive, no spaces unless in the tag name)

5. **Set Field Properties:**
   - **Appearance:** Set font, size, alignment as needed
   - **Options:** Set default value, format, etc.
   - **Actions:** (Optional) Add any field actions

6. **Repeat for All Fields:**
   - Add a text field for each tag you want to populate
   - Name each field with the exact tag name from the list above

7. **Save the PDF:**
   - Save your PDF template
   - The form fields (tags) are now embedded in the PDF

### Method 2: Using PDF Form Tools (Alternative)

**Tools that support PDF form creation:**
- **PDFtk** (command-line tool)
- **LibreOffice Draw** (can create PDF forms)
- **Foxit PDF Editor**
- **Nitro PDF**

**General Steps:**
1. Open your PDF in the form editor
2. Add text form fields
3. Set the field name to match your tag names exactly
4. Save the PDF

### Method 3: Using Signiflow Dashboard (If Available)

Some Signiflow instances allow you to add tags directly in their dashboard:

1. Upload your PDF to Signiflow
2. Use Signiflow's document editor to add form fields
3. Name the fields with your tag names
4. Save the workflow template

## Tag Naming Rules

⚠️ **Critical Requirements:**

1. **Exact Match:** Tag names in PDF must match exactly (case-sensitive)
   - ✅ Correct: `renter_legal_name`
   - ❌ Wrong: `Renter_Legal_Name`, `renter legal name`, `renterLegalName`

2. **No Spaces:** Use underscores, not spaces
   - ✅ Correct: `installation_address`
   - ❌ Wrong: `installation address`

3. **Case Sensitive:** Match the exact case
   - ✅ Correct: `email`
   - ❌ Wrong: `Email`, `EMAIL`

4. **Special Characters:** Only use letters, numbers, and underscores
   - ✅ Correct: `order_id`
   - ❌ Wrong: `order-id`, `order.id`

## Example: Adding Tags to Your PDF

Here's a step-by-step example for adding the `renter_legal_name` tag:

1. Open PDF in Adobe Acrobat Pro
2. Tools → Prepare Form
3. Click "Add a Text Field"
4. Click where "Company Name" should appear
5. Right-click field → Properties
6. **Name:** `renter_legal_name` (exact match)
7. **Tooltip:** "Renter Legal Name" (optional, for your reference)
8. Set appearance (font, size, etc.)
9. Click OK
10. Save PDF

Repeat for all other fields you need.

## Verifying Tags in Your PDF

### Using Adobe Acrobat:
1. Open PDF
2. Go to **Forms** → **Edit Form** (or Tools → Prepare Form)
3. You should see all form fields listed
4. Check that field names match your tag names exactly

### Using PDFtk (Command Line):
```bash
pdftk your_template.pdf dump_data_fields
```
This will list all form field names in your PDF.

### Using Online Tools:
- Upload PDF to a PDF form viewer
- Check field names match your tag names

## Common Issues and Solutions

### Issue 1: Tags Not Populating
**Cause:** Tag name mismatch
**Solution:** 
- Verify exact spelling and case
- Check for extra spaces or special characters
- Use underscores, not spaces or hyphens

### Issue 2: Some Tags Work, Others Don't
**Cause:** Only some tag names match
**Solution:**
- Review the exact tag names in your plugin code
- Check each field name in your PDF matches exactly

### Issue 3: Tags Appear But Are Empty
**Cause:** Tag name exists but value isn't being sent
**Solution:**
- Check that the tag name exists in your `TagValuesField` in the plugin
- Verify the order data is being collected correctly

## Testing Your Tags

1. **Create a test PDF** with a few tags (e.g., `email`, `contact_name`, `order_id`)
2. **Upload to Signiflow** or use direct PDF upload in plugin settings
3. **Place a test order** in WooCommerce
4. **Check the debug log** to see if tags are being populated
5. **Review the signed document** in Signiflow to verify values appear

## Quick Reference: All Tag Names

Copy-paste these exact names when creating your PDF form fields:

```
renter_legal_name
abn
contact_name
email
phone
installation_address
installation_state
installation_postcode
equipment_description
quantity
rental_term
monthly_rent
gst_amount
total_monthly_payment
terms_accepted
order_id
```

## Next Steps

1. **Edit your PDF templates** using one of the methods above
2. **Add form fields** with the exact tag names
3. **Save your PDF templates**
4. **Upload to WordPress** (Media Library) or configure file paths in plugin settings
5. **Test with a sample order**

## Need Help?

If tags still aren't working:
1. Check WordPress debug log for tag-related errors
2. Verify PDF form fields are properly created (not just text)
3. Test with a simple tag first (e.g., `order_id`)
4. Contact Signiflow support if tags are created correctly but not populating
