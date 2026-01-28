# Signiflow Direct PDF Upload Approach

## Overview

Instead of using a pre-existing workflow template (`WorkflowIDField`), we can upload PDFs directly via the API using `DocField` (base64 encoded). This approach bypasses workflow template issues.

## ✅ API Support Confirmed

According to Signiflow API documentation:
- **`DocField`**: Accepts "Base64 Encoded document"
- **`UseAutoTagsField`**: Works with directly uploaded PDFs
- **`TagValuesField`**: Populates tags in uploaded PDFs

## 📋 Implementation Steps

### Step 1: Store PDF Templates in WordPress

**Option A: WordPress Media Library**
- Upload your 2 PDF templates to WordPress Media Library
- Get their attachment IDs or file paths

**Option B: File System**
- Store PDFs in a plugin directory (e.g., `/wp-content/plugins/easyrent-signiflow-integration/templates/`)
- Reference by file path

**Option C: Settings Page**
- Add file upload fields in plugin settings
- Store file paths in WordPress options

### Step 2: Merge Multiple PDFs (If Needed)

**Challenge**: `DocField` appears to accept only ONE base64-encoded PDF.

**Solutions**:
1. **Merge PDFs into one file** (Recommended)
   - Use a PHP library like `setasign/fpdi` or `smalot/pdfparser`
   - Merge both PDFs before encoding
   - Upload as single document

2. **Send multiple requests** (Not ideal)
   - Send first PDF, get DocID
   - Send second PDF, link to first
   - More complex, may not work as expected

3. **Check API for multi-document support**
   - Some APIs support document arrays
   - Need to verify Signiflow's exact structure

### Step 3: Base64 Encode PDFs

```php
// Read PDF file
$pdf_path = '/path/to/your/template.pdf';
$pdf_content = file_get_contents($pdf_path);
$pdf_base64 = base64_encode($pdf_content);
```

### Step 4: Modify Payload Structure

**Current (Workflow Template):**
```json
{
  "WorkflowIDField": 2301,
  "DocField": "",
  "TagValuesField": {...}
}
```

**New (Direct Upload):**
```json
{
  "DocField": "base64_encoded_pdf_content_here",
  "DocNameField": "EasyRent Agreement - Order #4267",
  "TagValuesField": {...},
  "UseAutoTagsField": 1
}
```

### Step 5: Update Plugin Code

**Changes needed:**
1. Remove `WorkflowIDField` requirement
2. Add PDF file path/upload settings
3. Add PDF reading and base64 encoding
4. Add PDF merging (if 2 PDFs)
5. Update payload to use `DocField` instead of `WorkflowIDField`

## 🔧 Technical Requirements

### PHP Libraries Needed

**For PDF Merging:**
```bash
composer require setasign/fpdi
# OR
composer require smalot/pdfparser
```

**Alternative (WordPress Native):**
- Use WordPress file handling
- May need custom PDF merging function

### File Size Considerations

- Base64 encoding increases file size by ~33%
- Large PDFs may hit API size limits
- May need to compress PDFs first

## 📝 Implementation Plan

### Phase 1: Settings & File Management
1. Add PDF template upload fields to settings page
2. Store PDF file paths in WordPress options
3. Add file validation (PDF format, size limits)

### Phase 2: PDF Processing
1. Read PDF files from stored paths
2. Merge 2 PDFs into one (if needed)
3. Base64 encode the merged PDF

### Phase 3: API Integration
1. Modify payload to use `DocField` instead of `WorkflowIDField`
2. Remove workflow ID requirement
3. Keep `TagValuesField` and `UseAutoTagsField`
4. Keep `WorkflowUsersListField`

### Phase 4: Testing
1. Test with single PDF first
2. Test with merged PDFs
3. Verify tag population works
4. Verify emails are sent

## ⚠️ Potential Challenges

1. **PDF Merging Complexity**
   - Need reliable PHP library
   - May lose some PDF features
   - File size increases

2. **File Storage**
   - Where to store templates securely
   - How to update templates
   - Version control

3. **Performance**
   - Base64 encoding is CPU intensive
   - Large files may timeout
   - API request size limits

4. **Tag Matching**
   - Tags must match exactly in PDF
   - Case-sensitive
   - Must exist in PDF before upload

## 🎯 Recommended Approach

**Best Option: Merge PDFs + Direct Upload**

1. Store 2 PDF templates in WordPress
2. Merge them into one PDF per order
3. Base64 encode merged PDF
4. Send via `DocField`
5. Use `TagValuesField` to populate tags

**Advantages:**
- ✅ Bypasses workflow template issues
- ✅ Full control over documents
- ✅ No dependency on Signiflow workflow setup
- ✅ Works with any PDF structure

**Disadvantages:**
- ❌ Requires PDF merging library
- ❌ More complex code
- ❌ Larger API payloads

## 📊 Comparison: Workflow Template vs Direct Upload

| Feature | Workflow Template | Direct Upload |
|---------|------------------|--------------|
| Setup Complexity | High (Signiflow dashboard) | Medium (Code) |
| PDF Management | In Signiflow | In WordPress |
| Flexibility | Limited | High |
| Performance | Fast | Slower (encoding) |
| Maintenance | Signiflow dashboard | WordPress settings |
| Current Issue | "Valid document" error | Not tested yet |

## 🚀 Next Steps

1. **Decide on approach:**
   - Try to fix workflow template issue first? (Contact Signiflow support)
   - OR implement direct PDF upload?

2. **If direct upload:**
   - Choose PDF storage method
   - Choose PDF merging library
   - Implement step by step

3. **Test thoroughly:**
   - Single PDF first
   - Merged PDFs
   - Tag population
   - Email delivery

## ❓ Questions to Answer

1. **Where are your 2 PDF templates currently stored?**
   - Signiflow dashboard only?
   - Do you have local copies?

2. **Do you want to merge PDFs or send separately?**
   - Merged = one document
   - Separate = two documents (may need different approach)

3. **File size of PDFs?**
   - Small (< 1MB) = easy
   - Large (> 5MB) = may need compression

4. **Can you install PHP libraries?**
   - Composer available?
   - Or prefer WordPress-native solution?

## 📞 Recommendation

**Before implementing direct upload, try:**
1. Contact Signiflow support about workflow 2301
2. Ask why "Failed - Please provide a valid document"
3. Verify workflow is published/active
4. Check if documents are properly linked

**If that doesn't work, then:**
- Implement direct PDF upload approach
- More control, less dependency on Signiflow workflow setup
