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

    // Snapshot of last persisted state (after load/save/publish)
    this._persistedSnapshot = null;
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
    // Do not touch persisted snapshot here
  }

  // Create a deep snapshot of the current timetable to compare later
  setPersistedSnapshotFromCurrent() {
    const clone = {};
    this.days.forEach((day) => {
      clone[day] = {};
      this.timeSlots.forEach((time) => {
        const entry = this.timetableData?.[day]?.[time] || null;
        if (entry) {
          // Copy without transient flags
          const { __persisted, ...rest } = entry;
          clone[day][time] = JSON.parse(JSON.stringify(rest));
        } else {
          clone[day][time] = null;
        }
      });
    });
    this._persistedSnapshot = clone;
  }

  // Recompute hasUnsavedChanges by comparing current state with snapshot
  computeUnsavedChanges() {
    if (!this._persistedSnapshot) {
      this.hasUnsavedChanges = false;
      return this.hasUnsavedChanges;
    }

    for (const day of this.days) {
      for (const time of this.timeSlots) {
        const curr = this.timetableData?.[day]?.[time] || null;
        const snap = this._persistedSnapshot?.[day]?.[time] ?? null;

        if (!curr && !snap) continue;
        if (!curr && snap) {
          this.hasUnsavedChanges = true;
          return true;
        }
        if (curr && !snap) {
          this.hasUnsavedChanges = true;
          return true;
        }
        // Both objects: compare shallowly via JSON excluding transient flag
        const { __persisted, ...currRest } = curr || {};
        if (JSON.stringify(currRest) !== JSON.stringify(snap)) {
          this.hasUnsavedChanges = true;
          return true;
        }
      }
    }
    this.hasUnsavedChanges = false;
    return false;
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