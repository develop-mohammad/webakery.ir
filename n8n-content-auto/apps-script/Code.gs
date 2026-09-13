/**
 * وب‌اپ گوگل برای زدن تیک و تاریخ شمسی از بیرون سایت.
 * در Apps Script: Deploy → Web app → Anyone with link
 */
function doPost(e) {
  var body = {};
  try {
    body = JSON.parse((e && e.postData && e.postData.contents) || '{}');
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ ok: false, error: 'json' }))
      .setMimeType(ContentService.MimeType.JSON);
  }
  var secret = PropertiesService.getScriptProperties().getProperty('WBCA_SECRET') || '';
  if (secret && body.secret !== secret) {
    return ContentService.createTextOutput(JSON.stringify({ ok: false, error: 'secret' }))
      .setMimeType(ContentService.MimeType.JSON);
  }
  var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(
    PropertiesService.getScriptProperties().getProperty('WBCA_TAB') || 'Sheet1'
  );
  var row = parseInt(body.row, 10);
  if (!row) {
    return ContentService.createTextOutput(JSON.stringify({ ok: false, error: 'row' }))
      .setMimeType(ContentService.MimeType.JSON);
  }
  var doneCol = parseInt(PropertiesService.getScriptProperties().getProperty('COL_DONE') || '6', 10);
  var dateCol = parseInt(PropertiesService.getScriptProperties().getProperty('COL_DATE') || '7', 10);
  var noteCol = parseInt(PropertiesService.getScriptProperties().getProperty('COL_NOTE') || '8', 10);
  sheet.getRange(row, doneCol).setValue(true);
  sheet.getRange(row, dateCol).setValue(body.date || '');
  sheet.getRange(row, noteCol).setValue(body.note || 'منتشر شد');
  return ContentService.createTextOutput(JSON.stringify({ ok: true, row: row }))
    .setMimeType(ContentService.MimeType.JSON);
}
