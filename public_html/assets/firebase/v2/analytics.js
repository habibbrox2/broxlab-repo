// v2/analytics.js - Modular Analytics wrapper for Firebase v9+ style
// Uses centralized debug control from debug.js
// Provides: logEvent, setUserId, setUserProperties and various tracking helpers.

import initFirebase, { getAnalyticsInstance } from './init.js';
import { DebugUtils } from './debug.js';
import { logEvent as fbLogEvent, setUserId as fbSetUserId, setUserProperties as fbSetUserProperties } from 'firebase/analytics';

async function _getAnalytics() {
  await initFirebase();
  return getAnalyticsInstance();
}

export async function logEvent(name, params = {}) {
  const analytics = await _getAnalytics();
  if (!analytics) return null;
  try {
    DebugUtils.moduleLog('analytics', `Event: ${name}`);
    return fbLogEvent(analytics, name, params);
  } catch (e) {
    DebugUtils.moduleError('analytics', 'Event logging failed');
  }
}

export async function setUserId(uid) {
  const analytics = await _getAnalytics();
  if (!analytics) return null;
  try {
    DebugUtils.moduleLog('analytics', 'User ID set');
    return fbSetUserId(analytics, uid);
  } catch (e) {
    DebugUtils.moduleError('analytics', 'Failed to set user ID');
  }
}

export async function setUserProperties(props = {}) {
  const analytics = await _getAnalytics();
  if (!analytics) return null;
  try {
    DebugUtils.moduleLog('analytics', 'User properties updated');
    return fbSetUserProperties(analytics, props);
  } catch (e) {
    DebugUtils.moduleError('analytics', 'Failed to set user properties');
  }
}

// Reuse earlier tracking helpers
export async function trackUserLogin(userId) { return logEvent('login', { user_id: userId }); }
export async function trackUserLogout(userId) { return logEvent('user_logout', { user_id: userId, timestamp: new Date().toISOString() }); }
export async function trackTokenGenerated(tokenData = {}) { return logEvent('fcm_token_generated', { token_length: tokenData.token ? tokenData.token.length : 0, user_id: tokenData.user_id || 'guest' }); }

// Backwards-compatible aliases
export const trackUserLoginEvent = trackUserLogin;
export const trackUserLogoutEvent = trackUserLogout;

// Admin notification tracking helpers (merged from analytics-wrapper.js)
export async function trackAdminNotificationSend(notificationData, recipientCount = 0) {
  try {
    await logEvent('admin_notification_send', { payload: notificationData, recipient_count: recipientCount });
  } catch (e) { DebugUtils?.warn('trackAdminNotificationSend failed', e); }
}

export async function trackAdminNotificationResend(notificationId, recipientCount = 0) {
  try {
    await logEvent('admin_notification_resend', { notification_id: notificationId, recipient_count: recipientCount });
  } catch (e) { DebugUtils?.warn('trackAdminNotificationResend failed', e); }
}

export default { logEvent, setUserId, setUserProperties, trackUserLogin, trackUserLogout, trackTokenGenerated, trackAdminNotificationSend, trackAdminNotificationResend };
