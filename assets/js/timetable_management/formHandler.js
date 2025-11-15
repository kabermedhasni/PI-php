// formHandler.js - Form Management and Validation

export class FormHandler {
  constructor(config, api, modal, toast, statusManager, renderer) {
    this.config = config;
    this.api = api;
    this.modal = modal;
    this.toast = toast;
    this.statusManager = statusManager;
    this.renderer = renderer;
  }

  openAddModal(day, time) {
    document.getElementById("modal-title").textContent = "Ajouter un Cours";
    document.getElementById("edit-day").value = day;
    document.getElementById("edit-time").value = time;
    document.getElementById("edit-id").value = "";

    this.resetFormFields();
    this.modal.showModal("class-modal");
  }

  openEditModal(day, time) {
    const data = this.config.timetableData[day][time];
    if (!data) return;

    document.getElementById("modal-title").textContent = "Modifier un Cours";
    document.getElementById("edit-day").value = day;
    document.getElementById("edit-time").value = time;
    document.getElementById("edit-id").value = data.id || "";
    document.getElementById("edit-color").value = data.color;

    this.fillFormWithData(data);
    this.modal.showModal("class-modal");
  }

  resetFormFields() {
    document.getElementById("selected-professor").textContent =
      "Sélectionner un professeur";
    document.getElementById("selected-professor").removeAttribute("data-id");
    document.getElementById("selected-subject").textContent =
      "Sélectionner un professeur d'abord";
    document.getElementById("selected-room").textContent =
      "Sélectionner une salle";
    document.getElementById("selected-course-type").textContent = "CM";
    document.getElementById("edit-color").value = "#6b7280";

    document.getElementById("subgroup-options").classList.add("hidden");
    document.getElementById("subgroup-split-options").classList.add("hidden");
    document.getElementById("second-subgroup-options").classList.add("hidden");
    document.getElementById("single-subgroup-selector").classList.add("hidden");
    document.getElementById("subgroup-single").checked = true;

    this.resetSecondProfessorFields();
    this.resetSubgroupSelector();

    const subjectDropdown = document.getElementById("subject-dropdown");
    subjectDropdown.setAttribute("disabled", "disabled");
    subjectDropdown.style.backgroundColor = "#f1f5f9";
    subjectDropdown.style.cursor = "not-allowed";
  }

  resetSecondProfessorFields() {
    document.getElementById("selected-professor-2").textContent =
      "Sélectionner un professeur";
    document.getElementById("selected-professor-2").removeAttribute("data-id");
    document.getElementById("selected-subject-2").textContent =
      "Sélectionner un professeur d'abord";
    document
      .getElementById("subject-dropdown-2")
      .setAttribute("disabled", "disabled");
    document.getElementById("subject-dropdown-2").style.backgroundColor =
      "#f1f5f9";
    document.getElementById("subject-dropdown-2").style.cursor = "not-allowed";
    document.getElementById("selected-subject-2").removeAttribute("data-id");
    document.getElementById("selected-room-2").textContent =
      "Sélectionner une salle";
  }

  resetSubgroupSelector() {
    document.getElementById("selected-subgroup").textContent = "Sous-groupe 1";
  }

  fillFormWithData(data) {
    document.getElementById("selected-professor").textContent =
      data.professor || "Sélectionner un professeur";
    if (data.professor_id) {
      document
        .getElementById("selected-professor")
        .setAttribute("data-id", data.professor_id);
    }

    document.getElementById("subject-dropdown").removeAttribute("disabled");
    document.getElementById("selected-subject").textContent =
      data.subject || "Sélectionner une matière";
    if (data.subject_id) {
      document
        .getElementById("selected-subject")
        .setAttribute("data-id", data.subject_id);
    }

    document.getElementById("selected-room").textContent =
      data.room || "Sélectionner une salle";

    if (data.class_type) {
      document.getElementById("selected-course-type").textContent =
        data.class_type;
      this.handleSubgroupDisplay(data);
    }
  }

