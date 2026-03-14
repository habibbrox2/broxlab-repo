// v2/auth.js - Modular Auth functions for Firebase v9+ (CDN modular imports)
// Uses centralized debug control from debug.js
// Exports maintain same function signatures used by existing code.

import initFirebase, { getAuthInstance } from './init.js';
import * as Analytics from './analytics.js';
import { DebugUtils } from './debug.js';
import {
  GoogleAuthProvider,
  FacebookAuthProvider,
  GithubAuthProvider,
  signInWithPopup,
  signInWithRedirect,
  getRedirectResult as firebaseGetRedirectResult,
  signInWithEmailAndPassword,
  createUserWithEmailAndPassword,
  sendEmailVerification,
  sendPasswordResetEmail,
  updateProfile,
  signInAnonymously as fbSignInAnonymously,
  signOut,
  onAuthStateChanged as onAuthStateChangedFn,
  reauthenticateWithCredential as fbReauthenticateWithCredential
} from 'firebase/auth';

import { fetchWithTimeout } from '../../js/shared/fetch-utils.js';
import { normalizeProvider } from './firebase-utils.js';

async function _ensureAuth() {
  await initFirebase();
  const auth = getAuthInstance();
  if (!auth) throw new Error('Firebase auth not available');
  return auth;
}

async function _syncFcmTokenAfterBackend(backendResult) {
  try {
    const userId = backendResult?.data?.user_id || backendResult?.data?.userId || null;
    if (!userId) return;
    const mod = await import('./messaging.js');
    if (mod && mod.obtainAndSendFCMToken) {
      await mod.obtainAndSendFCMToken({ requestPermission: false, userId });
    }
  } catch (e) {
    DebugUtils.moduleWarn('auth', 'FCM token sync after backend skipped');
  }
}

export async function syncIdTokenWithBackend(user, options = {}) {
  if (!user) return { success: false, error: 'missing_user' };
  const provider = normalizeProvider(options.provider);
  const endpoint = options.endpoint || '/api/firebase/signin';

  try {
    const idToken = await user.getIdToken(true);
    const { ok, status, data, error } = await fetchWithTimeout(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ idToken, provider })
    });

    const payload = (data && typeof data === 'object') ? data : {};

    if (status === 409) {
      return { success: false, conflict: payload?.conflict || null, status: 409, data: payload };
    }

    if (!ok || payload?.success === false) {
      const errorCode = payload?.error_code || payload?.code || 'signin_failed';
      const errorMessage = payload?.error || payload?.message || 'Sign-in sync failed.';
      return { success: false, error: errorMessage, error_code: errorCode, status, data: payload };
    }

    return { success: true, data: payload };
  } catch (err) {
    const isAbortError = String(err?.name || '').toLowerCase() === 'aborterror';
    return {
      success: false,
      error: err?.message || 'signin_exception',
      error_code: isAbortError ? 'network_timeout' : 'network_request_failed'
    };
  }
}

export async function signInWithGoogle(options = {}) {
  const auth = await _ensureAuth();
  const provider = new GoogleAuthProvider();
  try {
    const res = await signInWithPopup(auth, provider);
    DebugUtils.moduleLog('auth', 'Google sign-in successful');
    try {
      await Analytics.trackUserLoginEvent(res?.user?.uid);
    } catch (e) { /* swallow analytics errors */ }
    try {
      const mod = await import('./messaging.js');
      if (mod && mod.obtainAndSendFCMToken && !options.syncWithBackend) {
        await mod.obtainAndSendFCMToken({ requestPermission: false });
      }
    } catch (e) {
      DebugUtils.moduleWarn('auth', 'FCM token sync after authentication skipped');
    }
    if (options.syncWithBackend) {
      res.backend = await syncIdTokenWithBackend(res?.user, { provider: 'google', endpoint: options.endpoint });
      await _syncFcmTokenAfterBackend(res.backend);
    }
    return res;
  } catch (err) {
    DebugUtils.moduleError('auth', 'Authentication failed');
    throw err;
  }
}

