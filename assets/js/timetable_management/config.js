// config.js - Configuration and State Management

export class TimetableConfig {
  constructor(rootElement) {
    this.root = rootElement;
    this.cfg = this.loadConfig();
    
    // State variables
    this.timetableData = {};
    this.currentYear = this.cfg.currentYear;
    this.currentGroup = this.cfg.currentGroup;
    this.groupsByYear = this.cfg.groupsByYear || {};
    this.hasUnsavedChanges = false;
    this.isCurrentlyPublished = false;
    this.hasDraftChanges = false;
    this.pendingDestination = null;
    this.deleteClassDay = null;
    this.deleteClassTime = null;
    
    // Drag and drop state
    this.dragSourceDay = null;
    this.dragSourceTime = null;
    this.dragDestinationDay = null;
    this.dragDestinationTime = null;
    
    // Constants
    this.timeSlots = this.cfg.timeSlots || [];
    this.days = this.cfg.days || [];
  }
  
  loadConfig() {
    try {
      const raw = this.root.getAttribute("data-config") || "{}";
      return JSON.parse(raw);
    } catch (e) {
      console.error("Invalid admin timetable config JSON:", e);
      return {};
    }
  }
  
  initTimetableData() {
    this.timetableData = {};
    this.days.forEach((day) => {
      this.timetableData[day] = {};
      this.timeSlots.forEach((time) => {
        this.timetableData[day][time] = null;
      });
    });
    this.hasUnsavedChanges = false;
    this.isCurrentlyPublished = false;
    this.hasDraftChanges = false;
  }
  
  resetDragState() {
    this.dragSourceDay = null;
    this.dragSourceTime = null;
    this.dragDestinationDay = null;
    this.dragDestinationTime = null;
  }
  
  cleanUrlParameters() {
    try {
      const url = new URL(window.location.href);
      let changed = false;

      if (url.searchParams.has("year")) {
        url.searchParams.delete("year");
        changed = true;
      }

      if (url.searchParams.has("group")) {
        url.searchParams.delete("group");
        changed = true;
      }

      if (changed) {
        window.history.replaceState({}, "", url);
      }
    } catch (e) {
      console.error("Failed to clean admin year/group from URL:", e);
    }
  }
}