  handleSubgroupDisplay(data) {
    if (
      (data.class_type === "TD" || data.class_type === "TP") &&
      data.is_split
    ) {
      document.getElementById("subgroup-options").classList.remove("hidden");
      document.getElementById("subgroup-split").checked = true;

      if (data.split_type === "same_time") {
        document
          .getElementById("subgroup-split-options")
          .classList.remove("hidden");
        document.getElementById("subgroup-same-time").checked = true;
        document
          .getElementById("second-subgroup-options")
          .classList.remove("hidden");
        document
          .getElementById("single-subgroup-selector")
          .classList.add("hidden");

        this.fillSecondSubgroupData(data);
      } else if (data.split_type === "single_group") {
        document
          .getElementById("subgroup-split-options")
          .classList.remove("hidden");
        document.getElementById("subgroup-single-group").checked = true;
        document
          .getElementById("second-subgroup-options")
          .classList.add("hidden");
        document
          .getElementById("single-subgroup-selector")
          .classList.remove("hidden");

        if (data.subgroup) {
          const subgroupNum = data.subgroup.slice(-1);
          document.getElementById("selected-subgroup").textContent =
            "Sous-groupe " + subgroupNum;
        }
      }
    } else {
      document.getElementById("subgroup-options").classList.add("hidden");
      document.getElementById("subgroup-split-options").classList.add("hidden");
      document
        .getElementById("second-subgroup-options")
        .classList.add("hidden");
      document
        .getElementById("single-subgroup-selector")
        .classList.add("hidden");
      document.getElementById("subgroup-single").checked = true;
    }
  }

  fillSecondSubgroupData(data) {
    if (data.professor2) {
      document.getElementById("selected-professor-2").textContent =
        data.professor2;
      if (data.professor2_id) {
        document
          .getElementById("selected-professor-2")
          .setAttribute("data-id", data.professor2_id);
      }
    }

    if (data.subject2) {
      document.getElementById("subject-dropdown-2").removeAttribute("disabled");
      document.getElementById("subject-dropdown-2").style.backgroundColor =
        "#ffffff";
      document.getElementById("subject-dropdown-2").style.cursor = "pointer";
      document.getElementById("selected-subject-2").textContent = data.subject2;
      if (data.subject2_id) {
        document
          .getElementById("selected-subject-2")
          .setAttribute("data-id", data.subject2_id);
      }
    }

    if (data.room2) {
      document.getElementById("selected-room-2").textContent = data.room2;
    }
  }

  async handleFormSubmit(e) {
    e.preventDefault();

    const formData = this.collectFormData();

    if (!this.validateFormData(formData)) {
      return;
    }

    const classData = this.prepareClassData(formData);

    // Check availability
    const availabilityData = this.prepareAvailabilityData(formData, classData);

    try {
      const professorAvailable = await this.api.checkProfessorAvailability(
        availabilityData.professor
      );

      if (!professorAvailable.available) {
        console.log("Professor conflict found:", professorAvailable.conflicts);
        this.showProfessorConflict(professorAvailable.conflicts, classData);
        this.modal.closeModal("class-modal");
        return;
      }

      const roomAvailable = await this.api.checkRoomAvailability(
        availabilityData.room
      );

      if (!roomAvailable.available) {
        console.log("Room conflict found:", roomAvailable.conflicts);
        this.showRoomConflict(roomAvailable.conflicts, classData);
        this.modal.closeModal("class-modal");
        return;
      }

      // No conflicts, save the class
      this.saveClassData(classData, formData.day, formData.time);
    } catch (error) {
      console.error("Error checking availability:", error);
      this.toast.show(
        "error",
        "Erreur lors de la vérification de la disponibilité"
      );
    }
  }

  collectFormData() {
    return {
      day: document.getElementById("edit-day").value,
      time: document.getElementById("edit-time").value,
      id:
        document.getElementById("edit-id").value ||
        new Date().getTime().toString(),
      color: document.getElementById("edit-color").value,
      professor: document.getElementById("selected-professor").textContent,
      professorId: document
        .getElementById("selected-professor")
        .getAttribute("data-id"),
      subject: document.getElementById("selected-subject").textContent,
      subjectId: document
        .getElementById("selected-subject")
        .getAttribute("data-id"),
      room: document.getElementById("selected-room").textContent,
      courseType: document.getElementById("selected-course-type").textContent,
    };
  }

