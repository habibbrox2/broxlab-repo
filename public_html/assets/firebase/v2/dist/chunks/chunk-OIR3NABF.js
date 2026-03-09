// public_html/assets/js/shared/utils.js
function escapeHtml(text) {
  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;"
  };
  return String(text ?? "").replace(/[&<>"']/g, (char) => map[char]);
}
function getCsrfToken(selector) {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta?.content) return meta.content;
  if (selector) {
    const el = document.querySelector(selector);
    if (el) return el.value || el.content || "";
  }
  const hidden = document.getElementById("csrf_token");
  if (hidden?.value) return hidden.value;
  return "";
}

// public_html/assets/firebase/v2/firebase-utils.js
function normalizeProvider(provider) {
  if (!provider) return "firebase";
  const str = String(provider).toLowerCase();
  if (str === "google.com" || str === "google") return "google";
  if (str === "facebook.com" || str === "facebook") return "facebook";
  if (str === "github.com" || str === "github") return "github";
  if (str === "anonymous" || str === "guest") return "anonymous";
  return str;
}
function getErrorMessage(error) {
  if (!error) return "Unknown error occurred";
  if (error.message) return error.message;
  if (error.error) return error.error;
  return String(error);
}
function isPopupClosedError(error) {
  const code = String(error?.code || "").toLowerCase().replace(/^auth\//, "");
  return code === "popup_closed_by_user" || code === "cancelled-popup-request" || code === "popup_closed";
}
function getCsrfToken2() {
  return getCsrfToken();
}
var escapeHtml2 = escapeHtml;

export {
  normalizeProvider,
  getErrorMessage,
  isPopupClosedError,
  getCsrfToken2 as getCsrfToken,
  escapeHtml2 as escapeHtml
};
