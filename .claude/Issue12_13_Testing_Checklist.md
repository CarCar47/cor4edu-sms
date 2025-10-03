# Testing Checklist: Issue #12 & #13 Fixes

**Date**: 2025-10-02
**Issues Fixed**:
- Issue #12: Academic dates missing columns (anticipatedGraduationDate, actualGraduationDate, withdrawnDate, lastDayOfAttendance)
- Issue #13: Error banner doesn't auto-dismiss after 5 seconds

---

## Test Issue #12: Academic Info Edit (Database Fix)

### Prerequisites
- ✅ Migration applied: `update_academic_dates_fixed.sql`
- ✅ 4 columns added to `cor4edu_students` table

### Test Steps

1. **Navigate to Student Profile**
   - Go to: https://sms-edu-938209083489.us-central1.run.app
   - Login as superadmin
   - Navigate to Students → Manage Students
   - Click "View" on any student

2. **Go to Registrar Tab**
   - Click "Registrar" tab
   - Should see academic information section

3. **Click "Edit Academic Info"**
   - Click the "Edit Academic Info" button
   - Modal/form should open

4. **Enter Date Information**
   - Enter or verify "Start Date" (Enrollment Date)
   - Enter "Anticipated Graduation Date" (e.g., 2026-05-15)
   - Leave other dates empty for now
   - Select or verify Academic Status

5. **Save Changes**
   - Click "Save" button
   - **Expected**: ✅ Green success banner "Academic information updated successfully"
   - **Expected**: Banner auto-disappears after 5 seconds
   - **Not Expected**: ❌ Red error banner "Unknown column 'anticipatedGraduationDate'"

6. **Verify Data Saved**
   - Refresh page
   - Go back to Registrar tab
   - Anticipated graduation date should be displayed correctly

### Success Criteria
- [ ] No database column errors
- [ ] Success banner appears
- [ ] Success banner auto-disappears after 5 seconds
- [ ] Anticipated graduation date saves correctly

---

## Test Issue #13: Error Banner Auto-Dismiss (UI Fix)

### Prerequisites
- ✅ Template updated: `resources/templates/students/view.twig.html`
- ✅ Added `id="errorBanner"`, close button, hideErrorBanner() function
- ✅ Added auto-dismiss setTimeout(5000)

### Test Steps

1. **Trigger a Validation Error**
   - Go to student profile → Registrar tab
   - Click "Edit Academic Info"
   - Enter an INVALID date (e.g., start date AFTER anticipated graduation date)
   - Click Save

2. **Observe Error Banner**
   - **Expected**: Red error banner appears at top
   - **Expected**: Close X button visible in top-right corner of banner
   - **Expected**: Error message clearly visible

3. **Test Auto-Dismiss**
   - Wait 5 seconds without clicking anything
   - **Expected**: ✅ Error banner automatically disappears after 5 seconds
   - **Not Expected**: ❌ Banner stays visible forever

4. **Test Manual Close (Optional Second Test)**
   - Trigger error again (repeat step 1)
   - **Immediately** click the X button in top-right of error banner
   - **Expected**: Banner disappears immediately

### Success Criteria
- [ ] Error banner appears with error message
- [ ] Close X button is visible and clickable
- [ ] Banner auto-disappears after 5 seconds
- [ ] Manual close button works immediately
- [ ] Banner behavior matches success banner (green) behavior

---

## Combined Test: Both Fixes Working Together

1. Navigate to Registrar tab
2. Click "Edit Academic Info"
3. Enter valid dates
4. Click Save
5. **Expected**: Green success banner appears and auto-disappears in 5s
6. Click "Edit Academic Info" again
7. Enter invalid dates (e.g., graduation before start)
8. Click Save
9. **Expected**: Red error banner appears and auto-disappears in 5s

### Success Criteria
- [ ] Both success and error banners behave consistently
- [ ] Both auto-dismiss after 5 seconds
- [ ] Both have close X buttons
- [ ] No database column errors occur

---

## What to Report

**If tests pass** ✅:
- Confirm: "Issue #12 and #13 fixed - academic info edit working, error banners auto-dismiss"

**If Issue #12 still broken** ❌:
- Report exact error message
- Check Cloud Run logs: `gcloud run services logs read sms-edu --project=sms-edu-47 --region=us-central1 --limit=20`
- Possible issue: Migration didn't apply correctly

**If Issue #13 still broken** ❌:
- Report: Banner does/doesn't appear
- Report: Banner does/doesn't auto-dismiss
- Check browser console for JavaScript errors (F12 → Console tab)
- Possible issue: Template didn't redeploy to Cloud Run

---

## Deployment Note

**Issue #12 (Database)**: ✅ Already fixed in Cloud SQL - no deployment needed

**Issue #13 (Template)**: ⚠️ Requires Cloud Run deployment
- Template file changed: `resources/templates/students/view.twig.html`
- **Need to deploy**: `gcloud builds submit --config cloudbuild.yaml` (if you want the error banner fix live)
- **OR** test locally first with local development environment

---

##Next Steps After Testing

1. If both tests pass → Document issues in deployment log
2. If template fix works locally but not in Cloud → Deploy to Cloud Run
3. If any errors occur → Report back with details
4. Move forward with Phase 2-6 of Correction Plan