  validateFormData(formData) {
    if (formData.professor === "Sélectionner un professeur") {
      this.toast.show("error", "Veuillez sélectionner un professeur");
      return false;
    }

    const invalidSubjects = [
      "Sélectionner une matière",
      "Aucune matière disponible",
      "Erreur lors du chargement des matières",
      "Chargement des matières...",
      "Sélectionner un professeur d'abord",
    ];

    if (invalidSubjects.includes(formData.subject)) {
      this.toast.show("error", "Veuillez sélectionner une matière");
      return false;
    }

    if (formData.room === "Sélectionner une salle") {
      this.toast.show("error", "Veuillez sélectionner une salle");
      return false;
    }

    return true;
  }

  prepareClassData(formData) {
    const classData = {
      id: formData.id,
      subject: formData.subject,
      subject_id: formData.subjectId,
      professor: formData.professor,
      professor_id: formData.professorId,
      room: formData.room,
      room_id: formData.room,
      color: formData.color,
      class_type: formData.courseType,
      year: this.config.currentYear,
      group: this.config.currentGroup,
    };

    // Handle subgroups
    if (formData.courseType === "TD" || formData.courseType === "TP") {
      this.addSubgroupData(classData, formData);
    } else {
      classData.is_split = 0;
    }

    return classData;
  }

  addSubgroupData(classData, formData) {
    if (document.getElementById("subgroup-split").checked) {
      classData.is_split = 1;

      if (document.getElementById("subgroup-same-time").checked) {
        classData.split_type = "same_time";

        const professor2 = document.getElementById(
          "selected-professor-2"
        ).textContent;
        const subject2 =
          document.getElementById("selected-subject-2").textContent;
        const room2 = document.getElementById("selected-room-2").textContent;

        if (professor2 === "Sélectionner un professeur") {
          this.toast.show(
            "error",
            "Veuillez sélectionner un professeur pour le deuxième sous-groupe"
          );
          throw new Error("Validation failed");
        }

        if (
          subject2 === "Sélectionner une matière" ||
          subject2 === "Sélectionner un professeur d'abord"
        ) {
          this.toast.show(
            "error",
            "Veuillez sélectionner une matière pour le deuxième sous-groupe"
          );
          throw new Error("Validation failed");
        }

        if (room2 === "Sélectionner une salle") {
          this.toast.show(
            "error",
            "Veuillez sélectionner une salle pour le deuxième sous-groupe"
          );
          throw new Error("Validation failed");
        }

        const professor2Id = document
          .getElementById("selected-professor-2")
          .getAttribute("data-id");

        if (professor2Id === formData.professorId) {
          this.toast.show(
            "error",
            "Le même professeur ne peut pas enseigner à deux sous-groupes en même temps"
          );
          throw new Error("Validation failed");
        }

        if (room2 === formData.room) {
          this.toast.show(
            "error",
            "La même salle ne peut pas être utilisée par deux sous-groupes en même temps"
          );
          throw new Error("Validation failed");
        }

        classData.professor2 = professor2;
        classData.professor2_id = professor2Id;
        classData.subject2 = subject2;
        classData.subject2_id = document
          .getElementById("selected-subject-2")
          .getAttribute("data-id");
        classData.room2 = room2;

        const groupMatch = String(this.config.currentGroup).match(/(\d+)/);
        const groupNumber = groupMatch ? parseInt(groupMatch[1], 10) : 1;
        const subgroupNumber1 = groupNumber * 2 - 1;
        const subgroupNumber2 = groupNumber * 2;

        classData.subgroup1 = formData.courseType + subgroupNumber1;
        classData.subgroup2 = formData.courseType + subgroupNumber2;
      } else if (document.getElementById("subgroup-single-group").checked) {
        classData.split_type = "single_group";

        const subgroupElement = document.getElementById("selected-subgroup");
        const subgroupNum = subgroupElement.textContent.includes("1") ? 1 : 2;

        const groupMatch = String(this.config.currentGroup).match(/(\d+)/);
        const groupNumber = groupMatch ? parseInt(groupMatch[1], 10) : 1;
        const subgroupNumber =
          subgroupNum === 1 ? groupNumber * 2 - 1 : groupNumber * 2;

        classData.subgroup = formData.courseType + subgroupNumber;
      }
    } else {
      classData.is_split = 0;
    }
  }

