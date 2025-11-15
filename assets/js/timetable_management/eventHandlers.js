// eventHandlers.js - Event Handler Setup

export class EventHandlers {
  constructor(config, api, modal, statusManager, renderer, formHandler) {
    this.config = config;
    this.api = api;
    this.modal = modal;
    this.statusManager = statusManager;
    this.renderer = renderer;
    this.formHandler = formHandler;
  }

  setupAllEventHandlers() {
    this.setupActionButtons();
    this.setupModalButtons();
    this.setupDeleteModals();
    this.setupMoveClassModal();
    this.setupSubgroupRadioButtons();
    this.setupNavigationWarning();
    this.setupBeforeUnload();
    this.setupRadioAnimations();
  }

  setupActionButtons() {
    document.getElementById("save-btn").addEventListener("click", async () => {
      const success = await this.api.saveTimetable();
      if (success) {
        this.statusManager.updatePublishStatus();
      }
    });

    document
      .getElementById("publish-btn")
      .addEventListener("click", async () => {
        const success = await this.api.publishTimetable();
        if (success) {
          this.statusManager.updatePublishStatus();
        }
      });

    document
      .getElementById("delete-timetable-btn")
      .addEventListener("click", () => {
        document.getElementById("delete-year-group").textContent =
          this.config.currentYear + " - " + this.config.currentGroup;
        this.modal.showModal("delete-timetable-modal");
      });
  }

  setupModalButtons() {
    document.getElementById("cancel-btn").addEventListener("click", () => {
      this.modal.closeModal("class-modal");
    });
  }

  setupDeleteModals() {
    // Delete timetable modal
    document
      .getElementById("delete-timetable-close")
      .addEventListener("click", () => {
        this.modal.closeModal("delete-timetable-modal");
      });

    document
      .getElementById("delete-timetable-cancel")
      .addEventListener("click", () => {
        this.modal.closeModal("delete-timetable-modal");
      });

    document
      .getElementById("delete-timetable-confirm")
      .addEventListener("click", async () => {
        const success = await this.api.deleteTimetable();
        this.modal.closeModal("delete-timetable-modal");

        if (success) {
          this.config.initTimetableData();

          const timetableManager = window.timetableManagerInstance;
          if (timetableManager) {
            this.renderer.render(
              (d, t) => timetableManager.openEditModal(d, t),
              (d, t, s) => timetableManager.handleDeleteClick(d, t, s),
              (d, t) => timetableManager.openAddModal(d, t)
            );
          }

          this.config.hasUnsavedChanges = false;
          this.config.isCurrentlyPublished = false;
          this.config.hasDraftChanges = false;
          this.statusManager.updatePublishStatus();
        }
      });

    // Delete class modal
    document
      .getElementById("delete-class-confirm")
      .addEventListener("click", async () => {
        if (this.config.deleteClassDay && this.config.deleteClassTime) {
          const day = this.config.deleteClassDay;
          const time = this.config.deleteClassTime;

          this.config.timetableData[day][time] = null;
          this.statusManager.updatePublishStatus();

          const success = await this.api.deleteClass(day, time);

          if (success) {
            const timetableManager = window.timetableManagerInstance;
            if (timetableManager) {
              this.renderer.render(
                (d, t) => timetableManager.openEditModal(d, t),
                (d, t, s) => timetableManager.handleDeleteClick(d, t, s),
                (d, t) => timetableManager.openAddModal(d, t)
              );
            }
          }

          this.modal.closeModal("delete-class-modal");
          this.config.deleteClassDay = null;
          this.config.deleteClassTime = null;
        }
      });

    // Professor conflict modal
    document.getElementById("conflict-cancel").addEventListener("click", () => {
      this.modal.closeModal("professor-conflict-modal");
    });

    // Room conflict modal
    document
      .getElementById("room-conflict-cancel")
      .addEventListener("click", () => {
        this.modal.closeModal("room-conflict-modal");
      });

    document
      .getElementById("room-conflict-close")
      .addEventListener("click", () => {
        this.modal.closeModal("room-conflict-modal");
      });
  }

