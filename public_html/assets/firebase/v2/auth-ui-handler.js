/**
 * Firebase Auth UI Handler (v2)
 * Centralized handler for Firebase OAuth with popup/redirect
 * Manages sign-in, sign-up, error handling, and backend sync
 * 
 * Uses modular Firebase SDK (not FirebaseUI widget)
 * Provides clean popup/redirect API for login & register pages
 */

import * as Auth from './auth.js';
import { DebugUtils } from './debug.js';
import AccountConflictHandler from './account-conflict-handler.js';

// ============ CONSTANTS ============
const STATUS_TYPES = {
    SUCCESS: 'success',
    DANGER: 'danger',
    WARNING: 'warning',
    INFO: 'info'
};

const ERROR_CODES = {
    POPUP_CLOSED: 'popup-closed-by-user',
    UNSUPPORTED_OPERATION: 'operation-not-supported-in-this-environment',
    NETWORK_REQUEST_FAILED: 'network-request-failed',
    NETWORK_TIMEOUT: 'network-timeout',
    ACCOUNT_EXISTS: 'account-exists-with-different-credential',
    EMAIL_EXISTS: 'email-already-in-use'
};

// ============ PRIVATE STATE ============
let _isProcessing = false;
let _statusCallback = null;
import { normalizeProvider, getErrorMessage, isPopupClosedError, getCsrfToken, escapeHtml } from './firebase-utils.js';

let _successCallback = null;
let _conflictCallback = null;
let _errorCallback = null;
let _conflictHandler = null;

// ============ UTILS ============
// helper wrappers around shared functions
function _isPopupClosedError(error) {
    return isPopupClosedError(error);
}

function _getErrorMessage(error) {
    return getErrorMessage(error);
}

function _normalizeProvider(providerId) {
    return normalizeProvider(providerId);
}

function _setStatus(message, type = 'info') {
    if (_statusCallback && typeof _statusCallback === 'function') {
        _statusCallback(message, type);
    }
    DebugUtils.moduleLog('auth-ui', `Status [${type}]: ${message}`);
}

function _setProcessing(state) {
    _isProcessing = state;
    const buttons = document.querySelectorAll('[data-auth-provider]');
    buttons.forEach(btn => {
        btn.disabled = state;
        if (state) {
            btn.setAttribute('aria-disabled', 'true');
        } else {
            btn.removeAttribute('aria-disabled');
        }
    });
}

// ============ BACKEND SYNC ============
async function _syncWithBackend(user, provider, endpoint = '/api/firebase/signin') {
    if (!user) {
        throw new Error('No user available for backend sync');
    }

    try {
        const idToken = await user.getIdToken(true);
        const res = await Auth.syncIdTokenWithBackend(user, { provider, endpoint });

        if (!res.success) {
            if (res.conflict) {
                // Account conflict detection
                DebugUtils.moduleWarn('auth-ui', `Account conflict detected: ${res.conflict}`);
                return { success: false, conflict: res.conflict, conflictProvider: provider };
            }
            throw new Error(res.error || 'Backend sync failed');
        }

        return { success: true, data: res.data, userId: res.data?.user_id };
    } catch (err) {
        DebugUtils.moduleError('auth-ui', `Backend sync failed: ${err.message}`);
        throw err;
    }
}

