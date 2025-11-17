// api.js - API Communication Layer

export class APIManager {
  constructor(config, toast) {
    this.config = config;
    this.toast = toast;
  }
  
  async loadTimetableData() {
    const apiUrl = "../api/timetables/get_timetable.php";
    const params = new URLSearchParams({
      year: this.config.currentYear,
      group: this.config.currentGroup,
      admin: "true",
    });

    console.log("Loading timetable data from:", `${apiUrl}?${params.toString()}`);

    try {
      const response = await fetch(`${apiUrl}?${params.toString()}`);
      
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      
      const data = await response.json();
      console.log("Server response for timetable data:", data);

      if (data && data.success !== false && data.data) {
        return {
          success: true,
          data: data.data,
          isPublished: data.is_published || false,
          hasDraftChanges: data.has_draft_changes || false
        };
      } else {
        return { success: false };
      }
    } catch (error) {
      console.error("Error loading timetable data:", error);
      throw error;
    }
  }
  
  async saveTimetable(callback) {
    const timetableDataCopy = JSON.parse(JSON.stringify(this.config.timetableData || {}));

    // Determine if timetable is completely empty
    let isEmptyTimetable = true;
    for (const day in this.config.timetableData) {
      for (const time in this.config.timetableData[day]) {
        if (this.config.timetableData[day][time] !== null) {
          isEmptyTimetable = false;
          break;
        }
      }
      if (!isEmptyTimetable) break;
    }

    // If there are no unsaved changes
    if (!this.config.hasUnsavedChanges) {
      if (isEmptyTimetable) {
        this.toast.show("info", "L'emploi du temps est vide. Rien à enregistrer.");
        if (callback) callback();
        return false;
      } else {
        this.toast.show("info", "Aucune modification à enregistrer.");
        if (callback) callback();
        return false;
      }
    }

    const payload = {
      year: this.config.currentYear,
      group: this.config.currentGroup,
      data: timetableDataCopy,
      action: "save_only",
    };

    console.log("Saving timetable with payload:", payload);

    try {
      const response = await fetch("../api/timetables/save_timetable.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }

      const data = await response.json();
      console.log("Server response:", data);

      if (data.success) {
        // Choose toast based on emptiness
        if (isEmptyTimetable) {
          this.toast.show("info", "Emploi du temps vide enregistré.");
        } else {
          this.toast.show("success", `Emploi du temps enregistré pour ${this.config.currentYear}-${this.config.currentGroup}`);
        }

        // After a successful save, mark entries as persisted
        for (const d in this.config.timetableData) {
          for (const t in this.config.timetableData[d]) {
            const entry = this.config.timetableData[d][t];
            if (entry) entry.__persisted = true;
          }
        }

        this.config.hasUnsavedChanges = false;
        this.config.isCurrentlyPublished = data.is_published || false;
        // Only set draft changes when timetable is not empty
        this.config.hasDraftChanges = data.is_published ? !isEmptyTimetable : false;

        if (callback) callback();
        return true;
      } else {
        console.error("Save failed:", data);
        this.toast.show("error", data.message || "Échec de l'enregistrement de l'emploi du temps");
        if (callback) callback();
        return false;
      }
    } catch (error) {
      console.error("Error saving timetable:", error);
      this.toast.show("error", "Erreur lors de l'enregistrement de l'emploi du temps");
      if (callback) callback();
      return false;
    }
  }
  
  async publishTimetable() {
    const timetableDataCopy = JSON.parse(JSON.stringify(this.config.timetableData || {}));

    // Determine if timetable is completely empty
    let isEmptyTimetable = true;
    for (const day in this.config.timetableData) {
      for (const time in this.config.timetableData[day]) {
        if (this.config.timetableData[day][time] !== null) {
          isEmptyTimetable = false;
          break;
        }
      }
      if (!isEmptyTimetable) break;
    }

    // If nothing to publish
    if (isEmptyTimetable) {
      this.toast.show("info", "L'emploi du temps est vide. Rien à publier.");
      return false;
    }

    if (!this.config.hasUnsavedChanges && !this.config.hasDraftChanges && this.config.isCurrentlyPublished) {
      this.toast.show("info", "Aucune modification à publier.");
      return false;
    }

    const payload = {
      year: this.config.currentYear,
      group: this.config.currentGroup,
      data: timetableDataCopy,
      action: "publish",
    };

    console.log("Publishing timetable with payload:", payload);

    try {
      const response = await fetch("../api/timetables/publish_timetable.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }

      const data = await response.json();
      console.log("Server response:", data);

      if (data.success) {
        this.toast.show("success", `Emploi du temps publié pour ${this.config.currentYear}-${this.config.currentGroup}`);
        this.config.hasUnsavedChanges = false;
        this.config.isCurrentlyPublished = true;
        this.config.hasDraftChanges = false;
        return true;
      } else {
        console.error("Publish failed:", data);
        this.toast.show("error", data.message || "Échec de la publication de l'emploi du temps");
        return false;
      }
    } catch (error) {
      console.error("Error publishing timetable:", error);
      this.toast.show("error", "Erreur lors de la publication de l'emploi du temps");
      return false;
    }
  }
  
  async deleteTimetable() {
    const payload = {
      year: this.config.currentYear,
      group: this.config.currentGroup,
    };

    try {
      const response = await fetch("../api/timetables/delete_timetable.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const data = await response.json();

      if (data.success) {
        this.toast.show("success", `Emploi du temps supprimé pour ${this.config.currentYear}-${this.config.currentGroup}`);
        return true;
      } else {
        this.toast.show("error", data.message || "Erreur lors de la suppression de l'emploi du temps");
        return false;
      }
    } catch (error) {
      console.error("Error deleting timetable:", error);
      this.toast.show("error", "Erreur lors de la suppression de l'emploi du temps");
      return false;
    }
  }
  
  async deleteClass(day, timeSlot) {
    const deleteData = {
      year: this.config.currentYear,
      group: this.config.currentGroup,
      day: day,
      time_slot: timeSlot,
    };

    console.log("Sending delete request with data:", deleteData);

    try {
      const response = await fetch("../api/classes/delete_class.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(deleteData),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }

      const data = await response.json();
      console.log("Delete response:", data);

      if (data.success) {
        this.toast.show("success", "Cours supprimé avec succès");
        return true;
      } else {
        this.toast.show("error", data.message || "Erreur lors de la suppression du cours");
        console.error("Delete error:", data);
        return false;
      }
    } catch (error) {
      console.error("Error deleting class:", error);
      this.toast.show("error", "Erreur lors de la suppression du cours");
      return false;
    }
  }
  
  async fetchProfessorSubjects(professorId) {
    const apiUrl = `../api/professors/get_professor_subjects.php?professor_id=${professorId}`;
    console.log("Fetching professor subjects from:", apiUrl);

    try {
      const response = await fetch(apiUrl);
      
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }

      const data = await response.json();
      console.log("Professor subjects response:", data);
      return data;
    } catch (error) {
      console.error("Error fetching professor subjects:", error);
      throw error;
    }
  }
  
  async checkProfessorAvailability(checkData) {
    console.log("Checking professor availability with:", checkData);

    try {
      const response = await fetch("../api/availability/check_professor_availability.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(checkData),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error("Error checking professor availability:", error);
      throw error;
    }
  }
  
  async checkRoomAvailability(checkData) {
    console.log("Checking room availability with:", checkData);

    try {
      const response = await fetch("../api/availability/check_room_availability.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(checkData),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error("Error checking room availability:", error);
      throw error;
    }
  }
}