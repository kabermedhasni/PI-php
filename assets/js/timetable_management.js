document.addEventListener("DOMContentLoaded", function () {
  const root = document.getElementById("timetable-root");
  if (!root) return;
  // Read config safely from data attribute (JSON was escaped server-side with htmlspecialchars)
  let cfg = {};
  try {
    const raw = root.getAttribute("data-config") || "{}";
    cfg = JSON.parse(raw);
  } catch (e) {
    console.error("Invalid admin timetable config JSON:", e);
    cfg = {};
  }
  // Setup radio button animations - reset animation on each click
  document.addEventListener("click", function (e) {
    if (e.target.type === "radio") {
      e.target.style.animation = "none";
      e.target.offsetHeight; // Force reflow
      e.target.style.animation = "";
    }
  });

  // Global variables and utility functions
  let timetableData = {};
  let currentYear = cfg.currentYear;
  let currentGroup = cfg.currentGroup;
  let groupsByYear = cfg.groupsByYear || {};
  let hasUnsavedChanges = false;
  let isCurrentlyPublished = false;
  let hasDraftChanges = false;
  let pendingDestination = null;
  let deleteClassDay = null;
  let deleteClassTime = null;

  let dragSourceDay = null;
  let dragSourceTime = null;
  let dragDestinationDay = null;
  let dragDestinationTime = null;

  const timeSlots = cfg.timeSlots || [];
  const days = cfg.days || [];

  // Clean selection parameters from the visible URL while keeping them
  // available in PHP/JS config (timetable still loads correctly).
  (function cleanAdminYearGroupFromUrl() {
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
  })();

  // Consolidated modal animation functions (aligned with admin_index.js behavior)
  window.showModalWithAnimation = function (modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    modal.classList.remove("hidden");
    modal.classList.remove("fade-out");

    // Force reflow
    void modal.offsetWidth;

    modal.classList.add("fade-in");
  };

  window.closeModalWithAnimation = function (modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    modal.classList.remove("fade-in");
    modal.classList.add("fade-out");

    // Hide after animation completes
    setTimeout(function () {
      modal.classList.add("hidden");
      modal.classList.remove("fade-out");
    }, 300);
  };

  // Apply animations and handlers to all modals
  document.querySelectorAll(".modal").forEach((modal) => {
    // Ensure modals start hidden and without animation classes
    modal.classList.add("hidden");
    modal.classList.remove("fade-in", "fade-out");

    const closeBtn = modal.querySelector(".close");
    if (closeBtn && modal.id) {
      closeBtn.addEventListener("click", function () {
        closeModalWithAnimation(modal.id);
      });
    }

    // Close on click outside
    if (modal.id) {
      modal.addEventListener("click", function (e) {
        if (e.target === modal) {
          closeModalWithAnimation(modal.id);
        }
      });
    }
  });

  // Replace modal button handlers with consolidated approach
  document.getElementById("cancel-btn").addEventListener("click", function () {
    closeModalWithAnimation("class-modal");
  });

  // Move class confirmation modal handlers
  const moveClassModal = document.getElementById("move-class-modal");
  if (moveClassModal) {
    const moveClose = document.getElementById("move-class-close");
    const moveCancel = document.getElementById("move-class-cancel");
    const moveConfirm = document.getElementById("move-class-confirm");

    if (moveClose) {
      moveClose.addEventListener("click", function () {
        closeModalWithAnimation("move-class-modal");
        resetDragState();
      });
    }

    if (moveCancel) {
      moveCancel.addEventListener("click", function () {
        closeModalWithAnimation("move-class-modal");
        resetDragState();
      });
    }

    if (moveConfirm) {
      moveConfirm.addEventListener("click", function () {
        closeModalWithAnimation("move-class-modal");
        applyMoveOrSwap();
      });
    }
  }

  // Consolidated dropdown handling function
  function toggleDropdown(dropdownButton, dropdownMenu) {
    if (dropdownButton.hasAttribute("disabled")) return false;

    if (dropdownMenu.classList.contains("open")) {
      // Closing the dropdown
      dropdownButton.classList.remove("active");
      dropdownMenu.classList.remove("open");
      dropdownMenu.classList.add("closing");

      setTimeout(() => {
        dropdownMenu.classList.remove("closing");
        dropdownMenu.style.display = "none";
      }, 300);

      return false;
    } else {
      // Opening the dropdown
      closeAllDropdowns(); // Close any other open dropdowns
      dropdownButton.classList.add("active");
      dropdownMenu.style.display = "block";

      // Always position dropdown below the button
      dropdownMenu.style.top = "calc(100% + 4px)";
      dropdownMenu.style.bottom = "auto";

      void dropdownMenu.offsetWidth; // Force reflow
      dropdownMenu.classList.add("open");

      // Ensure dropdown is visible within modal
      setTimeout(() => {
        ensureDropdownVisible(dropdownButton, dropdownMenu);
      }, 50);

      return true;
    }
  }

  // Function to ensure dropdown is visible within modal by scrolling if necessary
  function ensureDropdownVisible(button, dropdown) {
    const modalContent = document.querySelector(".modal-content");
    if (!modalContent) return;

    const modalRect = modalContent.getBoundingClientRect();
    const buttonRect = button.getBoundingClientRect();
    const dropdownHeight = dropdown.offsetHeight;

    // Calculate if dropdown would extend beyond modal bottom
    const dropdownBottom = buttonRect.bottom + dropdownHeight - modalRect.top;
    const modalHeight = modalContent.offsetHeight;

    if (dropdownBottom > modalHeight) {
      // Dropdown extends beyond modal bottom, scroll to make it visible
      const scrollAmount = dropdownBottom - modalHeight + 20; // Add 20px padding
      modalContent.scrollTop += scrollAmount;
    }
  }

  function closeAllDropdowns() {
    document.querySelectorAll(".dropdown-menu.open").forEach((menu) => {
      const button = menu.parentElement.querySelector(".dropdown-button");
      button.classList.remove("active");
      menu.classList.remove("open");
      menu.classList.add("closing");

      setTimeout(() => {
        menu.classList.remove("closing");
        menu.style.display = "none";
      }, 300);
    });
  }

  // Core data management functions
  function initTimetableData() {
    // Create a fresh object
    timetableData = {};

    // Initialize each day and time slot
    days.forEach((day) => {
      timetableData[day] = {};
      timeSlots.forEach((time) => {
        timetableData[day][time] = null;
      });
    });

    hasUnsavedChanges = false;
    isCurrentlyPublished = false;
    hasDraftChanges = false;
  }

  // Status management functions
  function updatePublishStatus() {
    const statusDiv = document.getElementById("status-message");
    statusDiv.classList.remove(
      "hidden",
      "status-success",
      "status-warning",
      "status-info"
    );

    // Check if timetable is completely empty
    let isEmptyTimetable = true;
    for (const day in timetableData) {
      for (const time in timetableData[day]) {
        if (timetableData[day][time] !== null) {
          isEmptyTimetable = false;
          break;
        }
      }
      if (!isEmptyTimetable) break;
    }

    // Hide status message for completely empty timetables that haven't been saved yet
    if (
      isEmptyTimetable &&
      !hasUnsavedChanges &&
      !hasDraftChanges &&
      !isCurrentlyPublished
    ) {
      statusDiv.classList.add("hidden");
      return;
    }

    if (hasUnsavedChanges) {
      statusDiv.classList.add("status-warning");
      statusDiv.textContent =
        "Vous avez des modifications non enregistrées. N'oubliez pas d'enregistrer avant de quitter !";
      statusDiv.classList.remove("hidden");
    } else if (hasDraftChanges && isCurrentlyPublished) {
      statusDiv.classList.add("status-info");
      statusDiv.textContent =
        "Vous avez des modifications enregistrées qui ne sont pas encore publiées. Les étudiants et professeurs voient toujours la version précédemment publiée.";
      statusDiv.classList.remove("hidden");
    } else if (isCurrentlyPublished) {
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

  // Show warning for unsaved changes
  function showUnsavedChangesWarning(callback) {
    const modal = document.getElementById("unsaved-changes-modal");
    showModalWithAnimation("unsaved-changes-modal");

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
    newCloseBtn.addEventListener("click", function () {
      closeModalWithAnimation("unsaved-changes-modal");
    });

    newDiscardBtn.addEventListener("click", function () {
      closeModalWithAnimation("unsaved-changes-modal");
      hasUnsavedChanges = false;
      hasDraftChanges = false;
      if (callback) callback(false); // Continue without saving
    });

    newSaveBtn.addEventListener("click", function () {
      closeModalWithAnimation("unsaved-changes-modal");
      saveCurrentTimetable(function () {
        if (callback) callback(true); // Continue after saving
      });
    });
  }

  // Initialize and display the timetable
  function generateEmptyTimetable() {
    const tbody = document.getElementById("timetable-body");
    tbody.innerHTML = "";

    timeSlots.forEach((time) => {
      const row = document.createElement("tr");

      // Time cell
      const timeCell = document.createElement("td");
      timeCell.className = "time-cell";
      timeCell.textContent = time;
      row.appendChild(timeCell);

      // Day cells
      days.forEach((day) => {
        const cell = document.createElement("td");
        cell.className = "subject-cell";
        cell.setAttribute("data-day", day);
        cell.setAttribute("data-time", time);

        // Check if we have data for this cell
        if (timetableData[day] && timetableData[day][time]) {
          const data = timetableData[day][time];

          const classBlock = document.createElement("div");
          classBlock.className = "class-block";

          // Determine color based on class_type if available
          let color;
          if (data.class_type) {
            switch (data.class_type) {
              case "CM":
                color = "#6b7280";
                break;
              case "TD":
                color = "#10b981";
                break;
              case "TP":
                color = "#3b82f6";
                break;
              case "DE":
                color = "#f59e0b";
                break;
              case "CO":
                color = "#ef4444";
                break;
              default:
                color = data.color || "#6b7280"; // Fallback to data.color or default grey
            }
          } else {
            // Use the color from data if available, otherwise use default grey
            color = data.color || "#6b7280";
          }

          classBlock.style.borderLeftColor = color;

          // Apply visual indicators if class is canceled or rescheduled
          if (data.is_canceled == 1) {
            classBlock.style.backgroundColor = "#FEE2E2"; // Light red background
          } else if (data.is_reschedule == 1) {
            classBlock.style.backgroundColor = "#DBEAFE"; // Light blue background
          }

          const subjectDiv = document.createElement("div");
          subjectDiv.className = "class-subject";
          subjectDiv.textContent = data.subject;
          subjectDiv.style.color = color; // Make subject name same color as course type

          // Add a small indicator for the class type if available
          if (data.class_type) {
            const typeSpan = document.createElement("span");
            typeSpan.className = "class-type";
            typeSpan.textContent = `(${data.class_type})`;
            typeSpan.style.color = color;
            subjectDiv.appendChild(typeSpan);
          }

          const professorDiv = document.createElement("div");
          professorDiv.className = "class-professor";
          professorDiv.textContent = data.professor;

          const roomDiv = document.createElement("div");
          roomDiv.className = "class-room";
          roomDiv.textContent = `Salle: ${data.room}`;

          const actionDiv = document.createElement("div");
          actionDiv.className = "class-actions";

          const editBtn = document.createElement("button");
          editBtn.className = "btn-link btn-edit";
          editBtn.textContent = "Modifier";
          editBtn.addEventListener("click", function () {
            openEditModal(day, time);
          });

          const deleteBtn = document.createElement("button");
          deleteBtn.className = "btn-link btn-delete";
          deleteBtn.textContent = "Supprimer";
          deleteBtn.addEventListener("click", function () {
            // Store the day and time for the class to delete
            deleteClassDay = day;
            deleteClassTime = time;

            // Set the class name in the modal
            document.getElementById("delete-class-name").textContent =
              data.subject;

            // Show the delete confirmation modal
            showModalWithAnimation("delete-class-modal");
          });

          actionDiv.appendChild(editBtn);
          actionDiv.appendChild(deleteBtn);

          // Handle subgroup display if applicable
          if (
            (data.class_type === "TD" || data.class_type === "TP") &&
            data.is_split
          ) {
            // Add a split class indicator
            classBlock.style.borderTop = "2px dashed " + color;

            if (data.split_type === "same_time") {
              // Display both subgroups in same time slot
              const subgroupDiv = document.createElement("div");
              subgroupDiv.className = "class-subgroup";
              subgroupDiv.textContent = `${data.subgroup1}/${data.subgroup2}`;

              // Update subject display to show both subjects if they're different
              if (data.subject2 && data.subject !== data.subject2) {
                subjectDiv.textContent = `${data.subject}/${data.subject2}`;

                // Add a tooltip to show full subject names
                subjectDiv.title = `${data.subject} / ${data.subject2}`;
              }

              // Update professor display to show both professors
              professorDiv.textContent = `${data.professor}/${data.professor2}`;
              professorDiv.title = `${data.professor} / ${data.professor2}`;

              // Update room display to show both rooms
              roomDiv.textContent = `Salle: ${data.room}/${data.room2}`;
              roomDiv.title = `${data.room} / ${data.room2}`;

              // Add subgroup div after subject
              classBlock.appendChild(subjectDiv);
              classBlock.appendChild(subgroupDiv);
              classBlock.appendChild(professorDiv);
              classBlock.appendChild(roomDiv);
            } else if (data.split_type === "single_group") {
              // Display single subgroup
              const subgroupDiv = document.createElement("div");
              subgroupDiv.className = "class-subgroup";

              // Make sure we're using the correct subgroup property and provide a fallback
              if (data.subgroup) {
                subgroupDiv.textContent = data.subgroup;
              } else {
                // Recreate subgroup name from group if missing
                const groupNumber = currentGroup.replace("G", "");
                const subgroupNumber = parseInt(groupNumber) * 2 - 1; // Default to first subgroup
                subgroupDiv.textContent = data.class_type + subgroupNumber;

                console.log(
                  "Recreated missing subgroup name:",
                  subgroupDiv.textContent
                );
              }

              // Add subgroup div after subject
              classBlock.appendChild(subjectDiv);
              classBlock.appendChild(subgroupDiv);
              classBlock.appendChild(professorDiv);
              classBlock.appendChild(roomDiv);
            }
          } else {
            // Standard display without subgroups
            classBlock.appendChild(subjectDiv);
            classBlock.appendChild(professorDiv);
            classBlock.appendChild(roomDiv);
          }

          // Add status indicators if class is canceled or rescheduled
          if (data.is_canceled == 1 || data.is_reschedule == 1) {
            const statusDiv = document.createElement("div");
            if (data.is_canceled == 1) {
              statusDiv.className = "class-status class-status-canceled";
              statusDiv.textContent = "ANNULÉ PAR LE PROFESSEUR";
            } else if (data.is_reschedule == 1) {
              statusDiv.className = "class-status class-status-reschedule";
              statusDiv.textContent = "DEMANDE DE REPORT";
            }
            classBlock.appendChild(statusDiv);
          }

          classBlock.appendChild(actionDiv);

          cell.appendChild(classBlock);
        } else {
          // Empty cell with add button
          const emptyCell = document.createElement("div");
          emptyCell.className = "empty-cell";
          emptyCell.innerHTML = `
                            <button class="btn-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon-lg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </button>
                        `;
          emptyCell.addEventListener("click", function () {
            openAddModal(day, time);
          });

          cell.appendChild(emptyCell);
        }

        row.appendChild(cell);
      });

      tbody.appendChild(row);
    });

    attachDragHandlers();
  }

  function attachDragHandlers() {
    const cells = document.querySelectorAll("#timetable-body .subject-cell");

    cells.forEach((cell) => {
      const day = cell.getAttribute("data-day");
      const time = cell.getAttribute("data-time");
      const classBlock = cell.querySelector(".class-block");

      if (classBlock) {
        classBlock.setAttribute("draggable", "true");

        classBlock.addEventListener("dragstart", function (e) {
          dragSourceDay = day;
          dragSourceTime = time;
          dragDestinationDay = null;
          dragDestinationTime = null;

          this.classList.add("dragging");

          if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = "move";
            e.dataTransfer.setData("text/plain", `${day}-${time}`);
          }
        });

        classBlock.addEventListener("dragend", function () {
          this.classList.remove("dragging");
          document
            .querySelectorAll(".subject-cell.drop-target")
            .forEach((c) => c.classList.remove("drop-target"));
        });
      }

      cell.addEventListener("dragover", function (e) {
        if (!dragSourceDay) return;
        e.preventDefault();
        if (e.dataTransfer) {
          e.dataTransfer.dropEffect = "move";
        }
        this.classList.add("drop-target");
      });

      cell.addEventListener("dragleave", function () {
        this.classList.remove("drop-target");
      });

      cell.addEventListener("drop", function (e) {
        if (!dragSourceDay) return;
        e.preventDefault();

        document
          .querySelectorAll(".subject-cell.drop-target")
          .forEach((c) => c.classList.remove("drop-target"));

        const destDay = this.getAttribute("data-day");
        const destTime = this.getAttribute("data-time");

        dragDestinationDay = destDay;
        dragDestinationTime = destTime;

        if (
          dragDestinationDay === dragSourceDay &&
          dragDestinationTime === dragSourceTime
        ) {
          resetDragState();
          return;
        }

        const sourceData =
          timetableData[dragSourceDay] &&
          timetableData[dragSourceDay][dragSourceTime];

        if (!sourceData) {
          resetDragState();
          return;
        }

        const destData =
          timetableData[dragDestinationDay] &&
          timetableData[dragDestinationDay][dragDestinationTime];

        const moveMessageEl = document.getElementById("move-class-message");
        if (moveMessageEl) {
          const destLabel = `${dragDestinationDay} ${dragDestinationTime}`;

          if (destData) {
            const sourceLabel = `${dragSourceDay} ${dragSourceTime}`;
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

          showModalWithAnimation("move-class-modal");
        } else {
          applyMoveOrSwap();
        }
      });
    });
  }

  function resetDragState() {
    dragSourceDay = null;
    dragSourceTime = null;
    dragDestinationDay = null;
    dragDestinationTime = null;
  }

  function applyMoveOrSwap() {
    if (!dragSourceDay || !dragDestinationDay) {
      resetDragState();
      return;
    }

    const sourceData =
      timetableData[dragSourceDay] &&
      timetableData[dragSourceDay][dragSourceTime];
    const destData =
      timetableData[dragDestinationDay] &&
      timetableData[dragDestinationDay][dragDestinationTime];

    if (!sourceData) {
      resetDragState();
      return;
    }

    if (destData) {
      // Swap classes between the two cells
      timetableData[dragSourceDay][dragSourceTime] = destData;
      timetableData[dragDestinationDay][dragDestinationTime] = sourceData;
    } else {
      // Move class to an empty cell
      timetableData[dragSourceDay][dragSourceTime] = null;
      timetableData[dragDestinationDay][dragDestinationTime] = sourceData;
    }

    hasUnsavedChanges = true;
    updatePublishStatus();
    generateEmptyTimetable();
    showToast(
      "success",
      "Cours déplacé. N'oubliez pas d'enregistrer les modifications."
    );

    resetDragState();
  }

  // Set up initial state
  initTimetableData();
  generateEmptyTimetable();

  // Toast notification handling
  function createToastElement() {
    if (document.getElementById("toast-notification")) return;

    const toast = document.createElement("div");
    toast.id = "toast-notification";
    toast.className = "toast";
    document.body.appendChild(toast);
  }
  createToastElement();

  function showToast(type, message) {
    const toast = document.getElementById("toast-notification");
    toast.textContent = message;
    toast.className = "toast";

    if (type === "success") {
      toast.classList.add("toast-success");
    } else if (type === "error") {
      toast.classList.add("toast-error");
    } else {
      toast.classList.add("toast-info");
    }

    toast.classList.add("show");

    setTimeout(() => {
      toast.classList.remove("show");
    }, 3000);
  }

  // Setup event listeners for all dropdowns
  const dropdowns = [
    { button: "year-dropdown", menu: "year-menu" },
    { button: "group-dropdown", menu: "group-menu" },
    { button: "professor-dropdown", menu: "professor-menu" },
    { button: "subject-dropdown", menu: "subject-menu" },
    { button: "room-dropdown", menu: "room-menu" },
    { button: "course-type-dropdown", menu: "course-type-menu" },
    { button: "professor-dropdown-2", menu: "professor-menu-2" },
    { button: "subject-dropdown-2", menu: "subject-menu-2" },
    { button: "room-dropdown-2", menu: "room-menu-2" },
    { button: "subgroup-dropdown", menu: "subgroup-menu" },
  ];

  dropdowns.forEach((dropdown) => {
    const button = document.getElementById(dropdown.button);
    const menu = document.getElementById(dropdown.menu);

    if (button && menu) {
      button.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        toggleDropdown(this, menu);
      });
    }
  });

  // Close dropdowns when clicking outside
  document.addEventListener("click", function (event) {
    closeAllDropdowns();
  });

  // Handle professor search
  const professorSearch = document.getElementById("professor-search");
  if (professorSearch) {
    professorSearch.addEventListener("input", function (e) {
      e.stopPropagation();
      const searchTerm = this.value.toLowerCase().trim();
      const professorItems = document.querySelectorAll(
        "#professor-list .dropdown-item"
      );

      professorItems.forEach((item) => {
        const professorName = item.getAttribute("data-value").toLowerCase();
        if (searchTerm === "" || professorName.includes(searchTerm)) {
          item.style.display = "block";
        } else {
          item.style.display = "none";
        }
      });
    });

    // Prevent dropdown from closing when clicking in the search input
    professorSearch.addEventListener("click", function (e) {
      e.stopPropagation();
    });
  }

  // Setup handlers for dropdown items
  function setupDropdownItemHandlers() {
    // Year selection
    document.querySelectorAll("#year-menu .dropdown-item").forEach((item) => {
      item.addEventListener("click", function () {
        const year = this.getAttribute("data-value");

        // Skip if selecting the same year
        if (year === currentYear) {
          document.getElementById("year-menu").classList.remove("open");
          document.getElementById("year-dropdown").classList.remove("active");
          return;
        }

        // Store the destination
        pendingDestination = { type: "year", year: year };

        // If there are unsaved changes, show warning
        if (hasUnsavedChanges) {
          showUnsavedChangesWarning(function (saved) {
            // After user's decision, switch to the year
            document.getElementById("selected-year").textContent = year;
            currentYear = year;
            document.getElementById("year-menu").classList.remove("open");
            document.getElementById("year-dropdown").classList.remove("active");

            // Update the group dropdown with year-specific groups
            updateGroupDropdown(year);

            // Reset to first group in the list
            if (groupsByYear[year] && groupsByYear[year].length > 0) {
              currentGroup = groupsByYear[year][0];
              document.getElementById("selected-group").textContent =
                currentGroup;
            }

            // Load timetable for this year/group
            loadSavedData();
            showToast(
              "info",
              `Affichage de l'emploi du temps pour ${currentYear}-${currentGroup}`
            );
          });
        } else {
          // No changes, just update and load
          document.getElementById("selected-year").textContent = year;
          currentYear = year;
          document.getElementById("year-menu").classList.remove("open");
          document.getElementById("year-dropdown").classList.remove("active");

          // Update the group dropdown with year-specific groups
          updateGroupDropdown(year);

          // Reset to first group in the list
          if (groupsByYear[year] && groupsByYear[year].length > 0) {
            currentGroup = groupsByYear[year][0];
            document.getElementById("selected-group").textContent =
              currentGroup;
          }

          // Load timetable for this year/group
          loadSavedData();
          showToast(
            "info",
            `Affichage de l'emploi du temps pour ${currentYear}-${currentGroup}`
          );
        }
      });
    });

    // Setup professor dropdown items
    document
      .querySelectorAll("#professor-list .dropdown-item")
      .forEach((item) => {
        item.addEventListener("click", function () {
          const professorName = this.getAttribute("data-value");
          const professorId = this.getAttribute("data-id");

          document.getElementById("selected-professor").textContent =
            professorName;
          document
            .getElementById("selected-professor")
            .setAttribute("data-id", professorId);
          document.getElementById("professor-menu").classList.remove("open");
          document
            .getElementById("professor-dropdown")
            .classList.remove("active");

          // Enable subject dropdown now that a professor is selected
          document
            .getElementById("subject-dropdown")
            .removeAttribute("disabled");
          document.getElementById("subject-dropdown").style.backgroundColor =
            "#ffffff";
          document.getElementById("subject-dropdown").style.cursor = "pointer";
          document.getElementById("selected-subject").textContent =
            "Chargement des matières...";

          // Filter subjects based on selected professor
          filterSubjectsByProfessor(professorId);
        });
      });

    // Setup subject dropdown items
    document
      .querySelectorAll("#subject-menu .dropdown-item")
      .forEach((item) => {
        item.addEventListener("click", function () {
          const subject = this.getAttribute("data-value");
          const subjectId = this.getAttribute("data-id") || null;
          const color = this.getAttribute("data-color");

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

    // Setup room dropdown items
    document.querySelectorAll("#room-menu .dropdown-item").forEach((item) => {
      item.addEventListener("click", function () {
        // Get the current course type and its corresponding color
        const courseType = document.getElementById(
          "selected-course-type"
        ).textContent;
        let color;

        // Map course types to their colors
        switch (courseType) {
          case "CM":
            color = "#6b7280";
            break;
          case "TD":
            color = "#10b981";
            break;
          case "TP":
            color = "#3b82f6";
            break;
          case "DE":
            color = "#f59e0b";
            break;
          case "CO":
            color = "#ef4444";
            break;
          default:
            color = "#6b7280"; // Default to CM color
        }

        document.getElementById("selected-room").textContent =
          this.getAttribute("data-value");
        document.getElementById("room-menu").classList.remove("open");
        document.getElementById("room-dropdown").classList.remove("active");

        // Set the color based on the current course type
        document.getElementById("edit-color").value = color;
      });
    });

    // Setup course type dropdown items
    document
      .querySelectorAll("#course-type-menu .dropdown-item")
      .forEach((item) => {
        item.addEventListener("click", function () {
          const courseType = this.getAttribute("data-value");
          const color = this.getAttribute("data-color");

          document.getElementById("selected-course-type").textContent =
            courseType;
          document.getElementById("edit-color").value = color;

          document.getElementById("course-type-menu").classList.remove("open");
          document
            .getElementById("course-type-dropdown")
            .classList.remove("active");

          // Show/hide subgroup options based on course type
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

  setupDropdownItemHandlers();

  // Function to update group dropdown based on selected year
  function updateGroupDropdown(year) {
    const groupMenu = document.getElementById("group-menu");
    groupMenu.innerHTML = "";

    if (groupsByYear[year]) {
      groupsByYear[year].forEach((group) => {
        const item = document.createElement("div");
        item.className = "dropdown-item";
        item.setAttribute("data-value", group);
        item.textContent = group;
        item.addEventListener("click", function () {
          const selectedGroup = this.getAttribute("data-value");

          // Skip if selecting the same group
          if (selectedGroup === currentGroup) {
            groupMenu.classList.remove("open");
            document
              .getElementById("group-dropdown")
              .classList.remove("active");
            return;
          }

          // Store the destination
          pendingDestination = {
            type: "group",
            year: currentYear,
            group: selectedGroup,
          };

          // If there are unsaved changes, show warning
          if (hasUnsavedChanges) {
            showUnsavedChangesWarning(function (saved) {
              // After user's decision, switch to the group
              document.getElementById("selected-group").textContent =
                selectedGroup;
              currentGroup = selectedGroup;
              groupMenu.classList.remove("open");
              document
                .getElementById("group-dropdown")
                .classList.remove("active");

              // Load timetable for this year/group
              loadSavedData();
              showToast(
                "info",
                `Affichage de l'emploi du temps pour ${currentYear}-${currentGroup}`
              );
            });
          } else {
            // No changes, just update and load
            document.getElementById("selected-group").textContent =
              selectedGroup;
            currentGroup = selectedGroup;
            groupMenu.classList.remove("open");
            document
              .getElementById("group-dropdown")
              .classList.remove("active");

            // Load timetable for this year/group
            loadSavedData();
            showToast(
              "info",
              `Affichage de l'emploi du temps pour ${currentYear}-${currentGroup}`
            );
          }
        });
        groupMenu.appendChild(item);
      });
    }
  }

  // Initialize group dropdown with current year's groups on page load
  updateGroupDropdown(currentYear);

  // Setup handlers for all action buttons
  document.getElementById("save-btn").addEventListener("click", function () {
    saveCurrentTimetable();
  });

  document.getElementById("publish-btn").addEventListener("click", function () {
    publishCurrentTimetable();
  });

  document
    .getElementById("delete-timetable-btn")
    .addEventListener("click", function () {
      // Show confirmation modal with current year and group
      document.getElementById("delete-year-group").textContent =
        currentYear + " - " + currentGroup;
      showModalWithAnimation("delete-timetable-modal");
    });

  // Delete timetable modal handlers
  document
    .getElementById("delete-timetable-close")
    .addEventListener("click", function () {
      closeModalWithAnimation("delete-timetable-modal");
    });

  document
    .getElementById("delete-timetable-cancel")
    .addEventListener("click", function () {
      closeModalWithAnimation("delete-timetable-modal");
    });

  document
    .getElementById("delete-timetable-confirm")
    .addEventListener("click", function () {
      // Create payload for delete operation
      const payload = {
        year: currentYear,
        group: currentGroup,
      };

      // Send delete request to server
      fetch("../api/timetables/delete_timetable.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      })
        .then((response) => response.json())
        .then((data) => {
          closeModalWithAnimation("delete-timetable-modal");
          if (data.success) {
            // Reset timetable
            initTimetableData();
            generateEmptyTimetable();
            hasUnsavedChanges = false;
            isCurrentlyPublished = false;
            hasDraftChanges = false;
            updatePublishStatus();
            showToast(
              "success",
              `Emploi du temps supprimé pour ${currentYear}-${currentGroup}`
            );
          } else {
            showToast(
              "error",
              data.message ||
                "Erreur lors de la suppression de l'emploi du temps"
            );
          }
        })
        .catch((error) => {
          closeModalWithAnimation("delete-timetable-modal");
          console.error("Error deleting timetable:", error);
          showToast(
            "error",
            "Erreur lors de la suppression de l'emploi du temps"
          );
        });
    });

  // ...Remove the publish-all-btn event listener and function...

  // Function to save current timetable
  function saveCurrentTimetable(callback) {
    // Create a deep copy of the timetable data to avoid reference issues
    const timetableDataCopy = JSON.parse(JSON.stringify(timetableData || {}));

    // Create a payload for server - excluding any publish flags
    const payload = {
      year: currentYear,
      group: currentGroup,
      data: timetableDataCopy,
      action: "save_only", // Explicit action
    };

    console.log("Saving timetable with payload:", payload);

    // Send data to PHP backend
    fetch("../api/timetables/save_timetable.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        console.log("Server response:", data);
        if (data.success) {
          showToast(
            "success",
            `Emploi du temps enregistré pour ${currentYear}-${currentGroup}`
          );
          hasUnsavedChanges = false;

          // Check if the server tells us this is already published elsewhere
          if (data.is_published) {
            isCurrentlyPublished = true;
            hasDraftChanges = true;
          } else {
            isCurrentlyPublished = false;
            hasDraftChanges = false;
          }

          updatePublishStatus();
          if (callback) callback();
        } else {
          console.error("Save failed:", data);
          showToast(
            "error",
            data.message || "Échec de l'enregistrement de l'emploi du temps"
          );
          if (callback) callback();
        }
      })
      .catch((error) => {
        console.error("Error saving timetable:", error);
        showToast(
          "error",
          "Erreur lors de l'enregistrement de l'emploi du temps"
        );
        if (callback) callback();
      });
  }

  // Function to publish current timetable
  function publishCurrentTimetable() {
    // Create a payload specifically for publishing
    const timetableDataCopy = JSON.parse(JSON.stringify(timetableData || {}));

    const payload = {
      year: currentYear,
      group: currentGroup,
      data: timetableDataCopy,
      action: "publish", // Explicit action
    };

    console.log("Publishing timetable with payload:", payload);

    // Send data to PHP backend
    fetch("../api/timetables/publish_timetable.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        console.log("Server response:", data);
        if (data.success) {
          showToast(
            "success",
            `Emploi du temps publié pour ${currentYear}-${currentGroup}`
          );
          hasUnsavedChanges = false;
          isCurrentlyPublished = true;
          hasDraftChanges = false; // Reset draft changes flag since we just published
          updatePublishStatus();
        } else {
          console.error("Publish failed:", data);
          showToast(
            "error",
            data.message || "Échec de la publication de l'emploi du temps"
          );
        }
      })
      .catch((error) => {
        console.error("Error publishing timetable:", error);
        showToast(
          "error",
          "Erreur lors de la publication de l'emploi du temps"
        );
      });
  }

  // Function to publish all timetables
  function performPublishAllTimetables() {
    // Send request to publish all timetables
    fetch("../api/timetables/publish_all_timetables.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          showToast("success", data.message);
          // If current timetable was in the published list, update its status
          if (data.published.includes(currentYear + "-" + currentGroup)) {
            isCurrentlyPublished = true;
            hasDraftChanges = false;
            updatePublishStatus();
          }
        } else {
          showToast(
            "error",
            data.message ||
              "Échec de la publication de tous les emplois du temps"
          );
        }
      })
      .catch((error) => {
        console.error("Error publishing all timetables:", error);
        showToast(
          "error",
          "Erreur lors de la publication de tous les emplois du temps"
        );
      });
  }

  // Modal handling for class add/edit
  function openAddModal(day, time) {
    document.getElementById("modal-title").textContent = "Ajouter un Cours";
    document.getElementById("edit-day").value = day;
    document.getElementById("edit-time").value = time;
    document.getElementById("edit-id").value = ""; // New class

    // Reset dropdowns
    document.getElementById("selected-professor").textContent =
      "Sélectionner un professeur";
    document.getElementById("selected-professor").removeAttribute("data-id");
    document.getElementById("selected-subject").textContent =
      "Sélectionner un professeur d'abord";
    document.getElementById("selected-room").textContent =
      "Sélectionner une salle";
    document.getElementById("selected-course-type").textContent = "CM";
    document.getElementById("edit-color").value = "#6b7280"; // Default grey color for CM

    // Reset subgroup options
    document.getElementById("subgroup-options").classList.add("hidden");
    document.getElementById("subgroup-split-options").classList.add("hidden");
    document.getElementById("second-subgroup-options").classList.add("hidden");
    document.getElementById("single-subgroup-selector").classList.add("hidden");
    document.getElementById("subgroup-single").checked = true;

    // Reset second professor dropdown
    document.getElementById("selected-professor-2").textContent =
      "Sélectionner un professeur";
    document.getElementById("selected-professor-2").removeAttribute("data-id");

    // Reset second subject dropdown
    document.getElementById("selected-subject-2").textContent =
      "Sélectionner un professeur d'abord";
    document
      .getElementById("subject-dropdown-2")
      .setAttribute("disabled", "disabled");
    document.getElementById("subject-dropdown-2").style.backgroundColor =
      "#f1f5f9";
    document.getElementById("subject-dropdown-2").style.cursor = "not-allowed";
    document.getElementById("selected-subject-2").removeAttribute("data-id");

    // Reset second room dropdown
    document.getElementById("selected-room-2").textContent =
      "Sélectionner une salle";

    // Reset subgroup selector
    document.getElementById("selected-subgroup").textContent = "Sous-groupe 1";

    // Disable subject dropdown until professor is selected
    const subjectDropdown = document.getElementById("subject-dropdown");
    subjectDropdown.setAttribute("disabled", "disabled");
    subjectDropdown.style.backgroundColor = "#f1f5f9";
    subjectDropdown.style.cursor = "not-allowed";

    showModalWithAnimation("class-modal");
  }

  function openEditModal(day, time) {
    const data = timetableData[day][time];
    if (!data) return;

    document.getElementById("modal-title").textContent = "Modifier un Cours";
    document.getElementById("edit-day").value = day;
    document.getElementById("edit-time").value = time;
    document.getElementById("edit-id").value = data.id || "";
    document.getElementById("edit-color").value = data.color;

    // Fill form with existing data
    document.getElementById("selected-professor").textContent =
      data.professor || "Sélectionner un professeur";
    if (data.professor_id) {
      document
        .getElementById("selected-professor")
        .setAttribute("data-id", data.professor_id);
    }

    // Enable subject dropdown since we have a professor
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

    // Set course type if available
    if (data.class_type) {
      document.getElementById("selected-course-type").textContent =
        data.class_type;

      // Handle subgroup options for TD and TP
      if (
        (data.class_type === "TD" || data.class_type === "TP") &&
        data.is_split
      ) {
        // Show subgroup options
        document.getElementById("subgroup-options").classList.remove("hidden");
        document.getElementById("subgroup-split").checked = true;

        // Check split type and show appropriate options
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

          // Set second professor and room
          if (data.professor2) {
            document.getElementById("selected-professor-2").textContent =
              data.professor2;
            if (data.professor2_id) {
              document
                .getElementById("selected-professor-2")
                .setAttribute("data-id", data.professor2_id);
            }
          }

          // Set second subject if available
          if (data.subject2) {
            document
              .getElementById("subject-dropdown-2")
              .removeAttribute("disabled");
            document.getElementById(
              "subject-dropdown-2"
            ).style.backgroundColor = "#ffffff";
            document.getElementById("subject-dropdown-2").style.cursor =
              "pointer";
            document.getElementById("selected-subject-2").textContent =
              data.subject2;
            if (data.subject2_id) {
              document
                .getElementById("selected-subject-2")
                .setAttribute("data-id", data.subject2_id);
            }
          }

          if (data.room2) {
            document.getElementById("selected-room-2").textContent = data.room2;
          }
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

          // Set selected subgroup
          if (data.subgroup) {
            const subgroupNum = data.subgroup.slice(-1);
            document.getElementById("selected-subgroup").textContent =
              "Sous-groupe " + subgroupNum;
          }
        }
      } else {
        // Hide subgroup options for non-TD/TP or non-split classes
        document.getElementById("subgroup-options").classList.add("hidden");
        document
          .getElementById("subgroup-split-options")
          .classList.add("hidden");
        document
          .getElementById("second-subgroup-options")
          .classList.add("hidden");
        document
          .getElementById("single-subgroup-selector")
          .classList.add("hidden");
        document.getElementById("subgroup-single").checked = true;
      }
    }

    showModalWithAnimation("class-modal");
  }

  // Load saved timetable data
  function loadSavedData() {
    // First initialize with empty timetable
    initTimetableData();

    // Construct API URL for admin timetable view
    const apiUrl = "../api/timetables/get_timetable.php";

    // Add query parameters
    const params = new URLSearchParams({
      year: currentYear,
      group: currentGroup,
      admin: "true",
    });

    console.log(
      "Loading timetable data from:",
      `${apiUrl}?${params.toString()}`
    );

    // Try to load from server
    fetch(`${apiUrl}?${params.toString()}`)
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        console.log("Server response for timetable data:", data);

        if (data && data.success !== false && data.data) {
          // We found saved data, load it
          console.log("Received timetable data:", data.data);

          // Make sure we have a properly structured timetableData object
          timetableData = {};

          // Initialize the structure for all days and time slots
          days.forEach((day) => {
            timetableData[day] = {};
            timeSlots.forEach((time) => {
              timetableData[day][time] = null;
            });
          });

          // Now populate with the received data
          for (const day in data.data) {
            if (!timetableData[day]) {
              timetableData[day] = {};
            }

            for (const time in data.data[day]) {
              timetableData[day][time] = data.data[day][time];

              // Debug logging for split classes
              const entry = data.data[day][time];
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

          console.log("Processed timetable data:", timetableData);

          generateEmptyTimetable(); // This will actually display the loaded data
          showToast(
            "success",
            `Emploi du temps chargé pour ${currentYear}-${currentGroup}`
          );

          // Set published flag based on the server response
          isCurrentlyPublished = data.is_published || false;
          hasDraftChanges = data.has_draft_changes || false;
          hasUnsavedChanges = false;
          updatePublishStatus();
        } else {
          // No saved data found, keep the empty timetable
          console.log("No timetable data found or invalid response");
          generateEmptyTimetable();
          showToast(
            "info",
            `Aucun emploi du temps trouvé pour ${currentYear}-${currentGroup}`
          );

          isCurrentlyPublished = false;
          hasDraftChanges = false;
          hasUnsavedChanges = false;
          updatePublishStatus();
        }
      })
      .catch((error) => {
        console.error("Error loading timetable data:", error);
        showToast(
          "error",
          "Erreur lors du chargement des données. Vérifiez votre connexion et le chemin du projet."
        );
        generateEmptyTimetable();
        isCurrentlyPublished = false;
        hasDraftChanges = false;
        hasUnsavedChanges = false;
        updatePublishStatus();
      });
  }

  // Filter subjects based on professor ID
  function filterSubjectsByProfessor(professorId) {
    // Fetch subjects assigned to this professor from the database
    if (!professorId) {
      document.getElementById("selected-subject").textContent =
        "Sélectionner un professeur d'abord";
      document
        .getElementById("subject-dropdown")
        .setAttribute("disabled", "disabled");
      return;
    }

    // Clear existing subject menu items
    const subjectMenu = document.getElementById("subject-menu");
    subjectMenu.innerHTML =
      '<div class="dropdown-item" style="color: #888;">Chargement...</div>';

    // Fetch professor subjects from nested professors API
    const apiUrl = `../api/professors/get_professor_subjects.php?professor_id=${professorId}`;

    console.log("Fetching professor subjects from:", apiUrl);

    fetch(apiUrl)
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        console.log("Professor subjects response:", data);
        if (data.success && data.subjects && data.subjects.length > 0) {
          // Clear current menu
          subjectMenu.innerHTML = "";

          // Add each subject to the dropdown
          data.subjects.forEach((subject) => {
            const item = document.createElement("div");
            item.className = "dropdown-item";
            item.setAttribute("data-value", subject.name);
            item.setAttribute("data-id", subject.id);
            item.setAttribute("data-color", subject.color);

            // Show subject name and code if available
            const displayText = subject.code
              ? `${subject.name} (${subject.code})`
              : subject.name;

            item.textContent = displayText;

            item.addEventListener("click", function () {
              const subject = this.getAttribute("data-value");
              const subjectId = this.getAttribute("data-id");
              const color = this.getAttribute("data-color");

              document.getElementById("selected-subject").textContent = subject;
              document
                .getElementById("selected-subject")
                .setAttribute("data-id", subjectId);
              document.getElementById("edit-color").value = color;
              subjectMenu.classList.remove("open");
              document
                .getElementById("subject-dropdown")
                .classList.remove("active");
            });

            subjectMenu.appendChild(item);
          });

          // Enable the dropdown
          document
            .getElementById("subject-dropdown")
            .removeAttribute("disabled");
          document.getElementById("selected-subject").textContent =
            "Sélectionner une matière";
        } else {
          // No subjects found for this professor
          subjectMenu.innerHTML =
            '<div class="dropdown-item" style="color: #888;">Aucune matière assignée à ce professeur</div>';
          document.getElementById("selected-subject").textContent =
            "Aucune matière disponible";
          document
            .getElementById("subject-dropdown")
            .setAttribute("disabled", "disabled");
        }
      })
      .catch((error) => {
        console.error("Error fetching professor subjects:", error);
        document.getElementById("selected-subject").textContent =
          "Erreur lors du chargement des matières";
        document
          .getElementById("subject-dropdown")
          .setAttribute("disabled", "disabled");
        subjectMenu.innerHTML =
          '<div class="dropdown-item" style="color: #888;">Erreur lors du chargement des matières</div>';
      });
  }

  // Display professor conflict in modal
  function showProfessorConflict(conflicts, classData) {
    // Generate conflict details HTML
    const conflictDetailsElement = document.getElementById("conflict-details");
    let conflictHtml = "";

    conflicts.forEach((conflict) => {
      // Determine which professor has the conflict
      const isProfessor2Conflict = conflict.is_professor2_conflict === true;
      const professorTitle = isProfessor2Conflict
        ? "Conflit avec le deuxième professeur:"
        : "Conflit avec le professeur:";

      conflictHtml += `
                    <div class="conflict-item">
                        <p class="conflict-title conflict-title-danger">${professorTitle}</p>
                        <p><span class="strong-text">Professeur:</span> ${
                          conflict.professor || classData.professor
                        }</p>
                        <p><span class="strong-text">Jour:</span> ${
                          conflict.day
                        }</p>
                        <p><span class="strong-text">Heure:</span> ${
                          conflict.time
                        }</p>
                        <p><span class="strong-text">Année:</span> ${
                          conflict.year
                        }</p>
                        <p><span class="strong-text">Groupe:</span> ${
                          conflict.group
                        }</p>
                        <p><span class="strong-text">Matière:</span> ${
                          conflict.subject
                        }</p>
                        <p><span class="strong-text">Salle:</span> ${
                          conflict.room
                        }</p>`;

      // Add subgroup information if available
      if (conflict.is_split) {
        if (conflict.split_type === "same_time") {
          conflictHtml += `
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
                        </div>`;
        } else if (conflict.split_type === "single_group") {
          conflictHtml += `
                        <div class="conflict-subsection">
                            <p class="conflict-title conflict-title-info">Cours avec sous-groupe unique :</p>
                            <p><span class="strong-text">Sous-groupe:</span> ${
                              conflict.subgroup || ""
                            }</p>
                        </div>`;
        }
      }

      conflictHtml += `</div>`;
    });

    conflictDetailsElement.innerHTML = conflictHtml;
    showModalWithAnimation("professor-conflict-modal");
  }

  // Display room conflict in modal
  function showRoomConflict(conflicts, classData) {
    // Generate conflict details HTML
    const conflictDetailsElement = document.getElementById(
      "room-conflict-details"
    );
    let conflictHtml = "";

    conflicts.forEach((conflict) => {
      conflictHtml += `
                    <div class="conflict-item">
                        <p><span class="strong-text">Salle:</span> ${
                          conflict.room || classData.room
                        }</p>
                        <p><span class="strong-text">Jour:</span> ${
                          conflict.day
                        }</p>
                        <p><span class="strong-text">Heure:</span> ${
                          conflict.time
                        }</p>
                        <p><span class="strong-text">Année:</span> ${
                          conflict.year
                        }</p>
                        <p><span class="strong-text">Groupe:</span> ${
                          conflict.group
                        }</p>
                        <p><span class="strong-text">Matière:</span> ${
                          conflict.subject
                        }</p>
                        <p><span class="strong-text">Professeur:</span> ${
                          conflict.professor
                        }</p>`;

      // Add subgroup information if available
      if (conflict.is_split) {
        if (conflict.split_type === "same_time") {
          conflictHtml += `
                        <div class="conflict-subsection">
                            <p class="conflict-title conflict-title-info">Cours avec sous-groupes :</p>
                            <p><span class="strong-text">Sous-groupe 1:</span> ${
                              conflict.subgroup1 || ""
                            }</p>
                            <p><span class="strong-text">Sous-groupe 2:</span> ${
                              conflict.subgroup2 || ""
                            }</p>
                            <p><span class="strong-text">Matière 2:</span> ${
                              conflict.subject2 || ""
                            }</p>
                            <p><span class="strong-text">Professeur 2:</span> ${
                              conflict.professor2 || ""
                            }</p>
                            <p><span class="strong-text">Salle 2:</span> ${
                              conflict.room2 || ""
                            }</p>
                        </div>`;
        } else if (conflict.split_type === "single_group") {
          conflictHtml += `
                        <div class="conflict-subsection">
                            <p class="conflict-title conflict-title-info">Cours avec sous-groupe unique :</p>
                            <p><span class="strong-text">Sous-groupe:</span> ${
                              conflict.subgroup || ""
                            }</p>
                        </div>`;
        }
      }

      conflictHtml += `</div>`;
    });

    conflictDetailsElement.innerHTML = conflictHtml;
    showModalWithAnimation("room-conflict-modal");
  }

  // Function to save class data after validations
  function saveClassData(classData, day, time) {
    console.log("Saving class data:", { classData, day, time });

    // Ensure the day object exists
    if (!timetableData[day]) {
      timetableData[day] = {};
    }

    // Now we can safely set the time slot
    timetableData[day][time] = classData;

    hasUnsavedChanges = true;
    updatePublishStatus();
    generateEmptyTimetable();
    closeModalWithAnimation("class-modal");
    showToast(
      "success",
      "Cours enregistré ! N'oubliez pas d'utiliser le bouton Enregistrer pour sauvegarder les modifications"
    );
  }

  // Form submission handler
  document
    .getElementById("class-form")
    .addEventListener("submit", function (e) {
      e.preventDefault();

      const day = document.getElementById("edit-day").value;
      const time = document.getElementById("edit-time").value;
      const id =
        document.getElementById("edit-id").value ||
        new Date().getTime().toString();
      const color = document.getElementById("edit-color").value;

      const professorElement = document.getElementById("selected-professor");
      const subjectElement = document.getElementById("selected-subject");
      const roomElement = document.getElementById("selected-room");

      const professor = professorElement.textContent;
      const professorId = professorElement.getAttribute("data-id");
      const subject = subjectElement.textContent;
      const subjectId = subjectElement.getAttribute("data-id");
      const room = roomElement.textContent;
      const roomId = room; // For now, use room name as ID

      console.log("Form submission values:", {
        day,
        time,
        id,
        color,
        professor,
        professorId,
        subject,
        subjectId,
        room,
        roomId,
      });

      // Validate inputs
      if (professor === "Sélectionner un professeur") {
        showToast("error", "Veuillez sélectionner un professeur");
        return;
      }

      if (
        subject === "Sélectionner une matière" ||
        subject === "Aucune matière disponible" ||
        subject === "Erreur lors du chargement des matières" ||
        subject === "Chargement des matières..." ||
        subject === "Sélectionner un professeur d'abord"
      ) {
        showToast("error", "Veuillez sélectionner une matière");
        return;
      }

      if (room === "Sélectionner une salle") {
        showToast("error", "Veuillez sélectionner une salle");
        return;
      }

      // Get the current course type
      const courseTypeElement = document.getElementById("selected-course-type");
      const courseType = courseTypeElement.textContent;

      // Create class data object
      const classData = {
        id: id,
        subject: subject,
        subject_id: subjectId,
        professor: professor,
        professor_id: professorId,
        room: room,
        room_id: roomId, // Add room_id to data
        color: color,
        class_type: courseType, // Add class_type to data
        year: currentYear,
        group: currentGroup,
      };

      // Subgroup information for availability checks
      let is_split = false;
      let split_type = null;
      let subgroup = null;
      let room2 = null;
      let professor2_id = null;

      // Handle subgroup information if applicable
      if (courseType === "TD" || courseType === "TP") {
        // Check if split option is selected
        if (document.getElementById("subgroup-split").checked) {
          classData.is_split = 1;
          is_split = true;

          // Check which split option is selected
          if (document.getElementById("subgroup-same-time").checked) {
            classData.split_type = "same_time";
            split_type = "same_time";

            // Get second professor and room information
            const professor2Element = document.getElementById(
              "selected-professor-2"
            );
            const subject2Element =
              document.getElementById("selected-subject-2");
            const room2Element = document.getElementById("selected-room-2");

            const professor2 = professor2Element.textContent;
            const professor2Id = professor2Element.getAttribute("data-id");
            const subject2 = subject2Element.textContent;
            const subject2Id = subject2Element.getAttribute("data-id");
            const room2Value = room2Element.textContent;

            // Validate second professor, subject and room
            if (professor2 === "Sélectionner un professeur") {
              showToast(
                "error",
                "Veuillez sélectionner un professeur pour le deuxième sous-groupe"
              );
              return;
            }

            if (
              subject2 === "Sélectionner une matière" ||
              subject2 === "Aucune matière disponible" ||
              subject2 === "Erreur lors du chargement des matières" ||
              subject2 === "Chargement des matières..." ||
              subject2 === "Sélectionner un professeur d'abord"
            ) {
              showToast(
                "error",
                "Veuillez sélectionner une matière pour le deuxième sous-groupe"
              );
              return;
            }

            if (room2Value === "Sélectionner une salle") {
              showToast(
                "error",
                "Veuillez sélectionner une salle pour le deuxième sous-groupe"
              );
              return;
            }

            // Add second professor, subject and room to class data
            classData.professor2 = professor2;
            classData.professor2_id = professor2Id;
            classData.subject2 = subject2;
            classData.subject2_id = subject2Id;
            classData.room2 = room2Value;

            // Save for conflict checks
            room2 = room2Value;
            professor2_id = professor2Id;

            // Check if same professor is selected for both subgroups
            if (professor2Id === professorId) {
              showToast(
                "error",
                "Le même professeur ne peut pas enseigner à deux sous-groupes en même temps"
              );
              return;
            }

            // Check if same room is selected for both subgroups
            if (room2Value === room) {
              showToast(
                "error",
                "La même salle ne peut pas être utilisée par deux sous-groupes en même temps"
              );
              return;
            }

            // Generate subgroup names based on group
            // Format: TD1/TD2 for G1, TD3/TD4 for G2, etc.
            const groupNumber = currentGroup.replace("G", "");
            const subgroupNumber1 = parseInt(groupNumber) * 2 - 1;
            const subgroupNumber2 = parseInt(groupNumber) * 2;

            classData.subgroup1 = courseType + subgroupNumber1;
            classData.subgroup2 = courseType + subgroupNumber2;

            // Debug logging
            console.log("Split subgroups same time:", {
              subgroup1: classData.subgroup1,
              subgroup2: classData.subgroup2,
              professor2: professor2,
              room2: room2Value,
            });
          } else if (document.getElementById("subgroup-single-group").checked) {
            classData.split_type = "single_group";
            split_type = "single_group";

            // Get selected subgroup
            const subgroupElement =
              document.getElementById("selected-subgroup");
            const subgroupNum = subgroupElement.textContent.includes("1")
              ? 1
              : 2;

            // Generate subgroup name based on group
            const groupNumber = currentGroup.replace("G", "");
            const subgroupNumber =
              subgroupNum === 1
                ? parseInt(groupNumber) * 2 - 1
                : parseInt(groupNumber) * 2;

            classData.subgroup = courseType + subgroupNumber;
            subgroup = classData.subgroup;

            // Debug logging
            console.log("Single subgroup selected:", {
              subgroupNum,
              groupNumber,
              subgroupNumber,
              subgroup: classData.subgroup,
            });
          }
        } else {
          classData.is_split = 0; // Explicitly set to integer 0 instead of boolean false
        }
      } else {
        // For non-TD/TP courses, always set is_split to 0
        classData.is_split = 0;
      }

      console.log("Class data prepared:", classData);

      // Make sure timetableData is properly initialized
      if (!timetableData) {
        console.log("timetableData was null, initializing");
        initTimetableData();
      }

      // Ensure the day object exists
      if (!timetableData[day]) {
        console.log(`Day ${day} not found in timetableData, creating it`);
        timetableData[day] = {};
      }

      // Prepare data for professor availability check
      const professorCheckData = {
        professor_id: professorId,
        day: day,
        time_slot: time,
        year: currentYear,
        group: currentGroup,
        is_split: is_split,
        split_type: split_type,
        subgroup: subgroup,
        professor2_id: professor2_id,
      };

      // Prepare data for room availability check
      const roomCheckData = {
        room: room,
        day: day,
        time_slot: time,
        year: currentYear,
        group: currentGroup,
        is_split: is_split,
        split_type: split_type,
        subgroup: subgroup,
        room2: room2,
      };

      console.log("Checking professor availability with:", professorCheckData);
      console.log("Checking room availability with:", roomCheckData);

      // Check professor availability first
      fetch("../api/availability/check_professor_availability.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(professorCheckData),
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
          }
          return response.json();
        })
        .then((data) => {
          if (data.available) {
            // No professor conflicts, now check room availability
            fetch("../api/availability/check_room_availability.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(roomCheckData),
            })
              .then((response) => {
                if (!response.ok) {
                  throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
              })
              .then((roomData) => {
                if (roomData.available) {
                  // No conflicts, save the class
                  console.log("No conflicts found, saving class data");
                  saveClassData(classData, day, time);
                } else {
                  // Room conflict found
                  console.log("Room conflict found:", roomData.conflicts);
                  // Remove any data from this time slot to ensure conflicts aren't saved
                  if (timetableData[day] && timetableData[day][time]) {
                    timetableData[day][time] = null;
                  }

                  // Show room conflict modal
                  showRoomConflict(roomData.conflicts, classData);
                  closeModalWithAnimation("class-modal");
                }
              })
              .catch((error) => {
                console.error("Error checking room availability:", error);
                showToast(
                  "error",
                  "Erreur lors de la vérification de la disponibilité de la salle"
                );
              });
          } else {
            // Professor conflict found
            console.log("Professor conflict found:", data.conflicts);
            // Remove any data from this time slot to ensure conflicts aren't saved
            if (timetableData[day] && timetableData[day][time]) {
              timetableData[day][time] = null;
            }

            // Show conflict modal
            showProfessorConflict(data.conflicts, classData);
            closeModalWithAnimation("class-modal");
          }
        })
        .catch((error) => {
          console.error("Error checking professor availability:", error);
          showToast(
            "error",
            "Erreur lors de la vérification de la disponibilité du professeur"
          );
        });
    });

  // Warn user when leaving page with unsaved changes
  window.addEventListener("beforeunload", function (e) {
    if (hasUnsavedChanges) {
      e.preventDefault();
      e.returnValue = "";
      return "";
    }
  });

  // Handle back button navigation
  document
    .querySelector('a[href="../admin/index.php"]')
    .addEventListener("click", function (e) {
      if (hasUnsavedChanges) {
        e.preventDefault();
        const href = this.getAttribute("href");
        showUnsavedChangesWarning(function () {
          window.location.href = href;
        });
      }
    });

  // Load saved data on initial page load
  loadSavedData();

  // Add animation effect to radio buttons - simplified
  document.querySelectorAll('input[type="radio"]').forEach((radio) => {
    radio.addEventListener("change", function () {
      if (this.checked) {
        // Force animation to replay
        this.style.animation = "none";
        void this.offsetHeight; // Trigger reflow
        this.style.animation = "";
      }
    });
  });

  // Delete class confirmation handler
  document
    .getElementById("delete-class-confirm")
    .addEventListener("click", function () {
      if (deleteClassDay && deleteClassTime) {
        // Get the class data before deleting it
        const classToDelete = timetableData[deleteClassDay][deleteClassTime];

        console.log("Deleting class:", {
          day: deleteClassDay,
          time: deleteClassTime,
          class: classToDelete,
        });

        // Delete from in-memory timetable
        timetableData[deleteClassDay][deleteClassTime] = null;

        // Since we're deleting directly from the database, don't mark as unsaved changes
        // hasUnsavedChanges = true; - REMOVED THIS LINE
        updatePublishStatus();

        // Also delete from database to make it permanent
        const apiUrl = `../api/classes/delete_class.php`;

        const deleteData = {
          year: currentYear,
          group: currentGroup,
          day: deleteClassDay,
          time_slot: deleteClassTime,
        };

        console.log("Sending delete request with data:", deleteData);

        fetch(apiUrl, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(deleteData),
        })
          .then((response) => {
            if (!response.ok) {
              throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
          })
          .then((data) => {
            console.log("Delete response:", data);
            if (data.success) {
              // Show success message
              showToast("success", "Cours supprimé avec succès");
            } else {
              showToast(
                "error",
                data.message || "Erreur lors de la suppression du cours"
              );
              console.error("Delete error:", data);
            }
          })
          .catch((error) => {
            console.error("Error deleting class:", error);
            showToast("error", "Erreur lors de la suppression du cours");
          });

        // Regenerate timetable
        generateEmptyTimetable();

        // Close modal
        closeModalWithAnimation("delete-class-modal");

        // Reset variables
        deleteClassDay = null;
        deleteClassTime = null;
      }
    });

  // Professor conflict modal handlers
  document
    .getElementById("conflict-cancel")
    .addEventListener("click", function () {
      closeModalWithAnimation("professor-conflict-modal");
    });

  // Room conflict modal handlers
  document
    .getElementById("room-conflict-cancel")
    .addEventListener("click", function () {
      closeModalWithAnimation("room-conflict-modal");
    });

  document
    .getElementById("room-conflict-close")
    .addEventListener("click", function () {
      closeModalWithAnimation("room-conflict-modal");
    });

  // No longer needed - removed resetClassStatus function

  // Handle subgroup option changes
  document
    .getElementById("subgroup-single")
    .addEventListener("change", function () {
      document.getElementById("subgroup-split-options").classList.add("hidden");
      document
        .getElementById("second-subgroup-options")
        .classList.add("hidden");
      document
        .getElementById("single-subgroup-selector")
        .classList.add("hidden");
    });

  document
    .getElementById("subgroup-split")
    .addEventListener("change", function () {
      document
        .getElementById("subgroup-split-options")
        .classList.remove("hidden");

      // Check which split option is selected and show/hide accordingly
      if (document.getElementById("subgroup-same-time").checked) {
        document
          .getElementById("second-subgroup-options")
          .classList.remove("hidden");
        document
          .getElementById("single-subgroup-selector")
          .classList.add("hidden");

        // Scroll to make second subgroup options visible
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

  // Handle subgroup split option changes
  document
    .getElementById("subgroup-same-time")
    .addEventListener("change", function () {
      document
        .getElementById("second-subgroup-options")
        .classList.remove("hidden");
      document
        .getElementById("single-subgroup-selector")
        .classList.add("hidden");

      // Scroll to make second subgroup options visible
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
    .addEventListener("change", function () {
      document
        .getElementById("second-subgroup-options")
        .classList.add("hidden");
      document
        .getElementById("single-subgroup-selector")
        .classList.remove("hidden");
    });

  // Setup subgroup dropdown items
  document.querySelectorAll("#subgroup-menu .dropdown-item").forEach((item) => {
    item.addEventListener("click", function () {
      const subgroupNum = this.getAttribute("data-value");
      document.getElementById("selected-subgroup").textContent =
        "Sous-groupe " + subgroupNum;
      document.getElementById("subgroup-menu").classList.remove("open");
      document.getElementById("subgroup-dropdown").classList.remove("active");
    });
  });

  // Handle professor search for the second professor dropdown
  const professorSearch2 = document.getElementById("professor-search-2");
  if (professorSearch2) {
    professorSearch2.addEventListener("input", function (e) {
      e.stopPropagation();
      const searchTerm = this.value.toLowerCase().trim();
      const professorItems = document.querySelectorAll(
        "#professor-list-2 .dropdown-item"
      );

      professorItems.forEach((item) => {
        const professorName = item.getAttribute("data-value").toLowerCase();
        if (searchTerm === "" || professorName.includes(searchTerm)) {
          item.style.display = "block";
        } else {
          item.style.display = "none";
        }
      });
    });

    // Prevent dropdown from closing when clicking in the search input
    professorSearch2.addEventListener("click", function (e) {
      e.stopPropagation();
    });
  }

  // Setup handlers for second professor dropdown items
  document
    .querySelectorAll("#professor-list-2 .dropdown-item")
    .forEach((item) => {
      item.addEventListener("click", function () {
        const professorName = this.getAttribute("data-value");
        const professorId = this.getAttribute("data-id");

        document.getElementById("selected-professor-2").textContent =
          professorName;
        document
          .getElementById("selected-professor-2")
          .setAttribute("data-id", professorId);

        document.getElementById("professor-menu-2").classList.remove("open");
        document
          .getElementById("professor-dropdown-2")
          .classList.remove("active");

        // Enable subject dropdown for second professor
        document
          .getElementById("subject-dropdown-2")
          .removeAttribute("disabled");
        document.getElementById("subject-dropdown-2").style.backgroundColor =
          "#ffffff";
        document.getElementById("subject-dropdown-2").style.cursor = "pointer";
        document.getElementById("selected-subject-2").textContent =
          "Chargement des matières...";

        // Filter subjects based on selected professor
        filterSubjectsByProfessor2(professorId);
      });
    });

  // Filter subjects based on second professor ID
  function filterSubjectsByProfessor2(professorId) {
    // Fetch subjects assigned to this professor from the database
    if (!professorId) {
      document.getElementById("selected-subject-2").textContent =
        "Sélectionner un professeur d'abord";
      document
        .getElementById("subject-dropdown-2")
        .setAttribute("disabled", "disabled");
      return;
    }

    // Clear existing subject menu items
    const subjectMenu = document.getElementById("subject-menu-2");
    subjectMenu.innerHTML =
      '<div class="dropdown-item" style="color: #888;">Chargement...</div>';

    // Fetch professor subjects for second dropdown from nested professors API
    const apiUrl = `../api/professors/get_professor_subjects.php?professor_id=${professorId}`;

    console.log("Fetching professor subjects from:", apiUrl);

    fetch(apiUrl)
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        console.log("Professor subjects response:", data);
        if (data.success && data.subjects && data.subjects.length > 0) {
          // Clear current menu
          subjectMenu.innerHTML = "";

          // Add each subject to the dropdown
          data.subjects.forEach((subject) => {
            const item = document.createElement("div");
            item.className = "dropdown-item";
            item.setAttribute("data-value", subject.name);
            item.setAttribute("data-id", subject.id);
            item.setAttribute("data-color", subject.color);

            // Show subject name and code if available
            const displayText = subject.code
              ? `${subject.name} (${subject.code})`
              : subject.name;

            item.textContent = displayText;

            item.addEventListener("click", function () {
              const subject = this.getAttribute("data-value");
              const subjectId = this.getAttribute("data-id");

              document.getElementById("selected-subject-2").textContent =
                subject;
              document
                .getElementById("selected-subject-2")
                .setAttribute("data-id", subjectId);
              subjectMenu.classList.remove("open");
              document
                .getElementById("subject-dropdown-2")
                .classList.remove("active");
            });

            subjectMenu.appendChild(item);
          });

          // Enable the dropdown
          document
            .getElementById("subject-dropdown-2")
            .removeAttribute("disabled");
          document.getElementById("selected-subject-2").textContent =
            "Sélectionner une matière";
        } else {
          // No subjects found for this professor
          subjectMenu.innerHTML =
            '<div class="dropdown-item" style="color: #888;">Aucune matière assignée à ce professeur</div>';
          document.getElementById("selected-subject-2").textContent =
            "Aucune matière disponible";
          document
            .getElementById("subject-dropdown-2")
            .setAttribute("disabled", "disabled");
        }
      })
      .catch((error) => {
        console.error("Error fetching professor subjects:", error);
        document.getElementById("selected-subject-2").textContent =
          "Erreur lors du chargement des matières";
        document
          .getElementById("subject-dropdown-2")
          .setAttribute("disabled", "disabled");
        subjectMenu.innerHTML =
          '<div class="dropdown-item" style="color: #888;">Erreur lors du chargement des matières</div>';
      });
  }

  // Setup handlers for second subject dropdown items
  document
    .querySelectorAll("#subject-menu-2 .dropdown-item")
    .forEach((item) => {
      item.addEventListener("click", function () {
        const subject = this.getAttribute("data-value");
        const subjectId = this.getAttribute("data-id");

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

  // Setup handlers for second room dropdown items
  document.querySelectorAll("#room-menu-2 .dropdown-item").forEach((item) => {
    item.addEventListener("click", function () {
      const roomName = this.getAttribute("data-value");

      document.getElementById("selected-room-2").textContent = roomName;
      document.getElementById("room-menu-2").classList.remove("open");
      document.getElementById("room-dropdown-2").classList.remove("active");
    });
  });

  // ... existing code ...
  // Add animation effect to radio buttons
});
