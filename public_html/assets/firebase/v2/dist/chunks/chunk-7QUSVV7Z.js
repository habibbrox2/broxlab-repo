import {
  trackUserLoginEvent,
  trackUserLogout
} from "./chunk-QJ2663K6.js";
import {
  normalizeProvider
} from "./chunk-OIR3NABF.js";
import {
  FacebookAuthProvider,
  GithubAuthProvider,
  GoogleAuthProvider,
  createUserWithEmailAndPassword,
  getAuthInstance,
  getRedirectResult,
  init_default,
  onAuthStateChanged,
  reauthenticateWithCredential,
  sendEmailVerification,
  sendPasswordResetEmail,
  signInAnonymously,
  signInWithEmailAndPassword,
  signInWithPopup,
  signInWithRedirect,
  signOut,
  updateProfile
} from "./chunk-UEMGXEGC.js";
import {
  fetchWithTimeout
} from "./chunk-3CAKWXPH.js";
import {
  DebugUtils
} from "./chunk-A5EIDU75.js";

// public_html/assets/firebase/v2/auth.js
async function _ensureAuth() {
  await init_default();
  const auth = getAuthInstance();
  if (!auth) throw new Error("Firebase auth not available");
  return auth;
}
async function _syncFcmTokenAfterBackend(backendResult) {
  try {
    const userId = backendResult?.data?.user_id || backendResult?.data?.userId || null;
    if (!userId) return;
    const mod = await import("../messaging.js");
    if (mod && mod.obtainAndSendFCMToken) {
      await mod.obtainAndSendFCMToken({ requestPermission: false, userId });
    }
  } catch (e) {
    DebugUtils.moduleWarn("auth", "FCM token sync after backend skipped");
  }
}
async function syncIdTokenWithBackend(user, options = {}) {
  if (!user) return { success: false, error: "missing_user" };
  const provider = normalizeProvider(options.provider);
  const endpoint = options.endpoint || "/api/firebase/signin";
  try {
    const idToken = await user.getIdToken(true);
    const { ok, status, data, error } = await fetchWithTimeout(endpoint, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest"
      },
      body: JSON.stringify({ idToken, provider })
    });
    const payload = data && typeof data === "object" ? data : {};
    if (status === 409) {
      return { success: false, conflict: payload?.conflict || null, status: 409, data: payload };
    }
    if (!ok || payload?.success === false) {
      const errorCode = payload?.error_code || payload?.code || "signin_failed";
      const errorMessage = payload?.error || payload?.message || "Sign-in sync failed.";
      return { success: false, error: errorMessage, error_code: errorCode, status, data: payload };
    }
    return { success: true, data: payload };
  } catch (err) {
    const isAbortError = String(err?.name || "").toLowerCase() === "aborterror";
    return {
      success: false,
      error: err?.message || "signin_exception",
      error_code: isAbortError ? "network_timeout" : "network_request_failed"
    };
  }
}
async function signInWithGoogle(options = {}) {
  const auth = await _ensureAuth();
  const provider = new GoogleAuthProvider();
  try {
    const res = await signInWithPopup(auth, provider);
    DebugUtils.moduleLog("auth", "Google sign-in successful");
    try {
      await trackUserLoginEvent(res?.user?.uid);
    } catch (e) {
    }
    try {
      const mod = await import("../messaging.js");
      if (mod && mod.obtainAndSendFCMToken && !options.syncWithBackend) {
        await mod.obtainAndSendFCMToken({ requestPermission: false });
      }
    } catch (e) {
      DebugUtils.moduleWarn("auth", "FCM token sync after authentication skipped");
    }
    if (options.syncWithBackend) {
      res.backend = await syncIdTokenWithBackend(res?.user, { provider: "google", endpoint: options.endpoint });
      await _syncFcmTokenAfterBackend(res.backend);
    }
    return res;
  } catch (err) {
    DebugUtils.moduleError("auth", "Authentication failed");
    throw err;
  }
}
async function signInWithFacebook(options = {}) {
  const auth = await _ensureAuth();
  const provider = new FacebookAuthProvider();
  try {
    const res = await signInWithPopup(auth, provider);
    DebugUtils.moduleLog("auth", "Facebook sign-in successful");
    try {
      await trackUserLoginEvent(res?.user?.uid);
    } catch (e) {
    }
    try {
      const mod = await import("../messaging.js");
      if (mod && mod.obtainAndSendFCMToken && !options.syncWithBackend) {
        await mod.obtainAndSendFCMToken({ requestPermission: false });
      }
    } catch (e) {
      DebugUtils.moduleWarn("auth", "FCM token sync after authentication skipped");
    }
    if (options.syncWithBackend) {
      res.backend = await syncIdTokenWithBackend(res?.user, { provider: "facebook", endpoint: options.endpoint });
      await _syncFcmTokenAfterBackend(res.backend);
    }
    return res;
  } catch (err) {
    DebugUtils.moduleError("auth", "Authentication failed");
    throw err;
  }
}
async function signInWithGithub(options = {}) {
  const auth = await _ensureAuth();
  const provider = new GithubAuthProvider();
  try {
    const res = await signInWithPopup(auth, provider);
    DebugUtils.moduleLog("auth", "GitHub sign-in successful");
    try {
      await trackUserLoginEvent(res?.user?.uid);
    } catch (e) {
    }
    try {
      const mod = await import("../messaging.js");
      if (mod && mod.obtainAndSendFCMToken && !options.syncWithBackend) {
        await mod.obtainAndSendFCMToken({ requestPermission: false });
      }
    } catch (e) {
      DebugUtils.moduleWarn("auth", "FCM token sync after authentication skipped");
    }
    if (options.syncWithBackend) {
      res.backend = await syncIdTokenWithBackend(res?.user, { provider: "github", endpoint: options.endpoint });
      await _syncFcmTokenAfterBackend(res.backend);
    }
    return res;
  } catch (err) {
    DebugUtils.moduleError("auth", "Authentication failed");
    throw err;
  }
}
async function signInWithRedirectProvider(providerName, options = {}) {
  const auth = await _ensureAuth();
  const normalized = String(providerName || "").toLowerCase();
  let provider = null;
  if (normalized === "google") {
    provider = new GoogleAuthProvider();
    provider.addScope("email");
    provider.addScope("profile");
    provider.setCustomParameters({ prompt: "select_account" });
  } else if (normalized === "facebook") {
    provider = new FacebookAuthProvider();
    provider.addScope("email");
  } else if (normalized === "github") {
    provider = new GithubAuthProvider();
  } else {
    throw Object.assign(new Error("Invalid provider for redirect sign-in"), { code: "auth/invalid-provider" });
  }
  try {
    await signInWithRedirect(auth, provider);
    return { initiated: true };
  } catch (err) {
    DebugUtils.moduleError("auth", "Redirect sign-in failed");
    throw err;
  }
}
async function getRedirectResult2() {
  const auth = await _ensureAuth();
  try {
    const res = await getRedirectResult(auth);
    return res;
  } catch (err) {
    DebugUtils.moduleError("auth", "Get redirect result failed");
    throw err;
  }
}
async function signInWithEmail(email, password, options = {}) {
  const auth = await _ensureAuth();
  try {
    const res = await signInWithEmailAndPassword(auth, email, password);
    DebugUtils.moduleLog("auth", "Email sign-in successful");
    try {
      await trackUserLoginEvent(res?.user?.uid);
    } catch (e) {
    }
    try {
      const mod = await import("../messaging.js");
      if (mod && mod.obtainAndSendFCMToken && !options.syncWithBackend) {
        await mod.obtainAndSendFCMToken({ requestPermission: false });
      }
    } catch (e) {
      DebugUtils.moduleWarn("auth", "FCM token sync after authentication skipped");
    }
    if (options.syncWithBackend) {
      res.backend = await syncIdTokenWithBackend(res?.user, { provider: "password", endpoint: options.endpoint });
      await _syncFcmTokenAfterBackend(res.backend);
    }
    return res;
  } catch (e) {
    DebugUtils.moduleError("auth", "Email authentication failed");
    throw e;
  }
}
async function signUpWithEmail(email, password, displayName = null, options = {}) {
  const auth = await _ensureAuth();
  try {
    const res = await createUserWithEmailAndPassword(auth, email, password);
    DebugUtils.moduleLog("auth", "Email sign-up successful");
    if (displayName) {
      try {
        await updateProfile(res.user, { displayName });
      } catch (e) {
      }
    }
    if (options.sendEmailVerification !== false) {
      try {
        await sendEmailVerification(res.user);
      } catch (e) {
      }
    }
    if (options.syncWithBackend) {
      res.backend = await syncIdTokenWithBackend(res?.user, { provider: "password", endpoint: options.endpoint });
      await _syncFcmTokenAfterBackend(res.backend);
    }
    return res;
  } catch (e) {
    DebugUtils.moduleError("auth", "Email sign-up failed");
    throw e;
  }
}
async function signInAnonymous(options = {}) {
  const auth = await _ensureAuth();
  try {
    const res = await signInAnonymously(auth);
    DebugUtils.moduleLog("auth", "Anonymous sign-in successful");
    if (options.syncWithBackend) {
      res.backend = await syncIdTokenWithBackend(res?.user, { provider: "anonymous", endpoint: options.endpoint });
      await _syncFcmTokenAfterBackend(res.backend);
    }
    return res;
  } catch (e) {
    DebugUtils.moduleError("auth", "Anonymous sign-in failed");
    throw e;
  }
}
var signInAnonymously2 = signInAnonymous;
async function resetPassword(email) {
  const auth = await _ensureAuth();
  try {
    await sendPasswordResetEmail(auth, email);
    DebugUtils.moduleLog("auth", "Password reset email sent");
    return true;
  } catch (e) {
    DebugUtils.moduleError("auth", "Password reset failed");
    throw e;
  }
}
async function updateUserProfile(data = {}) {
  const auth = await _ensureAuth();
  if (!auth.currentUser) throw new Error("No authenticated user");
  await updateProfile(auth.currentUser, data);
  return auth.currentUser;
}
async function getIdToken(forceRefresh = false) {
  const auth = await _ensureAuth();
  if (!auth.currentUser) return null;
  return auth.currentUser.getIdToken(forceRefresh);
}
async function signOutUser(options = {}) {
  const auth = await _ensureAuth();
  try {
    const current = auth.currentUser;
    const res = await signOut(auth);
    DebugUtils.moduleLog("auth", "Sign-out successful");
    try {
      await trackUserLogout(current?.uid);
    } catch (e) {
    }
    if (options.syncWithBackend && options.redirectTo) {
      window.location.href = options.redirectTo;
    }
    return res;
  } catch (e) {
    DebugUtils.moduleError("auth", "Sign-out failed");
    throw e;
  }
}
async function onAuthStateChanged2(cb) {
  const auth = await _ensureAuth();
  return onAuthStateChanged(auth, (user) => {
    DebugUtils.moduleLog("auth", user ? "User authenticated" : "User logged out");
    cb(user);
  });
}
async function getCurrentUser() {
  const auth = await _ensureAuth();
  return auth.currentUser || null;
}
async function reauthenticateWithCredential2(credential) {
  const auth = await _ensureAuth();
  return reauthenticateWithCredential(auth.currentUser, credential);
}
var auth_default = {
  signInWithGoogle,
  signInWithFacebook,
  signInWithGithub,
  signInWithEmail,
  signUpWithEmail,
  signInAnonymous,
  signInAnonymously: signInAnonymously2,
  resetPassword,
  updateUserProfile,
  getIdToken,
  syncIdTokenWithBackend,
  signOutUser,
  onAuthStateChanged: onAuthStateChanged2,
  getCurrentUser,
  reauthenticateWithCredential: reauthenticateWithCredential2
};

export {
  syncIdTokenWithBackend,
  signInWithGoogle,
  signInWithFacebook,
  signInWithGithub,
  signInWithRedirectProvider,
  getRedirectResult2 as getRedirectResult,
  signInWithEmail,
  signUpWithEmail,
  signInAnonymous,
  signInAnonymously2 as signInAnonymously,
  resetPassword,
  updateUserProfile,
  getIdToken,
  signOutUser,
  onAuthStateChanged2 as onAuthStateChanged,
  getCurrentUser,
  reauthenticateWithCredential2 as reauthenticateWithCredential,
  auth_default
};
