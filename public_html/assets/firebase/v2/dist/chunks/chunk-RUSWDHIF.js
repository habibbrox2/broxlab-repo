import {
  escapeHtml
} from "./chunk-OIR3NABF.js";

// public_html/assets/firebase/v2/account-conflict-handler.js
var AccountConflictHandler = class {
  constructor(containerId = "accountConflictContainer") {
    this.containerId = containerId;
    this.container = document.getElementById(containerId);
    this.resolveCallback = null;
  }
  /**
   * Show conflict modal and handle resolution
   * @param {Object} conflictData - Email and existing provider info
   * @param {string} attemptedProvider - Provider user tried to sign in with
   * @param {Object} firebaseUser - Firebase user object
   * @param {Function} onResolve - Callback when user chooses resolution
   */
  show(conflictData, attemptedProvider, firebaseUser, onResolve) {
    if (!this.container) {
      console.error("Account conflict container not found");
      return;
    }
    this.resolveCallback = onResolve;
    const existingProvider = conflictData.provider || "unknown";
    const email = escapeHtml(conflictData.email || "your email");
    const modalHTML = `
            <div class="modal fade show" id="accountConflictModal" tabindex="-1" role="dialog" aria-labelledby="accountConflictTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header border-bottom-0">
                            <h5 class="modal-title" id="accountConflictTitle">
                                <i class="bi bi-exclamation-triangle text-warning"></i> Account Already Exists
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted mb-3">
                                An account with email <strong>${email}</strong> already exists.
                            </p>
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-info-circle"></i>
                                <strong>This account uses ${this.getProviderName(existingProvider)} for sign-in.</strong>
                            </div>
                            <p class="mb-3">
                                To access your account, please use the same sign-in method you originally registered with.
                            </p>
                        </div>
                        <div class="modal-footer border-top-0 gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x"></i> Cancel
                            </button>
                            <button type="button" class="btn btn-primary" id="signInWithExistingBtn">
                                <i class="bi bi-box-arrow-in-right"></i> Sign in with ${this.getProviderName(existingProvider)}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop fade show"></div>
        `;
    this.container.innerHTML = modalHTML;
    const existingBtn = document.getElementById("signInWithExistingBtn");
    if (existingBtn) {
      existingBtn.addEventListener("click", () => {
        this.handleResolution(existingProvider, firebaseUser);
      });
    }
    const modalElement = document.getElementById("accountConflictModal");
    if (modalElement) {
      modalElement.addEventListener("hidden.bs.modal", () => {
        this.cleanup();
      });
    }
    this.displayModal();
  }
  /**
   * Display the modal using Bootstrap
   */
  displayModal() {
    const modalElement = document.getElementById("accountConflictModal");
    if (modalElement && typeof bootstrap !== "undefined" && bootstrap.Modal) {
      const modal = new bootstrap.Modal(modalElement);
      modal.show();
    } else if (modalElement) {
      modalElement.style.display = "block";
      modalElement.classList.add("show");
    }
  }
  /**
   * Handle user's resolution choice
   */
  handleResolution(provider, firebaseUser) {
    if (this.resolveCallback && typeof this.resolveCallback === "function") {
      this.resolveCallback(provider, firebaseUser);
    }
    this.cleanup();
    this.redirect(provider);
  }
  /**
   * Clean up modal DOM
   */
  cleanup() {
    if (this.container) {
      this.container.innerHTML = "";
    }
    const backdrops = document.querySelectorAll(".modal-backdrop");
    backdrops.forEach((bd) => bd.remove());
  }
  /**
   * Redirect to provider-specific sign-in
   */
  redirect(provider) {
    const redirectMap = {
      "google": "/login?auth=google",
      "facebook": "/login?auth=facebook",
      "password": "/login",
      "anonymous": "/login?auth=guest"
    };
    const redirectUrl = redirectMap[provider] || "/login";
    setTimeout(() => {
      window.location.href = redirectUrl;
    }, 1e3);
  }
  /**
   * Get provider display name
   */
  getProviderName(provider) {
    const names = {
      "google": "Google",
      "facebook": "Facebook",
      "github": "GitHub",
      "password": "Email/Password",
      "anonymous": "Guest",
      "unknown": "your original method"
    };
    return names[provider] || "your original method";
  }
};
var account_conflict_handler_default = AccountConflictHandler;

export {
  AccountConflictHandler,
  account_conflict_handler_default
};