// ============ OAUTH SIGN-IN (POPUP) ============
export async function signInWithGoogle(options = {}) {
    if (_isProcessing) return;
    _setProcessing(true);
    _setStatus('Signing in with Google...', 'info');

    try {
        const signInOptions = {
            syncWithBackend: true,
            endpoint: options.endpoint || '/api/firebase/signin',
            ...options
        };

        const result = await Auth.signInWithGoogle(signInOptions);

        if (!result?.user) {
            throw new Error('Authentication failed: no user returned');
        }

        // Sync with backend
        const syncResult = await _syncWithBackend(result.user, 'google', signInOptions.endpoint);

        if (syncResult.conflict) {
            _setStatus('Account already exists with this email', 'warning');

            // Show conflict modal
            if (!_conflictHandler) {
                _conflictHandler = new AccountConflictHandler();
            }

            _conflictHandler.show(
                syncResult.conflict,
                'google',
                result.user,
                (provider, user) => {
                    if (_conflictCallback) {
                        _conflictCallback(syncResult.conflict, 'google', user);
                    }
                }
            );

            DebugUtils.moduleError('auth-ui', 'Google sign-in resulted in account conflict');
            return { success: false, conflict: syncResult.conflict };
        }

        if (!syncResult.success) {
            const msg = syncResult.error || 'Authentication failed';
            _setStatus(msg, 'danger');
            DebugUtils.moduleError('auth-ui', `Google sign-in error: ${msg}`);

            if (_errorCallback) {
                _errorCallback({
                    provider: 'google',
                    message: msg
                });
            }
            return { success: false, error: msg };
        }

        // Success - call callback
        _setStatus('Login successful! Redirecting...', 'success');
        if (_successCallback) {
            _successCallback(result, 'google');
        }

        // Redirect if specified
        if (options.redirectTo) {
            setTimeout(() => {
                window.location.href = options.redirectTo;
            }, 500);
        }

        return { success: true, user: result.user };
    } catch (error) {
        const msg = _getErrorMessage(error);
        DebugUtils.moduleError('auth-ui', `Google sign-in exception: ${msg}`);

        // Check for popup closed by user
        if (_isPopupClosedError(error)) {
            _setStatus('Sign-in cancelled', 'warning');
        } else {
            _setStatus(msg || 'Google sign-in failed', 'danger');
        }

        if (_errorCallback) {
            _errorCallback({
                provider: 'google',
                message: msg
            });
        }

        return { success: false, error: msg };
    } finally {
        _setProcessing(false);
    }
}

export async function signInWithFacebook(options = {}) {
    if (_isProcessing) return;
    _setProcessing(true);
    _setStatus('Signing in with Facebook...', 'info');

    try {
        const signInOptions = {
            syncWithBackend: true,
            endpoint: options.endpoint || '/api/firebase/signin',
            ...options
        };

        const result = await Auth.signInWithFacebook(signInOptions);

        if (!result?.user) {
            throw new Error('Authentication failed: no user returned');
        }

        const syncResult = await _syncWithBackend(result.user, 'facebook', signInOptions.endpoint);

        if (syncResult.conflict) {
            _setStatus('Account already exists with this email', 'warning');

            // Show conflict modal
            if (!_conflictHandler) {
                _conflictHandler = new AccountConflictHandler();
            }

            _conflictHandler.show(
                syncResult.conflict,
                'facebook',
                result.user,
                (provider, user) => {
                    if (_conflictCallback) {
                        _conflictCallback(syncResult.conflict, 'facebook', user);
                    }
                }
            );

            DebugUtils.moduleError('auth-ui', 'Facebook sign-in resulted in account conflict');
            return { success: false, conflict: syncResult.conflict };
        }

        if (!syncResult.success) {
            const msg = syncResult.error || 'Authentication failed';
            _setStatus(msg, 'danger');
            DebugUtils.moduleError('auth-ui', `Facebook sign-in error: ${msg}`);

            if (_errorCallback) {
                _errorCallback({
                    provider: 'facebook',
                    message: msg
                });
            }
            return { success: false, error: msg };
        }

        // Success
        _setStatus('Login successful! Redirecting...', 'success');
        if (_successCallback) {
            _successCallback(result, 'facebook');
        }

        // Redirect if specified
        if (options.redirectTo) {
            setTimeout(() => {
                window.location.href = options.redirectTo;
            }, 500);
        }

        return { success: true, user: result.user };
    } catch (error) {
        const msg = _getErrorMessage(error);
        DebugUtils.moduleError('auth-ui', `Facebook sign-in exception: ${msg}`);

        if (_isPopupClosedError(error)) {
            _setStatus('Sign-in cancelled', 'warning');
        } else {
            _setStatus(msg || 'Facebook sign-in failed', 'danger');
        }

        if (_errorCallback) {
            _errorCallback({
                provider: 'facebook',
                message: msg
            });
        }

        return { success: false, error: msg };
    } finally {
        _setProcessing(false);
    }
}