export async function signInWithFacebook(options = {}) {
  const auth = await _ensureAuth();
  const provider = new FacebookAuthProvider();
  try {
    const res = await signInWithPopup(auth, provider);
    DebugUtils.moduleLog('auth', 'Facebook sign-in successful');
    try {
      await Analytics.trackUserLoginEvent(res?.user?.uid);
    } catch (e) { }
    try {
      const mod = await import('./messaging.js');
      if (mod && mod.obtainAndSendFCMToken && !options.syncWithBackend) {
        await mod.obtainAndSendFCMToken({ requestPermission: false });
      }
    } catch (e) {
      DebugUtils.moduleWarn('auth', 'FCM token sync after authentication skipped');
    }
    if (options.syncWithBackend) {
      res.backend = await syncIdTokenWithBackend(res?.user, { provider: 'facebook', endpoint: options.endpoint });
      await _syncFcmTokenAfterBackend(res.backend);
    }
    return res;
  } catch (err) {
    DebugUtils.moduleError('auth', 'Authentication failed');
    throw err;
  }
}

export async function signInWithGithub(options = {}) {
  const auth = await _ensureAuth();
  const provider = new GithubAuthProvider();
  try {
    const res = await signInWithPopup(auth, provider);
    DebugUtils.moduleLog('auth', 'GitHub sign-in successful');
    try {
      await Analytics.trackUserLoginEvent(res?.user?.uid);
    } catch (e) { }
    try {
      const mod = await import('./messaging.js');
      if (mod && mod.obtainAndSendFCMToken && !options.syncWithBackend) {
        await mod.obtainAndSendFCMToken({ requestPermission: false });
      }
    } catch (e) {
      DebugUtils.moduleWarn('auth', 'FCM token sync after authentication skipped');
    }
    if (options.syncWithBackend) {
      res.backend = await syncIdTokenWithBackend(res?.user, { provider: 'github', endpoint: options.endpoint });
      await _syncFcmTokenAfterBackend(res.backend);
    }
    return res;
  } catch (err) {
    DebugUtils.moduleError('auth', 'Authentication failed');
    throw err;
  }
}

export async function signInWithRedirectProvider(providerName, options = {}) {
  const auth = await _ensureAuth();
  const normalized = String(providerName || '').toLowerCase();
  let provider = null;
  if (normalized === 'google') {
    provider = new GoogleAuthProvider();
    provider.addScope('email');
    provider.addScope('profile');
    provider.setCustomParameters({ prompt: 'select_account' });
  } else if (normalized === 'facebook') {
    provider = new FacebookAuthProvider();
    provider.addScope('email');
  } else if (normalized === 'github') {
    provider = new GithubAuthProvider();
  } else {
    throw Object.assign(new Error('Invalid provider for redirect sign-in'), { code: 'auth/invalid-provider' });
  }

  try {
    await signInWithRedirect(auth, provider);
    // signInWithRedirect triggers a navigation; this function will not resolve with a credential.
    return { initiated: true };
  } catch (err) {
    DebugUtils.moduleError('auth', 'Redirect sign-in failed');
    throw err;
  }
}

export async function getRedirectResult() {
  const auth = await _ensureAuth();
  try {
    const res = await firebaseGetRedirectResult(auth);
    return res;
  } catch (err) {
    DebugUtils.moduleError('auth', 'Get redirect result failed');
    throw err;
  }
}

export async function signInWithEmail(email, password, options = {}) {
  const auth = await _ensureAuth();
  try {
    const res = await signInWithEmailAndPassword(auth, email, password);
    DebugUtils.moduleLog('auth', 'Email sign-in successful');
    try {
      await Analytics.trackUserLoginEvent(res?.user?.uid);
    } catch (e) { }
    try {
      const mod = await import('./messaging.js');
      if (mod && mod.obtainAndSendFCMToken && !options.syncWithBackend) {
        await mod.obtainAndSendFCMToken({ requestPermission: false });
      }
    } catch (e) {
      DebugUtils.moduleWarn('auth', 'FCM token sync after authentication skipped');
    }
    if (options.syncWithBackend) {
      res.backend = await syncIdTokenWithBackend(res?.user, { provider: 'password', endpoint: options.endpoint });
      await _syncFcmTokenAfterBackend(res.backend);
    }
    return res;
  } catch (e) {
    DebugUtils.moduleError('auth', 'Email authentication failed');
    throw e;
  }
}

