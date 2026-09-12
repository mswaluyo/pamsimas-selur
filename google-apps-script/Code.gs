/**
 * PAMSIMAS - Google Apps Script IoT Data Collector
 * ==================================================
 * Terima data dari ESP8266/ESP32 dan simpan ke Google Sheets
 * 
 * Cara Deploy:
 * 1. Buka https://script.google.com
 * 2. New Project → Paste kode ini
 * 3. Deploy > New Deployment > Web App
 * 4. Execute as: Me
 * 5. Who has access: Anyone
 * 6. Deploy → Copy URL
 * 7. Gunakan URL di ESP8266/ESP32
 */

const SPREADSHEET_ID = 'BUAT_SPREADSHEET_BARU_DAN_COPY_ID_DI_SINI';
const SHEET_NAME = 'sensor_data';
const API_KEY = 'pamsimas-secret-key-123';

function doPost(e) {
  try {
    const apiKey = e.parameter.api_key || (e.postData && JSON.parse(e.postData.contents).api_key);
    if (apiKey !== API_KEY) {
      return jsonResponse({ status: 'error', message: 'Invalid API Key' });
    }
    
    let data;
    if (e.postData && e.postData.contents) {
      data = JSON.parse(e.postData.contents);
    } else {
      data = e.parameter;
    }
    
    if (!data.device_id) {
      return jsonResponse({ status: 'error', message: 'device_id is required' });
    }
    
    const result = saveToSheet(data);
    
    return jsonResponse({
      status: 'success',
      message: 'Data saved',
      row: result.row,
      timestamp: new Date().toISOString()
    });
    
  } catch (error) {
    return jsonResponse({ status: 'error', message: error.toString() });
  }
}

function doGet(e) {
  const action = e.parameter.action || 'status';
  
  switch (action) {
    case 'status':
      return jsonResponse({
        status: 'ok',
        service: 'PAMSIMAS IoT Collector',
        timestamp: new Date().toISOString()
      });
    case 'latest':
      return getLatestData(e.parameter.device_id);
    default:
      return jsonResponse({ status: 'error', message: 'Unknown action' });
  }
}

function saveToSheet(data) {
  const ss = SpreadsheetApp.openById(SPREADSHEET_ID);
  let sheet = ss.getSheetByName(SHEET_NAME);
  
  if (!sheet) {
    sheet = ss.insertSheet(SHEET_NAME);
    sheet.appendRow(['Timestamp', 'Device ID', 'Sensor Type', 'Value', 'Unit', 'Battery', 'Signal']);
    sheet.getRange(1, 1, 1, 7).setFontWeight('bold').setBackground('#4285f4').setFontColor('white');
    sheet.setFrozenRows(1);
  }
  
  const row = [
    new Date(),
    data.device_id || 'unknown',
    data.sensor_type || 'generic',
    data.value || 0,
    data.unit || '',
    data.battery || '',
    data.signal || ''
  ];
  
  sheet.appendRow(row);
  return { row: sheet.getLastRow() };
}

function getLatestData(deviceId) {
  const ss = SpreadsheetApp.openById(SPREADSHEET_ID);
  const sheet = ss.getSheetByName(SHEET_NAME);
  
  if (!sheet) {
    return jsonResponse({ status: 'error', message: 'No data yet' });
  }
  
  const data = sheet.getDataRange().getValues();
  const headers = data[0];
  const rows = data.slice(1).slice(-50).reverse();
  
  const result = rows.map(row => {
    const obj = {};
    headers.forEach((header, i) => { obj[header] = row[i]; });
    return obj;
  });
  
  return jsonResponse({ status: 'ok', count: result.length, data: result });
}

function jsonResponse(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
