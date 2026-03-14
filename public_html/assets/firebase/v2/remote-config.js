// v2/remote-config.js - Remote Config helper
// Exports: fetchRemoteConfigValues, fetchRemoteConfig

import initFirebase, { getRemoteConfigInstance, getFirebaseConfig } from './init.js';
import { DebugUtils } from './debug.js';
import { fetchAndActivate, getValue } from 'firebase/remote-config';

export async function fetchRemoteConfigValues() {
  await initFirebase();
  const rc = getRemoteConfigInstance();
  if (!rc) return null;
  try {
    await fetchAndActivate(rc);
    return getValue(rc, 'notification_config').asString();
  } catch (e) {
    DebugUtils.moduleError('remoteConfig', 'Failed to fetch configuration');
    return null;
  }
}

export async function fetchRemoteConfig() {
  try {
    return await getFirebaseConfig(0);
  } catch (e) { return null; }
}

export default { fetchRemoteConfigValues, fetchRemoteConfig };
