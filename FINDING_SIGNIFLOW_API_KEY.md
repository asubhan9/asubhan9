# How to Find Your Signiflow API Key

## Method 1: Signiflow Dashboard (Most Common)

1. **Log into your Signiflow account**
   - Go to: `https://sign.docs2me.com.au` (your Signiflow instance)
   - Or: `https://www.signiflow.com` (if using main portal)

2. **Navigate to Settings/Administration**
   - Look for: **Settings**, **Administration**, **API Settings**, or **Developer Settings**
   - Common locations:
     - Top menu: Settings → API
     - User menu (top right) → Settings → API Keys
     - Administration → API Configuration

3. **Find API Keys/Tokens Section**
   - Look for: **API Keys**, **API Tokens**, **Bearer Tokens**, or **Authentication Tokens**
   - You may see:
     - A list of existing API keys
     - A button to "Generate New API Key"
     - A "Create Token" option

4. **Copy the API Key**
   - Copy the full token (it's usually a long string)
   - Make sure to copy the entire key without spaces

## Method 2: Signiflow Developers Portal

1. **Visit Signiflow Developers Zone**
   - Go to: `https://www.signiflow.com/developers`

2. **Log in with your Signiflow credentials**

3. **Access API Documentation**
   - Look for "Open API" or "API Documentation"
   - Check for "Authentication" or "Getting Started" section

4. **Find Token Generation**
   - The documentation should explain how to generate tokens
   - There may be a direct link to generate tokens

## Method 3: Contact Signiflow Support

If you can't find the API key section:

1. **Contact Signiflow Support**
   - Email: Check your Signiflow account for support contact
   - Phone: Usually available in your account dashboard
   - Support Portal: Look for "Support" or "Help" in your Signiflow dashboard

2. **Ask them:**
   - "Where can I find or generate my API key for FullWorkflow integration?"
   - "I need a Bearer token for API authentication"
   - "How do I access API settings in my account?"

## Method 4: Check Your Signiflow Account Type

Different account types may have different locations:

- **Enterprise Accounts**: Usually in Administration → API Settings
- **Standard Accounts**: May be in Settings → Integrations
- **Developer Accounts**: Usually in Developer Portal

## What the API Key Should Look Like

- Usually a long alphanumeric string
- May contain letters, numbers, and special characters
- Typically 32-64 characters long
- Example format: `a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6` (but yours will be different)

## Important Notes

1. **API Key vs Token**: Signiflow may use "API Key", "Token", "Bearer Token", or "Authentication Token" - they're all the same thing

2. **Permissions**: Make sure your API key has permissions for:
   - FullWorkflow API
   - Document creation
   - Workflow management

3. **Security**: 
   - Never share your API key publicly
   - If exposed, regenerate it immediately
   - Store it securely in WordPress settings (password field)

## After You Find It

1. Go to **WordPress Admin → WooCommerce → EasyRent Contracts**
2. Paste the API key in the **"Signiflow API Key"** field
3. Click **"Save Settings"**
4. Test using the **"Test Integration"** button

## Still Can't Find It?

If you're still having trouble:

1. **Check your Signiflow welcome email** - Sometimes API keys are provided in initial setup emails

2. **Check with your Signiflow account administrator** - If you're not the account owner, they may need to provide access

3. **Look for "Integration" or "Webhook" settings** - API keys are sometimes in integration settings

4. **Check the Signiflow help documentation** - Your instance may have specific documentation at `https://sign.docs2me.com.au/help` or similar

## Quick Checklist

- [ ] Logged into Signiflow dashboard
- [ ] Checked Settings/Administration menu
- [ ] Looked for API/Developer section
- [ ] Found API Keys/Tokens section
- [ ] Copied the full API key
- [ ] Pasted into WordPress plugin settings
- [ ] Saved settings
- [ ] Tested integration
