// status.js - Status and Publish State Management

export class StatusManager {
  constructor(config) {
    this.config = config;
  }
  
  updatePublishStatus() {
    const statusDiv = document.getElementById("status-message");
    statusDiv.classList.remove(
      "hidden",
      "status-success",
      "status-warning",
      "status-info"
    );

    // Check if timetable is completely empty
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

    // Hide status message for completely empty timetables
    if (
      isEmptyTimetable &&
      !this.config.hasUnsavedChanges &&
      !this.config.hasDraftChanges &&
      !this.config.isCurrentlyPublished
    ) {
      statusDiv.classList.add("hidden");
      return;
    }

    if (this.config.hasUnsavedChanges) {
      statusDiv.classList.add("status-warning");
      statusDiv.textContent =
        "Vous avez des modifications non enregistrées. N'oubliez pas d'enregistrer avant de quitter !";
      statusDiv.classList.remove("hidden");
    } else if (this.config.hasDraftChanges && this.config.isCurrentlyPublished) {
      statusDiv.classList.add("status-info");
      statusDiv.textContent =
        "Vous avez des modifications enregistrées qui ne sont pas encore publiées. Les étudiants et professeurs voient toujours la version précédemment publiée.";
      statusDiv.classList.remove("hidden");
    } else if (this.config.isCurrentlyPublished) {
      statusDiv.classList.add("status-success");
      statusDiv.textContent =
        "Cet emploi du temps est publié et visible par les étudiants et professeurs.";
      statusDiv.classList.remove("hidden");
    } else if (!isEmptyTimetable) {
      statusDiv.classList.add("status-warning");
      statusDiv.textContent =
        "Cet emploi du temps est enregistré mais pas encore publié. Visible uniquement par les admins jusqu'à la publication.";
      statusDiv.classList.remove("hidden");
    } else {
      statusDiv.classList.add("hidden");
    }
  }
}