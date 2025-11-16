// dropdownItems.js - Dropdown Item Event Handlers

export class DropdownItemHandlers {
  constructor(config, api, modal, toast, statusManager, loadDataCallback) {
    this.config = config;
    this.api = api;
    this.modal = modal;
    this.toast = toast;
    this.statusManager = statusManager;
    this.loadDataCallback = loadDataCallback;
  }

  setupAllHandlers() {
    this.setupYearSelection();
    this.setupGroupSelection();
    this.setupProfessorSelection();
    this.setupSubjectSelection();
    this.setupRoomSelection();
    this.setupCourseTypeSelection();
    this.setupSubgroupSelection();
    this.setupSecondProfessorSelection();
    this.setupSecondSubjectSelection();
    this.setupSecondRoomSelection();
  }

  setupYearSelection() {
    document.querySelectorAll("#year-menu .dropdown-item").forEach((item) => {
      item.addEventListener("click", () => {
        const year = item.getAttribute("data-value");

        if (year === this.config.currentYear) {
          document.getElementById("year-menu").classList.remove("open");
          document.getElementById("year-dropdown").classList.remove("active");
          return;
        }

        this.config.pendingDestination = { type: "year", year: year };

        if (this.config.hasUnsavedChanges) {
          this.modal.showUnsavedChangesWarning(async (shouldSave) => {
            if (shouldSave) {
              const success = await this.api.saveTimetable();
              if (!success) return;
            }
            this.switchYear(year);
          });
        } else {
          this.switchYear(year);
        }
      });
    });
  }

  switchYear(year) {
    document.getElementById("selected-year").textContent = year;
    this.config.currentYear = year;
    document.getElementById("year-menu").classList.remove("open");
    document.getElementById("year-dropdown").classList.remove("active");

    this.updateGroupDropdown(year);

    if (
      this.config.groupsByYear[year] &&
      this.config.groupsByYear[year].length > 0
    ) {
      this.config.currentGroup = this.config.groupsByYear[year][0];
      document.getElementById("selected-group").textContent =
        this.config.currentGroup;
    }

    this.saveCurrentSelection();

    if (this.loadDataCallback) this.loadDataCallback();
    this.toast.show(
      "info",
      `Affichage de l'emploi du temps pour ${this.config.currentYear}-${this.config.currentGroup}`
    );
  }

  updateGroupDropdown(year) {
    const groupMenu = document.getElementById("group-menu");
    groupMenu.innerHTML = "";

    if (this.config.groupsByYear[year]) {
      this.config.groupsByYear[year].forEach((group) => {
        const item = document.createElement("div");
        item.className = "dropdown-item";
        item.setAttribute("data-value", group);
        item.textContent = group;
        item.addEventListener("click", () => this.handleGroupClick(group));
        groupMenu.appendChild(item);
      });
    }
  }

  setupGroupSelection() {
    // Group selection is handled dynamically through updateGroupDropdown
  }

  handleGroupClick(selectedGroup) {
    if (selectedGroup === this.config.currentGroup) {
      document.getElementById("group-menu").classList.remove("open");
      document.getElementById("group-dropdown").classList.remove("active");
      return;
    }

    this.config.pendingDestination = {
      type: "group",
      year: this.config.currentYear,
      group: selectedGroup,
    };

    if (this.config.hasUnsavedChanges) {
      this.modal.showUnsavedChangesWarning(async (shouldSave) => {
        if (shouldSave) {
          const success = await this.api.saveTimetable();
          if (!success) return;
        }
        this.switchGroup(selectedGroup);
      });
    } else {
      this.switchGroup(selectedGroup);
    }
  }

  switchGroup(selectedGroup) {
    document.getElementById("selected-group").textContent = selectedGroup;
    this.config.currentGroup = selectedGroup;
    document.getElementById("group-menu").classList.remove("open");
    document.getElementById("group-dropdown").classList.remove("active");

    this.saveCurrentSelection();

    if (this.loadDataCallback) this.loadDataCallback();
    this.toast.show(
      "info",
      `Affichage de l'emploi du temps pour ${this.config.currentYear}-${this.config.currentGroup}`
    );
  }

