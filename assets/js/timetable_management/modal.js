// modal.js - Modal Management

export class ModalManager {
  constructor() {
    this.setupModalAnimations();
  }
  
  setupModalAnimations() {
    // Setup all modals with animation and close handlers
    document.querySelectorAll(".modal").forEach((modal) => {
      modal.classList.add("hidden");
      modal.classList.remove("fade-in", "fade-out");

      const closeBtn = modal.querySelector(".close");
      if (closeBtn && modal.id) {
        closeBtn.addEventListener("click", () => {
          this.closeModal(modal.id);
        });
      }

      // Close on click outside
      if (modal.id) {
        modal.addEventListener("click", (e) => {
          if (e.target === modal) {
            this.closeModal(modal.id);
          }
        });
      }
    });
  }
  
  showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    modal.classList.remove("hidden");
    modal.classList.remove("fade-out");
    void modal.offsetWidth; // Force reflow
    modal.classList.add("fade-in");
  }
  
  closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    modal.classList.remove("fade-in");
    modal.classList.add("fade-out");

    setTimeout(() => {
      modal.classList.add("hidden");
      modal.classList.remove("fade-out");
    }, 300);
  }
  
  showUnsavedChangesWarning(callback) {
    this.showModal("unsaved-changes-modal");

    const closeBtn = document.getElementById("unsaved-close");
    const discardBtn = document.getElementById("discard-btn");
    const saveBtn = document.getElementById("save-continue-btn");

    // Clone and replace buttons to remove old event listeners
    const newCloseBtn = closeBtn.cloneNode(true);
    closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);

    const newDiscardBtn = discardBtn.cloneNode(true);
    discardBtn.parentNode.replaceChild(newDiscardBtn, discardBtn);

    const newSaveBtn = saveBtn.cloneNode(true);
    saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);

    // Add event listeners
    newCloseBtn.addEventListener("click", () => {
      this.closeModal("unsaved-changes-modal");
    });

    newDiscardBtn.addEventListener("click", () => {
      this.closeModal("unsaved-changes-modal");
      if (callback) callback(false);
    });

    newSaveBtn.addEventListener("click", () => {
      this.closeModal("unsaved-changes-modal");
      if (callback) callback(true);
    });
  }
}

// Make functions globally available (for legacy compatibility)
window.showModalWithAnimation = function(modalId) {
  const modalManager = new ModalManager();
  modalManager.showModal(modalId);
};

window.closeModalWithAnimation = function(modalId) {
  const modalManager = new ModalManager();
  modalManager.closeModal(modalId);
};