  setupMoveClassModal() {
    const moveClassModal = document.getElementById("move-class-modal");
    if (moveClassModal) {
      const moveClose = document.getElementById("move-class-close");
      const moveCancel = document.getElementById("move-class-cancel");
      const moveConfirm = document.getElementById("move-class-confirm");

      if (moveClose) {
        moveClose.addEventListener("click", () => {
          this.modal.closeModal("move-class-modal");
          this.config.resetDragState();
        });
      }

      if (moveCancel) {
        moveCancel.addEventListener("click", () => {
          this.modal.closeModal("move-class-modal");
          this.config.resetDragState();
        });
      }

      if (moveConfirm) {
        moveConfirm.addEventListener("click", () => {
          this.modal.closeModal("move-class-modal");

          const dragHandler = window.dragHandlerInstance;
          if (dragHandler) {
            dragHandler.applyMoveOrSwap();
          }
        });
      }
    }
  }

  setupSubgroupRadioButtons() {
    document
      .getElementById("subgroup-single")
      .addEventListener("change", () => {
        document
          .getElementById("subgroup-split-options")
          .classList.add("hidden");
        document
          .getElementById("second-subgroup-options")
          .classList.add("hidden");
        document
          .getElementById("single-subgroup-selector")
          .classList.add("hidden");
      });

    document.getElementById("subgroup-split").addEventListener("change", () => {
      document
        .getElementById("subgroup-split-options")
        .classList.remove("hidden");

      if (document.getElementById("subgroup-same-time").checked) {
        document
          .getElementById("second-subgroup-options")
          .classList.remove("hidden");
        document
          .getElementById("single-subgroup-selector")
          .classList.add("hidden");

        setTimeout(() => {
          const secondSubgroupOptions = document.getElementById(
            "second-subgroup-options"
          );
          if (secondSubgroupOptions) {
            secondSubgroupOptions.scrollIntoView({
              behavior: "smooth",
              block: "nearest",
            });
          }
        }, 100);
      } else {
        document
          .getElementById("second-subgroup-options")
          .classList.add("hidden");
        document
          .getElementById("single-subgroup-selector")
          .classList.remove("hidden");
      }
    });

    document
      .getElementById("subgroup-same-time")
      .addEventListener("change", () => {
        document
          .getElementById("second-subgroup-options")
          .classList.remove("hidden");
        document
          .getElementById("single-subgroup-selector")
          .classList.add("hidden");

        setTimeout(() => {
          const secondSubgroupOptions = document.getElementById(
            "second-subgroup-options"
          );
          if (secondSubgroupOptions) {
            secondSubgroupOptions.scrollIntoView({
              behavior: "smooth",
              block: "nearest",
            });
          }
        }, 100);
      });

    document
      .getElementById("subgroup-single-group")
      .addEventListener("change", () => {
        document
          .getElementById("second-subgroup-options")
          .classList.add("hidden");
        document
          .getElementById("single-subgroup-selector")
          .classList.remove("hidden");
      });
  }

  setupNavigationWarning() {
    const backLink = document.querySelector('a[href="../admin/index.php"]');
    if (backLink) {
      backLink.addEventListener("click", (e) => {
        if (this.config.hasUnsavedChanges) {
          e.preventDefault();
          const href = backLink.getAttribute("href");
          this.modal.showUnsavedChangesWarning(async (shouldSave) => {
            if (shouldSave) {
              const success = await this.api.saveTimetable();
              if (!success) return;
            }
            window.location.href = href;
          });
        }
      });
    }
  }

  setupBeforeUnload() {
    window.addEventListener("beforeunload", (e) => {
      if (this.config.hasUnsavedChanges) {
        e.preventDefault();
        e.returnValue = "";
        return "";
      }
    });
  }

  setupRadioAnimations() {
    document.addEventListener("click", (e) => {
      if (e.target.type === "radio") {
        e.target.style.animation = "none";
        e.target.offsetHeight; // Force reflow
        e.target.style.animation = "";
      }
    });

    document.querySelectorAll('input[type="radio"]').forEach((radio) => {
      radio.addEventListener("change", function () {
        if (this.checked) {
          this.style.animation = "none";
          void this.offsetHeight; // Trigger reflow
          this.style.animation = "";
        }
      });
    });
  }
}
