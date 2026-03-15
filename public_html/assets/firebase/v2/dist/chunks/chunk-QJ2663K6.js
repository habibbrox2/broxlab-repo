import {
  getAnalyticsInstance,
  init_default,
  logEvent,
  setUserId,
  setUserProperties
} from "./chunk-UEMGXEGC.js";
import {
  DebugUtils
} from "./chunk-A5EIDU75.js";

// public_html/assets/firebase/v2/analytics.js
async function _getAnalytics() {
  await init_default();
  return getAnalyticsInstance();
}
async function logEvent2(name, params = {}) {
  const analytics = await _getAnalytics();
  if (!analytics) return null;
  try {
    DebugUtils.moduleLog("analytics", `Event: ${name}`);
    return logEvent(analytics, name, params);
  } catch (e) {
    DebugUtils.moduleError("analytics", "Event logging failed");
  }
}
async function setUserId2(uid) {
  const analytics = await _getAnalytics();
  if (!analytics) return null;
  try {
    DebugUtils.moduleLog("analytics", "User ID set");
    return setUserId(analytics, uid);
  } catch (e) {
    DebugUtils.moduleError("analytics", "Failed to set user ID");
  }
}
async function setUserProperties2(props = {}) {
  const analytics = await _getAnalytics();
  if (!analytics) return null;
  try {
    DebugUtils.moduleLog("analytics", "User properties updated");
    return setUserProperties(analytics, props);
  } catch (e) {
    DebugUtils.moduleError("analytics", "Failed to set user properties");
  }
}
async function trackUserLogin(userId) {
  return logEvent2("login", { user_id: userId });
}
async function trackUserLogout(userId) {
  return logEvent2("user_logout", { user_id: userId, timestamp: (/* @__PURE__ */ new Date()).toISOString() });
}
async function trackTokenGenerated(tokenData = {}) {
  return logEvent2("fcm_token_generated", { token_length: tokenData.token ? tokenData.token.length : 0, user_id: tokenData.user_id || "guest" });
}
var trackUserLoginEvent = trackUserLogin;
var trackUserLogoutEvent = trackUserLogout;
async function trackAdminNotificationSend(notificationData, recipientCount = 0) {
  try {
    await logEvent2("admin_notification_send", { payload: notificationData, recipient_count: recipientCount });
  } catch (e) {
    DebugUtils?.warn("trackAdminNotificationSend failed", e);
  }
}
async function trackAdminNotificationResend(notificationId, recipientCount = 0) {
  try {
    await logEvent2("admin_notification_resend", { notification_id: notificationId, recipient_count: recipientCount });
  } catch (e) {
    DebugUtils?.warn("trackAdminNotificationResend failed", e);
  }
}
var analytics_default = { logEvent: logEvent2, setUserId: setUserId2, setUserProperties: setUserProperties2, trackUserLogin, trackUserLogout, trackTokenGenerated, trackAdminNotificationSend, trackAdminNotificationResend };

export {
  logEvent2 as logEvent,
  setUserId2 as setUserId,
  setUserProperties2 as setUserProperties,
  trackUserLogin,
  trackUserLogout,
  trackTokenGenerated,
  trackUserLoginEvent,
  trackUserLogoutEvent,
  trackAdminNotificationSend,
  trackAdminNotificationResend,
  analytics_default
};
