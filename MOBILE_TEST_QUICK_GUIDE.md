# 📱 Mobile Testing Quick Guide

## 🚀 Quick Start

### Step 1: Connect Your Phone
1. Connect your mobile phone to the **same Wi-Fi** as your development machine
2. Open your mobile browser (Chrome/Safari)
3. Navigate to: **http://192.168.1.10:8000**

### Step 2: Test Credentials
- **Admin Login:** admin@cwtacademy.local
- **Password:** Check the seeder or create a new admin

---

## ✅ 5-Minute Smoke Test

### Homepage (1 min)
- [ ] Page loads without errors
- [ ] No horizontal scrolling
- [ ] Hero section visible
- [ ] Navigation menu works

### Browse Courses (1 min)
- [ ] Tap "Courses" or navigate to /courses
- [ ] Course cards display correctly
- [ ] Tap a course card

### Course Detail (1 min)
- [ ] Course details load
- [ ] Price is visible
- [ ] Tap "Buy/Request Course" button
- [ ] Modal opens (if present) or navigates to form

### Submit Request (2 min)
- [ ] Fill form with test data:
  - Name: Mobile Test User
  - Email: mobile.test@test.local
  - Phone: 07701234567
  - City: Erbil
- [ ] Tap file upload
- [ ] Select an image from your phone
- [ ] Submit form
- [ ] **SAVE THE TRACKING CODE** displayed

### Track Request (30 sec)
- [ ] Navigate to /track
- [ ] Enter your tracking code
- [ ] Verify status shows "Pending"
- [ ] ❌ Telegram link should NOT be visible yet

---

## 🔍 Full Test (30 minutes)

### 1. Navigation & Layout (5 min)
Test every page for:
- No horizontal scroll
- Readable text without zoom
- Buttons are touch-friendly
- Images load correctly

### 2. Course Request Flow (10 min)
1. Browse courses
2. View course detail
3. Submit course request
4. Upload payment proof from phone camera
5. Get tracking code
6. Track request

### 3. Admin Approval (5 min)
1. Open **/admin** on desktop browser
2. Login as admin
3. Find your test request
4. Approve payment proof
5. Approve course request

### 4. Telegram Link Verification (5 min)
1. Return to mobile browser
2. Go to /track with your tracking code
3. ✅ Telegram link should NOW be visible
4. Tap the Telegram link
5. Verify it opens Telegram app or t.me web
6. Try to join the channel (will be test channel)

### 5. Student Dashboard (5 min)
1. Register/login as student on mobile
2. Navigate to /student/dashboard
3. Verify approved course shows
4. Verify "Open Telegram" button works
5. Verify tracking buttons work

---

## 🐛 Common Issues to Look For

### Layout Issues
- [ ] Text overflows container
- [ ] Buttons too small to tap
- [ ] Form inputs hidden or cut off
- [ ] Modal too wide for screen
- [ ] Sticky elements cover content

### Functionality Issues
- [ ] File upload doesn't open camera option
- [ ] Buttons don't respond to tap
- [ ] Form validation doesn't show errors
- [ ] Tracking code copy doesn't work
- [ ] Telegram link doesn't open app

### Performance Issues
- [ ] Page load > 5 seconds
- [ ] Images load slowly
- [ ] 3D model causes browser lag
- [ ] Form submission times out

---

## 📸 Screenshot Guide

Take screenshots of:
1. Homepage on mobile
2. Course catalog grid
3. Course detail page
4. Request form (before submit)
5. Success page with tracking code
6. Tracking page (before approval)
7. Tracking page (after approval) with Telegram link
8. Student dashboard

---

## 🔧 Troubleshooting

### Can't Connect from Phone
```bash
# On development machine, verify server is running:
ps aux | grep "php artisan serve"

# Verify IP address:
hostname -I

# Check firewall (if needed):
sudo ufw status
sudo ufw allow 8000/tcp
```

### Page Not Loading
- Check if you're on the same Wi-Fi network
- Try http://192.168.1.10:8000 (replace with your actual LAN IP)
- Verify server is running on 0.0.0.0:8000 (not 127.0.0.1)

### File Upload Not Working
- Check file size (< 5MB recommended)
- Check file type (jpg, png, pdf only)
- Check PHP upload limits in php.ini

### Telegram Link Doesn't Work
- Verify course has a Telegram channel configured
- Check if request status is "APPROVED"
- Check if Telegram access grant exists
- Verify URL format is correct (t.me/+...)

---

## ✅ Pass/Fail Criteria

### PASS Requirements
- ✅ All pages load without errors
- ✅ No horizontal scrolling on any page
- ✅ Form submission works from mobile
- ✅ File upload from phone works
- ✅ Tracking page shows correct status
- ✅ Telegram link visible only after approval
- ✅ Telegram link opens app or web
- ✅ All touch targets are at least 44x44px
- ✅ Text readable without zooming

### FAIL Indicators
- ❌ Horizontal scrolling present
- ❌ Buttons too small to tap reliably
- ❌ File upload doesn't open camera
- ❌ Form doesn't submit or times out
- ❌ Telegram link visible before approval
- ❌ Telegram link doesn't open Telegram
- ❌ JavaScript errors in console
- ❌ Page load > 10 seconds

---

## 📊 Test Results Template

Copy and fill this after testing:

```
=== MOBILE TEST RESULTS ===
Date: ___________
Tester: ___________
Device: ___________ (e.g., iPhone 14 Pro, Samsung S23)
OS: ___________ (e.g., iOS 17, Android 13)
Browser: ___________ (e.g., Safari, Chrome)
Network: ___________ (e.g., Wi-Fi, 4G)

SMOKE TEST: [ ] PASS [ ] FAIL
FULL TEST: [ ] PASS [ ] FAIL

Issues Found:
1. ___________
2. ___________
3. ___________

Screenshots: [ ] Attached [ ] Not taken

Notes:
___________
___________
___________
```

---

## 🎯 Priority Test Areas

### CRITICAL (Must Test)
1. Course request submission
2. File upload from mobile
3. Tracking page functionality
4. Telegram link visibility logic
5. Telegram deep link functionality

### HIGH (Should Test)
1. Navigation menu
2. Course catalog browsing
3. Form validation
4. Student dashboard
5. Copy-to-clipboard features

### MEDIUM (Nice to Test)
1. 3D hero animation performance
2. Modal interactions
3. Password visibility toggle
4. Language switching (if RTL)
5. Admin panel on mobile

---

**Ready to Test?** Start with the 5-minute smoke test, then proceed to full test if all passes!

**Questions?** Check the full report: `E2E_MOBILE_TEST_REPORT.md`
