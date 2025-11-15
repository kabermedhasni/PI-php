// dragHandler.js - Drag and Drop Handler

export class DragHandler {
  constructor(config, modal, toast, statusManager, renderer) {
    this.config = config;
    this.modal = modal;
    this.toast = toast;
    this.statusManager = statusManager;
    this.renderer = renderer;
  }

  attachDragHandlers() {
    const cells = document.querySelectorAll("#timetable-body .subject-cell");

    cells.forEach((cell) => {
      const day = cell.getAttribute("data-day");
      const time = cell.getAttribute("data-time");
      const classBlock = cell.querySelector(".class-block");

      if (classBlock) {
        this.setupDraggableClass(classBlock, day, time);
      }

      this.setupDropTarget(cell);
    });
  }

  setupDraggableClass(classBlock, day, time) {
    classBlock.setAttribute("draggable", "true");

    classBlock.addEventListener("dragstart", (e) => {
      this.config.dragSourceDay = day;
      this.config.dragSourceTime = time;
      this.config.dragDestinationDay = null;
      this.config.dragDestinationTime = null;

      classBlock.classList.add("dragging");

      if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = "move";
        e.dataTransfer.setData("text/plain", `${day}-${time}`);
      }
    });

    classBlock.addEventListener("dragend", () => {
      classBlock.classList.remove("dragging");
      document
        .querySelectorAll(".subject-cell.drop-target")
        .forEach((c) => c.classList.remove("drop-target"));
    });
  }

  setupDropTarget(cell) {
    cell.addEventListener("dragover", (e) => {
      if (!this.config.dragSourceDay) return;
      e.preventDefault();
      if (e.dataTransfer) {
        e.dataTransfer.dropEffect = "move";
      }
      cell.classList.add("drop-target");
    });

    cell.addEventListener("dragleave", () => {
      cell.classList.remove("drop-target");
    });

    cell.addEventListener("drop", (e) => {
      if (!this.config.dragSourceDay) return;
      e.preventDefault();

      document
        .querySelectorAll(".subject-cell.drop-target")
        .forEach((c) => c.classList.remove("drop-target"));

      const destDay = cell.getAttribute("data-day");
      const destTime = cell.getAttribute("data-time");

      this.config.dragDestinationDay = destDay;
      this.config.dragDestinationTime = destTime;

      if (
        this.config.dragDestinationDay === this.config.dragSourceDay &&
        this.config.dragDestinationTime === this.config.dragSourceTime
      ) {
        this.config.resetDragState();
        return;
      }

      const sourceData =
        this.config.timetableData[this.config.dragSourceDay]?.[
          this.config.dragSourceTime
        ];

      if (!sourceData) {
        this.config.resetDragState();
        return;
      }

      const destData =
        this.config.timetableData[this.config.dragDestinationDay]?.[
          this.config.dragDestinationTime
        ];

      this.showMoveConfirmation(sourceData, destData);
    });
  }

  showMoveConfirmation(sourceData, destData) {
    const moveMessageEl = document.getElementById("move-class-message");
    if (moveMessageEl) {
      const destLabel = `${this.config.dragDestinationDay} ${this.config.dragDestinationTime}`;

      if (destData) {
        const sourceLabel = `${this.config.dragSourceDay} ${this.config.dragSourceTime}`;
        const srcSubject = sourceData.subject || "(Matière inconnue)";
        const srcProf = sourceData.professor || "(Professeur inconnu)";
        const srcRoom = sourceData.room || "(Salle inconnue)";

        const destSubject = destData.subject || "(Matière inconnue)";
        const destProf = destData.professor || "(Professeur inconnu)";
        const destRoom = destData.room || "(Salle inconnue)";

        moveMessageEl.innerHTML =
          `<strong>Cours déplacé :</strong><br>` +
          `${srcSubject} — ${srcProf} — Salle ${srcRoom} (${sourceLabel})<br><br>` +
          `<strong>Cours cible :</strong><br>` +
          `${destSubject} — ${destProf} — Salle ${destRoom} (${destLabel})<br><br>` +
          `Voulez-vous échanger ces deux cours ?`;
      } else {
        const srcSubject = sourceData.subject || "ce cours";
        moveMessageEl.textContent = `Voulez-vous déplacer ${srcSubject} vers le créneau ${destLabel} ?`;
      }

      this.modal.showModal("move-class-modal");
    } else {
      this.applyMoveOrSwap();
    }
  }

  applyMoveOrSwap() {
    if (!this.config.dragSourceDay || !this.config.dragDestinationDay) {
      this.config.resetDragState();
      return;
    }

    const sourceData =
      this.config.timetableData[this.config.dragSourceDay]?.[
        this.config.dragSourceTime
      ];
    const destData =
      this.config.timetableData[this.config.dragDestinationDay]?.[
        this.config.dragDestinationTime
      ];

    if (!sourceData) {
      this.config.resetDragState();
      return;
    }

    if (destData) {
      // Swap classes
      this.config.timetableData[this.config.dragSourceDay][
        this.config.dragSourceTime
      ] = destData;
      this.config.timetableData[this.config.dragDestinationDay][
        this.config.dragDestinationTime
      ] = sourceData;
    } else {
      // Move class
      this.config.timetableData[this.config.dragSourceDay][
        this.config.dragSourceTime
      ] = null;
      this.config.timetableData[this.config.dragDestinationDay][
        this.config.dragDestinationTime
      ] = sourceData;
    }

    this.config.hasUnsavedChanges = true;
    this.statusManager.updatePublishStatus();

    // Re-render with callbacks
    const timetableManager = window.timetableManagerInstance;
    if (timetableManager) {
      this.renderer.render(
        (day, time) => timetableManager.openEditModal(day, time),
        (day, time, subject) =>
          timetableManager.handleDeleteClick(day, time, subject),
        (day, time) => timetableManager.openAddModal(day, time)
      );
    }

    this.toast.show(
      "success",
      "Cours déplacé. N'oubliez pas d'enregistrer les modifications."
    );
    this.config.resetDragState();
  }
}