  prepareAvailabilityData(formData, classData) {
    return {
      professor: {
        professor_id: formData.professorId,
        day: formData.day,
        time_slot: formData.time,
        year: this.config.currentYear,
        group: this.config.currentGroup,
        is_split: classData.is_split,
        split_type: classData.split_type,
        subgroup: classData.subgroup,
        professor2_id: classData.professor2_id,
      },
      room: {
        room: formData.room,
        day: formData.day,
        time_slot: formData.time,
        year: this.config.currentYear,
        group: this.config.currentGroup,
        is_split: classData.is_split,
        split_type: classData.split_type,
        subgroup: classData.subgroup,
        room2: classData.room2,
      },
    };
  }

  saveClassData(classData, day, time) {
    if (!this.config.timetableData[day]) {
      this.config.timetableData[day] = {};
    }

    this.config.timetableData[day][time] = classData;
    this.config.hasUnsavedChanges = true;
    this.statusManager.updatePublishStatus();

    const timetableManager = window.timetableManagerInstance;
    if (timetableManager) {
      this.renderer.render(
        (d, t) => timetableManager.openEditModal(d, t),
        (d, t, s) => timetableManager.handleDeleteClick(d, t, s),
        (d, t) => timetableManager.openAddModal(d, t)
      );
    }

    this.modal.closeModal("class-modal");
    this.toast.show(
      "success",
      "Cours enregistré ! N'oubliez pas d'utiliser le bouton Enregistrer pour sauvegarder les modifications"
    );
  }

  showProfessorConflict(conflicts, classData) {
    const conflictDetailsElement = document.getElementById("conflict-details");
    let conflictHtml = "";

    conflicts.forEach((conflict) => {
      const isProfessor2Conflict = conflict.is_professor2_conflict === true;
      const professorTitle = isProfessor2Conflict
        ? "Conflit avec le deuxième professeur:"
        : "Conflit avec le professeur:";

      conflictHtml += this.buildConflictHTML(
        conflict,
        professorTitle,
        classData
      );
    });

    conflictDetailsElement.innerHTML = conflictHtml;
    this.modal.showModal("professor-conflict-modal");
  }

  showRoomConflict(conflicts, classData) {
    const conflictDetailsElement = document.getElementById(
      "room-conflict-details"
    );
    let conflictHtml = "";

    conflicts.forEach((conflict) => {
      conflictHtml += this.buildConflictHTML(conflict, null, classData);
    });

    conflictDetailsElement.innerHTML = conflictHtml;
    this.modal.showModal("room-conflict-modal");
  }

  buildConflictHTML(conflict, titleOverride, classData) {
    let html = '<div class="conflict-item">';

    if (titleOverride) {
      html += `<p class="conflict-title conflict-title-danger">${titleOverride}</p>`;
    }

    html += `
      <p><span class="strong-text">Professeur:</span> ${
        conflict.professor || classData.professor
      }</p>
      <p><span class="strong-text">Jour:</span> ${conflict.day}</p>
      <p><span class="strong-text">Heure:</span> ${conflict.time}</p>
      <p><span class="strong-text">Année:</span> ${conflict.year}</p>
      <p><span class="strong-text">Groupe:</span> ${conflict.group}</p>
      <p><span class="strong-text">Matière:</span> ${conflict.subject}</p>
      <p><span class="strong-text">Salle:</span> ${conflict.room}</p>
    `;

    if (conflict.is_split) {
      if (conflict.split_type === "same_time") {
        html += `
          <div class="conflict-subsection">
            <p class="conflict-title conflict-title-info">Cours avec sous-groupes :</p>
            <p><span class="strong-text">Sous-groupe 1:</span> ${
              conflict.subgroup1 || ""
            }</p>
            <p><span class="strong-text">Sous-groupe 2:</span> ${
              conflict.subgroup2 || ""
            }</p>
            <p><span class="strong-text">Professeur 2:</span> ${
              conflict.professor2 || ""
            }</p>
            <p><span class="strong-text">Salle 2:</span> ${
              conflict.room2 || ""
            }</p>
          </div>
        `;
      } else if (conflict.split_type === "single_group") {
        html += `
          <div class="conflict-subsection">
            <p class="conflict-title conflict-title-info">Cours avec sous-groupe unique :</p>
            <p><span class="strong-text">Sous-groupe:</span> ${
              conflict.subgroup || ""
            }</p>
          </div>
        `;
      }
    }

    html += "</div>";
    return html;
  }
}
