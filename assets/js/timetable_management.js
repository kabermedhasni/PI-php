// timetable_management.js - Main Application Orchestrator

import { TimetableConfig } from "./timetable_management/config.js";
import { ModalManager } from "./timetable_management/modal.js";
import { DropdownManager } from "./timetable_management/dropdown.js";
import { ToastManager } from "./timetable_management/toast.js";
import { StatusManager } from "./timetable_management/status.js";
import { APIManager } from "./timetable_management/api.js";
import { TimetableRenderer } from "./timetable_management/timetableRenderer.js";
import { DragHandler } from "./timetable_management/dragHandler.js";
import { FormHandler } from "./timetable_management/formHandler.js";
import { DropdownItemHandlers } from "./timetable_management/dropdownItems.js";
import { EventHandlers } from "./timetable_management/eventHandlers.js";

// Main Application Class
class TimetableManager {
  constructor() {
    // Get root element
    this.root = document.getElementById("timetable-root");
    if (!this.root) {
      console.error("Timetable root element not found");
      return;
    }

    // Initialize all managers
    this.config = new TimetableConfig(this.root);
    this.modal = new ModalManager();
    this.dropdown = new DropdownManager();
    this.toast = new ToastManager();
    this.statusManager = new StatusManager(this.config);
    this.api = new APIManager(this.config, this.toast);

    // Initialize renderer and handlers (these need references to each other)
    this.dragHandler = null; // Will be set after renderer
    this.renderer = new TimetableRenderer(this.config, this.dragHandler);
    this.dragHandler = new DragHandler(
      this.config,
      this.modal,
      this.toast,
      this.statusManager,
      this.renderer
    );

    // Update renderer's drag handler reference
    this.renderer.dragHandler = this.dragHandler;

    this.formHandler = new FormHandler(
      this.config,
      this.api,
      this.modal,
      this.toast,
      this.statusManager,
      this.renderer
    );

    this.dropdownItems = new DropdownItemHandlers(
      this.config,
      this.api,
      this.modal,
      this.toast,
      this.statusManager,
      () => this.loadSavedData()
    );

    this.eventHandlers = new EventHandlers(
      this.config,
      this.api,
      this.modal,
      this.statusManager,
      this.renderer,
      this.formHandler
    );

    // Initialize the application
    this.initialize();
  }

  initialize() {
    // Clean URL parameters
    this.config.cleanUrlParameters();

    // Setup all event handlers
    this.dropdown.setupProfessorSearch();
    this.dropdownItems.setupAllHandlers();
    this.eventHandlers.setupAllEventHandlers();

    // Initialize group dropdown for the current year so it only shows that year's groups
    if (this.config.currentYear && this.config.groupsByYear) {
      this.dropdownItems.updateGroupDropdown(this.config.currentYear);
    }

    // Setup form submission
    document.getElementById("class-form").addEventListener("submit", (e) => {
      this.formHandler.handleFormSubmit(e);
    });

    // Initialize timetable data and render
    this.config.initTimetableData();
    this.renderTimetable();

    // Load saved data from server
    this.loadSavedData();

    // Store instance globally for callbacks
    window.timetableManagerInstance = this;
    window.dragHandlerInstance = this.dragHandler;
  }

  renderTimetable() {
    this.renderer.render(
      (day, time) => this.openEditModal(day, time),
      (day, time, subject) => this.handleDeleteClick(day, time, subject),
      (day, time) => this.openAddModal(day, time)
    );
  }

  openAddModal(day, time) {
    this.formHandler.openAddModal(day, time);
  }

  openEditModal(day, time) {
    this.formHandler.openEditModal(day, time);
  }

  handleDeleteClick(day, time, subject) {
    this.config.deleteClassDay = day;
    this.config.deleteClassTime = time;
    document.getElementById("delete-class-name").textContent = subject;
    this.modal.showModal("delete-class-modal");
  }

  async loadSavedData() {
    // Initialize with empty timetable first
    this.config.initTimetableData();

    try {
      const result = await this.api.loadTimetableData();

      if (result.success && result.data) {
        console.log("Received timetable data:", result.data);

        // Initialize structure for all days and time slots
        this.config.days.forEach((day) => {
          this.config.timetableData[day] = {};
          this.config.timeSlots.forEach((time) => {
            this.config.timetableData[day][time] = null;
          });
        });

        // Populate with received data
        for (const day in result.data) {
          if (!this.config.timetableData[day]) {
            this.config.timetableData[day] = {};
          }

          for (const time in result.data[day]) {
            const entry = result.data[day][time];
            if (entry) entry.__persisted = true;
            this.config.timetableData[day][time] = entry;

            // Debug logging for split classes
            if (entry && entry.is_split) {
              console.log(`Split class found at ${day} ${time}:`, {
                split_type: entry.split_type,
                subgroup: entry.subgroup,
                subgroup1: entry.subgroup1,
                subgroup2: entry.subgroup2,
                subject2: entry.subject2,
                professor2: entry.professor2,
              });
            }
          }
        }

        console.log("Processed timetable data:", this.config.timetableData);

        this.renderTimetable();
        this.toast.show(
          "success",
          `Emploi du temps chargé pour ${this.config.currentYear}-${this.config.currentGroup}`
        );

        this.config.isCurrentlyPublished = result.isPublished;
        this.config.hasDraftChanges = result.hasDraftChanges;
        this.config.hasUnsavedChanges = false;
        this.config.setPersistedSnapshotFromCurrent();
        this.statusManager.updatePublishStatus();
      } else {
        console.log("No timetable data found or invalid response");
        this.renderTimetable();
        this.toast.show(
          "info",
          `Aucun emploi du temps trouvé pour ${this.config.currentYear}-${this.config.currentGroup}`
        );

        this.config.isCurrentlyPublished = false;
        this.config.hasDraftChanges = false;
        this.config.hasUnsavedChanges = false;
        this.config.setPersistedSnapshotFromCurrent();
        this.statusManager.updatePublishStatus();
      }
    } catch (error) {
      console.error("Error loading timetable data:", error);
      this.toast.show(
        "error",
        "Erreur lors du chargement des données. Vérifiez votre connexion et le chemin du projet."
      );
      this.renderTimetable();
      this.config.isCurrentlyPublished = false;
      this.config.hasDraftChanges = false;
      this.config.hasUnsavedChanges = false;
      this.statusManager.updatePublishStatus();
    }
  }
}

// Initialize application when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  new TimetableManager();
});