  async saveCurrentSelection() {
    try {
      const params = new URLSearchParams({
        year: this.config.currentYear,
        group: this.config.currentGroup,
      });

      await fetch("../api/timetables/set_admin_selection.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: params.toString(),
      });
    } catch (error) {
      console.error("Failed to persist admin selection:", error);
    }
  }

  setupProfessorSelection() {
    document
      .querySelectorAll("#professor-list .dropdown-item")
      .forEach((item) => {
        item.addEventListener("click", () => {
          const professorName = item.getAttribute("data-value");
          const professorId = item.getAttribute("data-id");

          document.getElementById("selected-professor").textContent =
            professorName;
          document
            .getElementById("selected-professor")
            .setAttribute("data-id", professorId);
          document.getElementById("professor-menu").classList.remove("open");
          document
            .getElementById("professor-dropdown")
            .classList.remove("active");

          document
            .getElementById("subject-dropdown")
            .removeAttribute("disabled");
          document.getElementById("subject-dropdown").style.backgroundColor =
            "#ffffff";
          document.getElementById("subject-dropdown").style.cursor = "pointer";
          document.getElementById("selected-subject").textContent =
            "Chargement des matières...";

          this.filterSubjectsByProfessor(professorId, "subject");
        });
      });
  }

  async filterSubjectsByProfessor(professorId, dropdownType) {
    const suffix = dropdownType === "subject2" ? "-2" : "";
    const subjectMenu = document.getElementById(`subject-menu${suffix}`);
    const selectedSubject = document.getElementById(
      `selected-subject${suffix}`
    );
    const subjectDropdown = document.getElementById(
      `subject-dropdown${suffix}`
    );

    if (!professorId) {
      selectedSubject.textContent = "Sélectionner un professeur d'abord";
      subjectDropdown.setAttribute("disabled", "disabled");
      return;
    }

    subjectMenu.innerHTML =
      '<div class="dropdown-item" style="color: #888;">Chargement...</div>';

    try {
      const data = await this.api.fetchProfessorSubjects(professorId);

      if (data.success && data.subjects && data.subjects.length > 0) {
        subjectMenu.innerHTML = "";

        data.subjects.forEach((subject) => {
          const item = document.createElement("div");
          item.className = "dropdown-item";
          item.setAttribute("data-value", subject.name);
          item.setAttribute("data-id", subject.id);
          item.setAttribute("data-color", subject.color);

          const displayText = subject.code
            ? `${subject.name} (${subject.code})`
            : subject.name;
          item.textContent = displayText;

          item.addEventListener("click", () => {
            selectedSubject.textContent = subject.name;
            selectedSubject.setAttribute("data-id", subject.id);

            if (dropdownType === "subject") {
              document.getElementById("edit-color").value = subject.color;
            }

            subjectMenu.classList.remove("open");
            subjectDropdown.classList.remove("active");
          });

          subjectMenu.appendChild(item);
        });

        subjectDropdown.removeAttribute("disabled");
        selectedSubject.textContent = "Sélectionner une matière";
      } else {
        subjectMenu.innerHTML =
          '<div class="dropdown-item" style="color: #888;">Aucune matière assignée à ce professeur</div>';
        selectedSubject.textContent = "Aucune matière disponible";
        subjectDropdown.setAttribute("disabled", "disabled");
      }
    } catch (error) {
      console.error("Error fetching professor subjects:", error);
      selectedSubject.textContent = "Erreur lors du chargement des matières";
      subjectDropdown.setAttribute("disabled", "disabled");
      subjectMenu.innerHTML =
        '<div class="dropdown-item" style="color: #888;">Erreur lors du chargement des matières</div>';
    }
  }

  setupSubjectSelection() {
    document
      .querySelectorAll("#subject-menu .dropdown-item")
      .forEach((item) => {
        item.addEventListener("click", () => {
          const subject = item.getAttribute("data-value");
          const subjectId = item.getAttribute("data-id");
          const color = item.getAttribute("data-color");

          document.getElementById("selected-subject").textContent = subject;
          document
            .getElementById("selected-subject")
            .setAttribute("data-id", subjectId);
          document.getElementById("edit-color").value = color;
          document.getElementById("subject-menu").classList.remove("open");
          document
            .getElementById("subject-dropdown")
            .classList.remove("active");
        });
      });
  }

  setupRoomSelection() {
    document.querySelectorAll("#room-menu .dropdown-item").forEach((item) => {
      item.addEventListener("click", () => {
        const courseType = document.getElementById(
          "selected-course-type"
        ).textContent;
        const color = this.getCourseTypeColor(courseType);

        document.getElementById("selected-room").textContent =
          item.getAttribute("data-value");
        document.getElementById("room-menu").classList.remove("open");
        document.getElementById("room-dropdown").classList.remove("active");
        document.getElementById("edit-color").value = color;
      });
    });
  }

  setupCourseTypeSelection() {
    document
      .querySelectorAll("#course-type-menu .dropdown-item")
      .forEach((item) => {
        item.addEventListener("click", () => {
          const courseType = item.getAttribute("data-value");
          const color = item.getAttribute("data-color");

          document.getElementById("selected-course-type").textContent =
            courseType;
          document.getElementById("edit-color").value = color;
          document.getElementById("course-type-menu").classList.remove("open");
          document
            .getElementById("course-type-dropdown")
            .classList.remove("active");

          const subgroupOptions = document.getElementById("subgroup-options");
          if (courseType === "TD" || courseType === "TP") {
            subgroupOptions.classList.remove("hidden");
          } else {
            subgroupOptions.classList.add("hidden");
            document
              .getElementById("subgroup-split-options")
              .classList.add("hidden");
            document
              .getElementById("second-subgroup-options")
              .classList.add("hidden");
            document
              .getElementById("single-subgroup-selector")
              .classList.add("hidden");
          }
        });
      });
  }

  setupSubgroupSelection() {
    document
      .querySelectorAll("#subgroup-menu .dropdown-item")
      .forEach((item) => {
        item.addEventListener("click", () => {
          const subgroupNum = item.getAttribute("data-value");
          document.getElementById("selected-subgroup").textContent =
            "Sous-groupe " + subgroupNum;
          document.getElementById("subgroup-menu").classList.remove("open");
          document
            .getElementById("subgroup-dropdown")
            .classList.remove("active");
        });
      });
  }

  setupSecondProfessorSelection() {
    document
      .querySelectorAll("#professor-list-2 .dropdown-item")
      .forEach((item) => {
        item.addEventListener("click", () => {
          const professorName = item.getAttribute("data-value");
          const professorId = item.getAttribute("data-id");

          document.getElementById("selected-professor-2").textContent =
            professorName;
          document
            .getElementById("selected-professor-2")
            .setAttribute("data-id", professorId);
          document.getElementById("professor-menu-2").classList.remove("open");
          document
            .getElementById("professor-dropdown-2")
            .classList.remove("active");

          document
            .getElementById("subject-dropdown-2")
            .removeAttribute("disabled");
          document.getElementById("subject-dropdown-2").style.backgroundColor =
            "#ffffff";
          document.getElementById("subject-dropdown-2").style.cursor =
            "pointer";
          document.getElementById("selected-subject-2").textContent =
            "Chargement des matières...";

          this.filterSubjectsByProfessor(professorId, "subject2");
        });
      });
  }

  setupSecondSubjectSelection() {
    document
      .querySelectorAll("#subject-menu-2 .dropdown-item")
      .forEach((item) => {
        item.addEventListener("click", () => {
          const subject = item.getAttribute("data-value");
          const subjectId = item.getAttribute("data-id");

          document.getElementById("selected-subject-2").textContent = subject;
          document
            .getElementById("selected-subject-2")
            .setAttribute("data-id", subjectId);
          document.getElementById("subject-menu-2").classList.remove("open");
          document
            .getElementById("subject-dropdown-2")
            .classList.remove("active");
        });
      });
  }

  setupSecondRoomSelection() {
    document.querySelectorAll("#room-menu-2 .dropdown-item").forEach((item) => {
      item.addEventListener("click", () => {
        document.getElementById("selected-room-2").textContent =
          item.getAttribute("data-value");
        document.getElementById("room-menu-2").classList.remove("open");
        document.getElementById("room-dropdown-2").classList.remove("active");
      });
    });
  }

  getCourseTypeColor(courseType) {
    const colors = {
      CM: "#6b7280",
      TD: "#10b981",
      TP: "#3b82f6",
      DE: "#f59e0b",
      CO: "#ef4444",
    };
    return colors[courseType] || "#6b7280";
  }
}