export async function signInAnonymous(options = {}) {
    if (_isProcessing) return;
    _setProcessing(true);
    _setStatus('Creating guest session...', 'info');

    try {
        const signInOptions = {
            syncWithBackend: true,
            endpoint: options.endpoint || '/api/firebase/signin',
            ...options
        };

        const result = await Auth.signInAnonymous(signInOptions);

        if (!result?.user) {
            throw new Error('Guest sign-in failed: no user returned');
        }

        const syncResult = await _syncWithBackend(result.user, 'anonymous', signInOptions.endpoint);

        if (syncResult.conflict) {
            _setStatus('Account conflict detected', 'warning');

            if (!_conflictHandler) {
                _conflictHandler = new AccountConflictHandler();
            }

            _conflictHandler.show(
                syncResult.conflict,
                'anonymous',
                result.user,
                (provider, user) => {
                    if (_conflictCallback) {
                        _conflictCallback(syncResult.conflict, 'anonymous', user);
                    }
                }
            );

            DebugUtils.moduleError('auth-ui', 'Anonymous sign-in resulted in account conflict');
            return { success: false, conflict: syncResult.conflict };
        }

        if (!syncResult.success) {
            const msg = syncResult.error || 'Guest sign-in failed';
            _setStatus(msg, 'danger');
            DebugUtils.moduleError('auth-ui', `Anonymous sign-in error: ${msg}`);

            if (_errorCallback) {
                _errorCallback({
                    provider: 'anonymous',
                    message: msg
                });
            }
            return { success: false, error: msg };
        }

        // Success - redirect if needed
        _setStatus('Guest session created! Redirecting...', 'success');
        if (_successCallback) {
            _successCallback(result, 'anonymous');
        }

        // Redirect if specified
        if (options.redirectTo) {
            setTimeout(() => {
                window.location.href = options.redirectTo;
            }, 500);
        }

        return { success: true, user: result.user };
    } catch (error) {
        const msg = _getErrorMessage(error);
        DebugUtils.moduleError('auth-ui', `Anonymous sign-in exception: ${msg}`);

        _setStatus(msg || 'Guest sign-in failed', 'danger');

        if (_errorCallback) {
            _errorCallback({
                provider: 'anonymous',
                message: msg
            });
        }

        return { success: false, error: msg };
    } finally {
        _setProcessing(false);
    }
}

// ============ EMAIL/PASSWORD SIGN-UP ============
export async function signUpWithEmail(email, password, userData = {}, options = {}) {
    if (_isProcessing) return;
    _setProcessing(true);
    _setStatus('Creating account...', 'info');

    try {
        const displayName = [userData.firstName, userData.lastName].filter(Boolean).join(' ').trim() || userData.displayName;

        const signUpOptions = {
            sendEmailVerification: true,
            syncWithBackend: true,
            endpoint: options.endpoint || '/api/firebase/signin',
            ...options
        };

        const result = await Auth.signUpWithEmail(email, password, displayName, signUpOptions);

        if (!result?.user) {
            throw new Error('Sign-up failed: no user returned');
        }

        // Sync with backend
        const syncResult = await _syncWithBackend(result.user, 'password', signUpOptions.endpoint);

        _setStatus('Account created! Check email for verification link.', 'success');
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

        // Handle specific Firebase errors
        if (error.code === 'auth/email-already-in-use') {
            msg = 'This email is already registered. Please sign in instead.';
        } else if (error.code === 'auth/weak-password') {
            msg = 'Password is too weak. Please use a stronger password.';
        } else if (error.code === 'auth/invalid-email') {
            msg = 'Invalid email address.';
        }

        _setStatus(`Sign-up failed: ${msg}`, 'danger');
        DebugUtils.moduleError('auth-ui', `Email sign-up error: ${msg}`);

        if (_errorCallback) {
            _errorCallback({
                provider: 'password',
                message: msg,
                error: error,
                field: error.code === 'auth/email-already-in-use' ? 'email' : undefined
            });
        }
        return { success: false, error: msg, errorCode: error.code };
    } finally {
        _setProcessing(false);
    }
}

