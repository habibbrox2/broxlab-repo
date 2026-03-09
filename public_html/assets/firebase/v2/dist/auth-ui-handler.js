import {
  resetPassword,
  signInAnonymous,
  signInWithEmail,
  signInWithFacebook,
  signInWithGoogle,
  signOutUser,
  signUpWithEmail,
  syncIdTokenWithBackend
} from "./chunks/chunk-7QUSVV7Z.js";
import "./chunks/chunk-QJ2663K6.js";
import {
  account_conflict_handler_default
} from "./chunks/chunk-RUSWDHIF.js";
import {
  getErrorMessage,
  isPopupClosedError
} from "./chunks/chunk-OIR3NABF.js";
import "./chunks/chunk-UEMGXEGC.js";
import "./chunks/chunk-3CAKWXPH.js";
import {
  DebugUtils
} from "./chunks/chunk-A5EIDU75.js";

// public_html/assets/firebase/v2/auth-ui-handler.js
var _isProcessing = false;
var _statusCallback = null;
var _successCallback = null;
var _conflictCallback = null;
var _errorCallback = null;
var _conflictHandler = null;
function _isPopupClosedError(error) {
  return isPopupClosedError(error);
}
function _getErrorMessage(error) {
  return getErrorMessage(error);
}
function _setStatus(message, type = "info") {
  if (_statusCallback && typeof _statusCallback === "function") {
    _statusCallback(message, type);
  }
  DebugUtils.moduleLog("auth-ui", `Status [${type}]: ${message}`);
}
function _setProcessing(state) {
  _isProcessing = state;
  const buttons = document.querySelectorAll("[data-auth-provider]");
  buttons.forEach((btn) => {
    btn.disabled = state;
    if (state) {
      btn.setAttribute("aria-disabled", "true");
    } else {
      btn.removeAttribute("aria-disabled");
    }
  });
}
async function _syncWithBackend(user, provider, endpoint = "/api/firebase/signin") {
  if (!user) {
    throw new Error("No user available for backend sync");
  }
  try {
    const idToken = await user.getIdToken(true);
    const res = await syncIdTokenWithBackend(user, { provider, endpoint });
    if (!res.success) {
      if (res.conflict) {
        DebugUtils.moduleWarn("auth-ui", `Account conflict detected: ${res.conflict}`);
        return { success: false, conflict: res.conflict, conflictProvider: provider };
      }
      throw new Error(res.error || "Backend sync failed");
    }
    return { success: true, data: res.data, userId: res.data?.user_id };
  } catch (err) {
    DebugUtils.moduleError("auth-ui", `Backend sync failed: ${err.message}`);
    throw err;
  }
}
async function signInWithGoogle2(options = {}) {
  if (_isProcessing) return;
  _setProcessing(true);
  _setStatus("Signing in with Google...", "info");
  try {
    const signInOptions = {
      syncWithBackend: true,
      endpoint: options.endpoint || "/api/firebase/signin",
      ...options
    };
    const result = await signInWithGoogle(signInOptions);
    if (!result?.user) {
      throw new Error("Authentication failed: no user returned");
    }
    const syncResult = await _syncWithBackend(result.user, "google", signInOptions.endpoint);
    if (syncResult.conflict) {
      _setStatus("Account already exists with this email", "warning");
      if (!_conflictHandler) {
        _conflictHandler = new account_conflict_handler_default();
      }
      _conflictHandler.show(
        syncResult.conflict,
        "google",
        result.user,
        (provider, user) => {
          if (_conflictCallback) {
            _conflictCallback(syncResult.conflict, "google", user);
          }
        }
      );
      DebugUtils.moduleError("auth-ui", "Google sign-in resulted in account conflict");
      return { success: false, conflict: syncResult.conflict };
    }
    if (!syncResult.success) {
      const msg = syncResult.error || "Authentication failed";
      _setStatus(msg, "danger");
      DebugUtils.moduleError("auth-ui", `Google sign-in error: ${msg}`);
      if (_errorCallback) {
        _errorCallback({
          provider: "google",
          message: msg
        });
      }
      return { success: false, error: msg };
    }
    _setStatus("Login successful! Redirecting...", "success");
    if (_successCallback) {
      _successCallback(result, "google");
    }
    if (options.redirectTo) {
      setTimeout(() => {
        window.location.href = options.redirectTo;
      }, 500);
    }
    return { success: true, user: result.user };
  } catch (error) {
    const msg = _getErrorMessage(error);
    DebugUtils.moduleError("auth-ui", `Google sign-in exception: ${msg}`);
    if (_isPopupClosedError(error)) {
      _setStatus("Sign-in cancelled", "warning");
    } else {
      _setStatus(msg || "Google sign-in failed", "danger");
    }
    if (_errorCallback) {
      _errorCallback({
        provider: "google",
        message: msg
      });
    }
    return { success: false, error: msg };
  } finally {
    _setProcessing(false);
  }
}
async function signInWithFacebook2(options = {}) {
  if (_isProcessing) return;
  _setProcessing(true);
  _setStatus("Signing in with Facebook...", "info");
  try {
    const signInOptions = {
      syncWithBackend: true,
      endpoint: options.endpoint || "/api/firebase/signin",
      ...options
    };
    const result = await signInWithFacebook(signInOptions);
    if (!result?.user) {
      throw new Error("Authentication failed: no user returned");
    }
    const syncResult = await _syncWithBackend(result.user, "facebook", signInOptions.endpoint);
    if (syncResult.conflict) {
      _setStatus("Account already exists with this email", "warning");
      if (!_conflictHandler) {
        _conflictHandler = new account_conflict_handler_default();
      }
      _conflictHandler.show(
        syncResult.conflict,
        "facebook",
        result.user,
        (provider, user) => {
          if (_conflictCallback) {
            _conflictCallback(syncResult.conflict, "facebook", user);
          }
        }
      );
      DebugUtils.moduleError("auth-ui", "Facebook sign-in resulted in account conflict");
      return { success: false, conflict: syncResult.conflict };
    }
    if (!syncResult.success) {
      const msg = syncResult.error || "Authentication failed";
      _setStatus(msg, "danger");
      DebugUtils.moduleError("auth-ui", `Facebook sign-in error: ${msg}`);
      if (_errorCallback) {
        _errorCallback({
          provider: "facebook",
          message: msg
        });
      }
      return { success: false, error: msg };
    }
    _setStatus("Login successful! Redirecting...", "success");
    if (_successCallback) {
      _successCallback(result, "facebook");
    }
    if (options.redirectTo) {
      setTimeout(() => {
        window.location.href = options.redirectTo;
      }, 500);
    }
    return { success: true, user: result.user };
  } catch (error) {
    const msg = _getErrorMessage(error);
    DebugUtils.moduleError("auth-ui", `Facebook sign-in exception: ${msg}`);
    if (_isPopupClosedError(error)) {
      _setStatus("Sign-in cancelled", "warning");
    } else {
      _setStatus(msg || "Facebook sign-in failed", "danger");
    }
    if (_errorCallback) {
      _errorCallback({
        provider: "facebook",
        message: msg
      });
    }
    return { success: false, error: msg };
  } finally {
    _setProcessing(false);
  }
}
async function signInAnonymous2(options = {}) {
  if (_isProcessing) return;
  _setProcessing(true);
  _setStatus("Creating guest session...", "info");
  try {
    const signInOptions = {
      syncWithBackend: true,
      endpoint: options.endpoint || "/api/firebase/signin",
      ...options
    };
    const result = await signInAnonymous(signInOptions);
    if (!result?.user) {
      throw new Error("Guest sign-in failed: no user returned");
    }
    const syncResult = await _syncWithBackend(result.user, "anonymous", signInOptions.endpoint);
    if (syncResult.conflict) {
      _setStatus("Account conflict detected", "warning");
      if (!_conflictHandler) {
        _conflictHandler = new account_conflict_handler_default();
      }
      _conflictHandler.show(
        syncResult.conflict,
        "anonymous",
        result.user,
        (provider, user) => {
          if (_conflictCallback) {
            _conflictCallback(syncResult.conflict, "anonymous", user);
          }
        }
      );
      DebugUtils.moduleError("auth-ui", "Anonymous sign-in resulted in account conflict");
      return { success: false, conflict: syncResult.conflict };
    }
    if (!syncResult.success) {
      const msg = syncResult.error || "Guest sign-in failed";
      _setStatus(msg, "danger");
      DebugUtils.moduleError("auth-ui", `Anonymous sign-in error: ${msg}`);
      if (_errorCallback) {
        _errorCallback({
          provider: "anonymous",
          message: msg
        });
      }
      return { success: false, error: msg };
    }
    _setStatus("Guest session created! Redirecting...", "success");
    if (_successCallback) {
      _successCallback(result, "anonymous");
    }
    if (options.redirectTo) {
      setTimeout(() => {
        window.location.href = options.redirectTo;
      }, 500);
    }
    return { success: true, user: result.user };
  } catch (error) {
    const msg = _getErrorMessage(error);
    DebugUtils.moduleError("auth-ui", `Anonymous sign-in exception: ${msg}`);
    _setStatus(msg || "Guest sign-in failed", "danger");
    if (_errorCallback) {
      _errorCallback({
        provider: "anonymous",
        message: msg
      });
    }
    return { success: false, error: msg };
  } finally {
    _setProcessing(false);
  }
}
async function signUpWithEmail2(email, password, userData = {}, options = {}) {
  if (_isProcessing) return;
  _setProcessing(true);
  _setStatus("Creating account...", "info");
  try {
    const displayName = [userData.firstName, userData.lastName].filter(Boolean).join(" ").trim() || userData.displayName;
    const signUpOptions = {
      sendEmailVerification: true,
      syncWithBackend: true,
      endpoint: options.endpoint || "/api/firebase/signin",
      ...options
    };
    const result = await signUpWithEmail(email, password, displayName, signUpOptions);
    if (!result?.user) {
      throw new Error("Sign-up failed: no user returned");
    }
    const syncResult = await _syncWithBackend(result.user, "password", signUpOptions.endpoint);
    _setStatus("Account created! Check email for verification link.", "success");
    if (_successCallback) {
      _successCallback(syncResult);
    }
    if (options.redirectTo) {
      setTimeout(() => {
        window.location.href = options.redirectTo;
      }, 1500);
    }
    return syncResult;
  } catch (error) {
    let msg = _getErrorMessage(error);
    if (error.code === "auth/email-already-in-use") {
      msg = "This email is already registered. Please sign in instead.";
    } else if (error.code === "auth/weak-password") {
      msg = "Password is too weak. Please use a stronger password.";
    } else if (error.code === "auth/invalid-email") {
      msg = "Invalid email address.";
    }
    _setStatus(`Sign-up failed: ${msg}`, "danger");
    DebugUtils.moduleError("auth-ui", `Email sign-up error: ${msg}`);
    if (_errorCallback) {
      _errorCallback({
        provider: "password",
        message: msg,
        error,
        field: error.code === "auth/email-already-in-use" ? "email" : void 0
      });
    }
    return { success: false, error: msg, errorCode: error.code };
  } finally {
    _setProcessing(false);
  }
}
async function signInWithEmail2(email, password, options = {}) {
  if (_isProcessing) return;
  _setProcessing(true);
  _setStatus("Signing in...", "info");
  try {
    const signInOptions = {
      syncWithBackend: true,
      endpoint: options.endpoint || "/api/firebase/signin",
      ...options
    };
    const result = await signInWithEmail(email, password, signInOptions);
    if (!result?.user) {
      throw new Error("Sign-in failed: no user returned");
    }
    const syncResult = await _syncWithBackend(result.user, "password", signInOptions.endpoint);
    _setStatus("Successfully signed in!", "success");
    if (_successCallback) {
      _successCallback(syncResult);
    }
    if (options.redirectTo) {
      setTimeout(() => {
        window.location.href = options.redirectTo;
      }, 500);
    }
    return syncResult;
  } catch (error) {
    let msg = _getErrorMessage(error);
    if (error.code === "auth/user-not-found" || error.code === "auth/wrong-password") {
      msg = "Invalid email or password.";
    } else if (error.code === "auth/user-disabled") {
      msg = "This account has been disabled.";
    } else if (error.code === "auth/invalid-email") {
      msg = "Invalid email address.";
    }
    _setStatus(`Sign-in failed: ${msg}`, "danger");
    DebugUtils.moduleError("auth-ui", `Email sign-in error: ${msg}`);
    if (_errorCallback) {
      _errorCallback({
        provider: "password",
        message: msg,
        error
      });
    }
    return { success: false, error: msg, errorCode: error.code };
  } finally {
    _setProcessing(false);
  }
}
async function resetPassword2(email) {
  _setProcessing(true);
  _setStatus("Sending password reset email...", "info");
  try {
    await resetPassword(email);
    _setStatus("Password reset email sent. Check your inbox!", "success");
    return { success: true };
  } catch (error) {
    const msg = _getErrorMessage(error);
    _setStatus(`Password reset failed: ${msg}`, "danger");
    DebugUtils.moduleError("auth-ui", `Password reset error: ${msg}`);
    return { success: false, error: msg };
  } finally {
    _setProcessing(false);
  }
}
async function signOutUser2(options = {}) {
  try {
    _setStatus("Signing out...", "info");
    await signOutUser(options);
    _setStatus("Signed out successfully", "success");
    if (options.redirectTo) {
      setTimeout(() => {
        window.location.href = options.redirectTo;
      }, 500);
    }
    return { success: true };
  } catch (error) {
    const msg = _getErrorMessage(error);
    _setStatus(`Sign-out failed: ${msg}`, "danger");
    DebugUtils.moduleError("auth-ui", `Sign-out error: ${msg}`);
    return { success: false, error: msg };
  }
}
function setStatusCallback(callback) {
  _statusCallback = callback;
}
function setSuccessCallback(callback) {
  _successCallback = callback;
}
function setConflictCallback(callback) {
  _conflictCallback = callback;
}
function setErrorCallback(callback) {
  _errorCallback = callback;
}
function setAllCallbacks(callbacks) {
  if (callbacks.onStatus) _statusCallback = callbacks.onStatus;
  if (callbacks.onSuccess) _successCallback = callbacks.onSuccess;
  if (callbacks.onConflict) _conflictCallback = callbacks.onConflict;
  if (callbacks.onError) _errorCallback = callbacks.onError;
}
function isProcessing() {
  return _isProcessing;
}
function resetState() {
  _isProcessing = false;
  _statusCallback = null;
  _successCallback = null;
  _conflictCallback = null;
  _errorCallback = null;
}
var auth_ui_handler_default = {
  signInWithGoogle: signInWithGoogle2,
  signInWithFacebook: signInWithFacebook2,
  signInAnonymous: signInAnonymous2,
  signUpWithEmail: signUpWithEmail2,
  signInWithEmail: signInWithEmail2,
  resetPassword: resetPassword2,
  signOutUser: signOutUser2,
  setStatusCallback,
  setSuccessCallback,
  setConflictCallback,
  setErrorCallback,
  setAllCallbacks,
  isProcessing,
  resetState
};
export {
  auth_ui_handler_default as default,
  isProcessing,
  resetPassword2 as resetPassword,
  resetState,
  setAllCallbacks,
  setConflictCallback,
  setErrorCallback,
  setStatusCallback,
  setSuccessCallback,
  signInAnonymous2 as signInAnonymous,
  signInWithEmail2 as signInWithEmail,
  signInWithFacebook2 as signInWithFacebook,
  signInWithGoogle2 as signInWithGoogle,
  signOutUser2 as signOutUser,
  signUpWithEmail2 as signUpWithEmail
};