export async function signUpWithEmail(email, password, displayName = null, options = {}) {
  const auth = await _ensureAuth();
  try {
    const res = await createUserWithEmailAndPassword(auth, email, password);
    DebugUtils.moduleLog('auth', 'Email sign-up successful');
    if (displayName) {
      try { await updateProfile(res.user, { displayName }); } catch (e) { }
    }
    if (options.sendEmailVerification !== false) {
      try { await sendEmailVerification(res.user); } catch (e) { }
    }
    if (options.syncWithBackend) {
      res.backend = await syncIdTokenWithBackend(res?.user, { provider: 'password', endpoint: options.endpoint });
      await _syncFcmTokenAfterBackend(res.backend);
    }
    return res;
  } catch (e) {
    DebugUtils.moduleError('auth', 'Email sign-up failed');
    throw e;
  }
}

export async function signInAnonymous(options = {}) {
  const auth = await _ensureAuth();
  try {
    const res = await fbSignInAnonymously(auth);
    DebugUtils.moduleLog('auth', 'Anonymous sign-in successful');
    if (options.syncWithBackend) {
      res.backend = await syncIdTokenWithBackend(res?.user, { provider: 'anonymous', endpoint: options.endpoint });
      await _syncFcmTokenAfterBackend(res.backend);
    }
    return res;
  } catch (e) {
    DebugUtils.moduleError('auth', 'Anonymous sign-in failed');
    throw e;
  }
}

// Alias for consistency with Firebase naming
export const signInAnonymously = signInAnonymous;

export async function resetPassword(email) {
  const auth = await _ensureAuth();
  try {
    await sendPasswordResetEmail(auth, email);
    DebugUtils.moduleLog('auth', 'Password reset email sent');
    return true;
  } catch (e) {
    DebugUtils.moduleError('auth', 'Password reset failed');
    throw e;
  }
}

export async function updateUserProfile(data = {}) {
  const auth = await _ensureAuth();
  if (!auth.currentUser) throw new Error('No authenticated user');
  await updateProfile(auth.currentUser, data);
  return auth.currentUser;
}

export async function getIdToken(forceRefresh = false) {
  const auth = await _ensureAuth();
  if (!auth.currentUser) return null;
  return auth.currentUser.getIdToken(forceRefresh);
}

export async function signOutUser(options = {}) {
  const auth = await _ensureAuth();
  try {
    const current = auth.currentUser;
    const res = await signOut(auth);
    DebugUtils.moduleLog('auth', 'Sign-out successful');
    try {
      await Analytics.trackUserLogout(current?.uid);
    } catch (e) { }
    if (options.syncWithBackend && options.redirectTo) {
      window.location.href = options.redirectTo;
    }
    return res;
  } catch (e) {
    DebugUtils.moduleError('auth', 'Sign-out failed');
    throw e;
  }
}

export async function onAuthStateChanged(cb) {
  const auth = await _ensureAuth();
  return onAuthStateChangedFn(auth, (user) => {
    DebugUtils.moduleLog('auth', user ? 'User authenticated' : 'User logged out');
    cb(user);
  });
}

export async function getCurrentUser() {
  const auth = await _ensureAuth();
  return auth.currentUser || null;
}

export async function reauthenticateWithCredential(credential) {
  const auth = await _ensureAuth();
  return fbReauthenticateWithCredential(auth.currentUser, credential);
}

export default {
  signInWithGoogle,
  signInWithFacebook,
  signInWithGithub,
  signInWithEmail,
  signUpWithEmail,
  signInAnonymous,
  signInAnonymously,
  resetPassword,
  updateUserProfile,
  getIdToken,
  syncIdTokenWithBackend,
  signOutUser,
  onAuthStateChanged,
  getCurrentUser,
  reauthenticateWithCredential
};