// ============ EMAIL/PASSWORD SIGN-IN ============
export async function signInWithEmail(email, password, options = {}) {
    if (_isProcessing) return;
    _setProcessing(true);
    _setStatus('Signing in...', 'info');

    try {
        const signInOptions = {
            syncWithBackend: true,
            endpoint: options.endpoint || '/api/firebase/signin',
            ...options
        };

        const result = await Auth.signInWithEmail(email, password, signInOptions);

        if (!result?.user) {
            throw new Error('Sign-in failed: no user returned');
        }

        // Sync with backend
        const syncResult = await _syncWithBackend(result.user, 'password', signInOptions.endpoint);

        _setStatus('Successfully signed in!', 'success');
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

        if (error.code === 'auth/user-not-found' || error.code === 'auth/wrong-password') {
            msg = 'Invalid email or password.';
        } else if (error.code === 'auth/user-disabled') {
            msg = 'This account has been disabled.';
        } else if (error.code === 'auth/invalid-email') {
            msg = 'Invalid email address.';
        }

        _setStatus(`Sign-in failed: ${msg}`, 'danger');
        DebugUtils.moduleError('auth-ui', `Email sign-in error: ${msg}`);

        if (_errorCallback) {
            _errorCallback({
                provider: 'password',
                message: msg,
                error: error
            });
        }
        return { success: false, error: msg, errorCode: error.code };
    } finally {
        _setProcessing(false);
    }
}

// ============ PASSWORD RESET ============
export async function resetPassword(email) {
    _setProcessing(true);
    _setStatus('Sending password reset email...', 'info');

    try {
        await Auth.resetPassword(email);
        _setStatus('Password reset email sent. Check your inbox!', 'success');
        return { success: true };
    } catch (error) {
        const msg = _getErrorMessage(error);
        _setStatus(`Password reset failed: ${msg}`, 'danger');
        DebugUtils.moduleError('auth-ui', `Password reset error: ${msg}`);
        return { success: false, error: msg };
    } finally {
        _setProcessing(false);
    }
}

// ============ SIGN OUT ============
export async function signOutUser(options = {}) {
    try {
        _setStatus('Signing out...', 'info');
        await Auth.signOutUser(options);
        _setStatus('Signed out successfully', 'success');

        if (options.redirectTo) {
            setTimeout(() => {
                window.location.href = options.redirectTo;
            }, 500);
        }
        return { success: true };
    } catch (error) {
        const msg = _getErrorMessage(error);
        _setStatus(`Sign-out failed: ${msg}`, 'danger');
        DebugUtils.moduleError('auth-ui', `Sign-out error: ${msg}`);
        return { success: false, error: msg };
    }
}

// ============ SETUP CALLBACKS ============
export function setStatusCallback(callback) {
    _statusCallback = callback;
}

export function setSuccessCallback(callback) {
    _successCallback = callback;
}

export function setConflictCallback(callback) {
    _conflictCallback = callback;
}

export function setErrorCallback(callback) {
    _errorCallback = callback;
}

export function setAllCallbacks(callbacks) {
    if (callbacks.onStatus) _statusCallback = callbacks.onStatus;
    if (callbacks.onSuccess) _successCallback = callbacks.onSuccess;
    if (callbacks.onConflict) _conflictCallback = callbacks.onConflict;
    if (callbacks.onError) _errorCallback = callbacks.onError;
}

// ============ STATE HELPERS ============
export function isProcessing() {
    return _isProcessing;
}

export function resetState() {
    _isProcessing = false;
    _statusCallback = null;
    _successCallback = null;
    _conflictCallback = null;
    _errorCallback = null;
}

// ============ EXPORTS ============
export default {
    signInWithGoogle,
    signInWithFacebook,
    signInAnonymous,
    signUpWithEmail,
    signInWithEmail,
    resetPassword,
    signOutUser,
    setStatusCallback,
    setSuccessCallback,
    setConflictCallback,
    setErrorCallback,
    setAllCallbacks,
    isProcessing,
    resetState
};
