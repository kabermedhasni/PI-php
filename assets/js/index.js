document.addEventListener("DOMContentLoaded", function () {
  const root = document.getElementById("timetable-root");
  if (!root) return;

  // Read config safely from data attribute (JSON was escaped server-side)
  let cfg = {};
  try {
    const raw = root.getAttribute("data-config") || "{}";
    cfg = JSON.parse(raw);
  } catch (e) {
    console.error("Invalid timetable config JSON:", e);
    cfg = {};
  }

  // Initialize variables from config
  let currentYear = cfg.currentYear;
  let currentGroup = cfg.currentGroup;
  const professorId = cfg.professorId;
  const userRole = cfg.role;
  const groupsByYear = cfg.groupsByYear || {};
  const timeSlots = cfg.timeSlots || [];
  const days = cfg.days || [];
  const isProfessorDebug = !!cfg.isProfessorDebug;

  // Clean sensitive selection parameters from the visible URL while
  // keeping them available in session / JS config.
  (function cleanParamsFromUrl() {
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

      // In professor debug mode, also hide the professor_id used to
      // select which professor timetable is shown.
      if (url.searchParams.has("professor_id")) {
        url.searchParams.delete("professor_id");
        changed = true;
      }

      if (changed) {
        window.history.replaceState({}, "", url);
      }
    } catch (e) {
      console.error("Failed to clean year/group from URL:", e);
    }
  })();

  // Timetable data
  let timetableData = {};

  // Initialize empty timetable data
  function initTimetableData() {
    timetableData = {};
    days.forEach((day) => {
      timetableData[day] = {};
      timeSlots.forEach((time) => {
        timetableData[day][time] = null;
      });
    });
  }

  // Generate view-only timetable
  function generateViewTimetable() {
    const tbody = document.getElementById("timetable-body");
    if (!tbody) return;
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

        // Check if we have data for this cell
        if (timetableData[day] && timetableData[day][time]) {
          const data = timetableData[day][time];

          // If we're in professor view, we might have multiple classes at same time
          const classes = Array.isArray(data) ? data : [data];

          classes.forEach((classData) => {
            if (!classData) return;

            const classBlock = document.createElement("div");
            classBlock.className = "class-block";

            // Determine color based on class_type if available
            let color;
            if (classData.class_type) {
              switch (classData.class_type) {
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
                  color = classData.color || "#6b7280"; // Fallback to data.color or default grey
              }
            } else {
              // Use the color from data if available, otherwise use default color
              color = classData.color || "#6b7280";
            }

            classBlock.style.borderLeftColor = color;

            // Apply visual styling for professor view and admin debug mode if class is canceled or rescheduled
            if (
              (userRole === "professor" || userRole === "admin") &&
              (classData.is_canceled == 1 || classData.is_reschedule == 1)
            ) {
              if (classData.is_canceled == 1) {
                classBlock.style.backgroundColor = "#FEF2F2"; // Very light red background for professor
              } else if (classData.is_reschedule == 1) {
                classBlock.style.backgroundColor = "#EFF6FF"; // Very light blue background for professor
              }
            }

            const subjectDiv = document.createElement("div");
            subjectDiv.className = "class-subject";
            subjectDiv.textContent = classData.subject;

            // Color the subject name based on class type
            if (classData.class_type) {
              subjectDiv.style.color = color;

              // Add class type indicator
              const typeSpan = document.createElement("span");
              typeSpan.className = "class-type";
              typeSpan.textContent = `(${classData.class_type})`;
              subjectDiv.appendChild(typeSpan);
            }

            // Handle subgroup information
            if (classData.is_split) {
              // For split classes with same time option
              if (classData.split_type === "same_time") {
                // For student view, show both subgroups
                if (userRole === "student") {
                  // Show both subgroups
                  const subgroupDiv = document.createElement("div");
                  subgroupDiv.className = "class-subgroup";
                  subgroupDiv.textContent = `${classData.subgroup1}/${classData.subgroup2}`;

                  // If subjects are different, show both
                  if (
                    classData.subject2 &&
                    classData.subject !== classData.subject2
                  ) {
                    subjectDiv.textContent = `${classData.subject}/${classData.subject2}`;
                    // Add tooltip for full subject names
                    subjectDiv.title = `${classData.subject} / ${classData.subject2}`;
                    subjectDiv.style.cursor = "help";
                  }

                  // Add professors for both subgroups
                  const detailsDiv = document.createElement("div");
                  detailsDiv.className = "class-details";
                  detailsDiv.textContent = `${classData.professor}/${
                    classData.professor2 || ""
                  }`;
                  detailsDiv.title = `${classData.professor} / ${
                    classData.professor2 || ""
                  }`;
                  detailsDiv.style.cursor = "help";

                  // Add rooms for both subgroups
                  const roomDiv = document.createElement("div");
                  roomDiv.className = "class-room";
                  roomDiv.textContent = `Salle: ${classData.room}/${
                    classData.room2 || ""
                  }`;
                  roomDiv.title = `${classData.room} / ${
                    classData.room2 || ""
                  }`;

                  classBlock.appendChild(subjectDiv);
                  classBlock.appendChild(subgroupDiv);
                  classBlock.appendChild(detailsDiv);
                  classBlock.appendChild(roomDiv);
                }
                // For professor view, only show the subgroup they teach
                else if (userRole === "professor" || isProfessorDebug) {
                  // Determine if this professor teaches subgroup 1 or 2
                  const isProfessor1 = classData.professor_id == professorId;
                  const isProfessor2 = classData.professor2_id == professorId;

                  // Only show the relevant subgroup
                  if (isProfessor1 || isProfessor2) {
                    const subgroupDiv = document.createElement("div");
                    subgroupDiv.className = "class-subgroup";

                    // Show which subgroup they teach
                    if (isProfessor1) {
                      subgroupDiv.textContent =
                        classData.subgroup1 || "Sous-groupe 1";
                      subjectDiv.textContent = classData.subject;
                    } else {
                      subgroupDiv.textContent =
                        classData.subgroup2 || "Sous-groupe 2";
                      subjectDiv.textContent =
                        classData.subject2 || classData.subject;
                    }

                    // For professors, show group and year
                    const detailsDiv = document.createElement("div");
                    detailsDiv.className = "class-details";
                    detailsDiv.textContent = `${classData.year} - ${classData.group}`;

                    // Room info
                    const roomDiv = document.createElement("div");
                    roomDiv.className = "class-room";
                    roomDiv.textContent = `Salle: ${
                      isProfessor1 ? classData.room : classData.room2
                    }`;

                    classBlock.appendChild(subjectDiv);
                    classBlock.appendChild(subgroupDiv);
                    classBlock.appendChild(detailsDiv);
                    classBlock.appendChild(roomDiv);
                  }
                }
              }
              // For split classes with single group option
              else if (classData.split_type === "single_group") {
                // For student view, show the subgroup
                if (userRole === "student") {
                  const subgroupDiv = document.createElement("div");
                  subgroupDiv.className = "class-subgroup";
                  subgroupDiv.textContent = classData.subgroup || "";

                  // Add professor info
                  const detailsDiv = document.createElement("div");
                  detailsDiv.className = "class-details";
                  detailsDiv.textContent = classData.professor;

                  // Add room info
                  const roomDiv = document.createElement("div");
                  roomDiv.className = "class-room";
                  roomDiv.textContent = `Salle: ${classData.room}`;

                  classBlock.appendChild(subjectDiv);
                  classBlock.appendChild(subgroupDiv);
                  classBlock.appendChild(detailsDiv);
                  classBlock.appendChild(roomDiv);
                }
                // For professor view, only show if they teach this subgroup
                else if (userRole === "professor" || isProfessorDebug) {
                  // Only show if this professor teaches this subgroup
                  if (classData.professor_id == professorId) {
                    const subgroupDiv = document.createElement("div");
                    subgroupDiv.className = "class-subgroup";
                    subgroupDiv.textContent = classData.subgroup || "";

                    // For professors, show group and year
                    const detailsDiv = document.createElement("div");
                    detailsDiv.className = "class-details";
                    detailsDiv.textContent = `${classData.year} - ${classData.group}`;

                    // Room info
                    const roomDiv = document.createElement("div");
                    roomDiv.className = "class-room";
                    roomDiv.textContent = `Salle: ${classData.room}`;

                    classBlock.appendChild(subjectDiv);
                    classBlock.appendChild(subgroupDiv);
                    classBlock.appendChild(detailsDiv);
                    classBlock.appendChild(roomDiv);
                  }
                }
              }
            } else {
              // Regular class without subgroups
              // For professors, show group and year
              let detailsText = "";
              if (userRole === "professor" || isProfessorDebug) {
                detailsText = `${classData.year} - ${classData.group}`;
              } else {
                detailsText = classData.professor;
              }

              const detailsDiv = document.createElement("div");
              detailsDiv.className = "class-details";
              detailsDiv.textContent = detailsText;

              const roomDiv = document.createElement("div");
              roomDiv.className = "class-room";

              // Add class type to room info if available
              if (classData.class_type) {
                roomDiv.textContent = `Salle: ${classData.room}`;
              }

              classBlock.appendChild(subjectDiv);
              classBlock.appendChild(detailsDiv);
              classBlock.appendChild(roomDiv);
            }

            // Add action buttons for professors (but not for admins in debug mode)
            if (userRole === "professor") {
              const actionsDiv = document.createElement("div");
              actionsDiv.className = "actions";

              if (classData.is_reschedule == 1) {
                // Show undo reschedule button
                const undoRescheduleBtn = document.createElement("button");
                undoRescheduleBtn.className = "btn btn-undo-reschedule";
                undoRescheduleBtn.textContent = "Annuler le report";
                undoRescheduleBtn.onclick = function (e) {
                  e.stopPropagation();
                  updateClassStatus(
                    classData.id,
                    "reset",
                    classData.professor_id
                  );
                };
                actionsDiv.appendChild(undoRescheduleBtn);
              } else if (classData.is_canceled == 1) {
                // Show undo cancel button
                const undoCancelBtn = document.createElement("button");
                undoCancelBtn.className = "btn btn-undo-cancel";
                undoCancelBtn.textContent = "Annuler l'annulation";
                undoCancelBtn.onclick = function (e) {
                  e.stopPropagation();
                  updateClassStatus(
                    classData.id,
                    "reset",
                    classData.professor_id
                  );
                };
                actionsDiv.appendChild(undoCancelBtn);
              } else {
                // Show regular buttons
                // Reschedule button
                const rescheduleBtn = document.createElement("button");
                rescheduleBtn.className = "btn btn-reschedule";
                rescheduleBtn.textContent = "Reporter";
                rescheduleBtn.onclick = function (e) {
                  e.stopPropagation();
                  updateClassStatus(
                    classData.id,
                    "reschedule",
                    classData.professor_id
                  );
                };

                // Cancel button
                const cancelBtn = document.createElement("button");
                cancelBtn.className = "btn btn-cancel";
                cancelBtn.textContent = "Annuler";
                cancelBtn.onclick = function (e) {
                  e.stopPropagation();
                  updateClassStatus(
                    classData.id,
                    "cancel",
                    classData.professor_id
                  );
                };

                actionsDiv.appendChild(rescheduleBtn);
                actionsDiv.appendChild(cancelBtn);
              }

              classBlock.appendChild(actionsDiv);
            }

            // Apply visual indicators for admin if class is canceled or rescheduled
            if (
              userRole === "admin" &&
              (classData.is_canceled == 1 || classData.is_reschedule == 1)
            ) {
              if (classData.is_canceled == 1) {
                classBlock.style.backgroundColor = "#FEE2E2"; // Light red background
                const statusDiv = document.createElement("div");
                statusDiv.className = "status status-red";
                statusDiv.textContent = "DEMANDE D'ANNULATION";
                classBlock.appendChild(statusDiv);
              } else if (classData.is_reschedule == 1) {
                classBlock.style.backgroundColor = "#DBEAFE"; // Light blue background
                const statusDiv = document.createElement("div");
                statusDiv.className = "status status-blue";
                statusDiv.textContent = "DEMANDE DE REPORT";
                classBlock.appendChild(statusDiv);
              }
            }

            cell.appendChild(classBlock);
          });
        } else {
          // Empty cell
          cell.innerHTML = `<div class="empty-cell">Pas de cours</div>`;
        }

        row.appendChild(cell);
      });

      tbody.appendChild(row);
    });
  }

  // Initialize
  initTimetableData();
  generateViewTimetable();

  // Create toast notification element
  function createToastElement() {
    if (document.getElementById("toast-notification")) return;

    const toast = document.createElement("div");
    toast.id = "toast-notification";
    toast.className = "toast";
    document.body.appendChild(toast);
  }
  createToastElement();

  // Show toast message
  function showToast(type, message) {
    const toast = document.getElementById("toast-notification");
    if (!toast) return;
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

  // Load timetable data
  function loadTimetableData() {
    let apiUrl = "";

    if (userRole === "professor" || isProfessorDebug) {
      // For professor view, we get courses filtered by professor ID
      apiUrl = `api/timetables/get_timetable.php?professor_id=${professorId}`;
    } else {
      // For students and admin preview of student view, filter by year and group
      apiUrl = `api/timetables/get_timetable.php?year=${encodeURIComponent(
        currentYear
      )}&group=${encodeURIComponent(currentGroup)}`;
    }

    // Try to load from server - prefer published version first
    fetch(apiUrl)
      .then((response) => response.json())
      .then((data) => {
        if (data && data.data) {
          timetableData = data.data;
          generateViewTimetable();
          showToast("success", `Emploi du temps chargé`);

          // Keep currentYear/currentGroup internal; do not expose in URL
        } else {
          // If no data from server, show empty timetable
          initTimetableData();
          generateViewTimetable();
          if (userRole === "professor" || isProfessorDebug) {
            showToast(
              "info",
              "Aucun cours n'a été trouvé dans votre emploi du temps"
            );
          } else {
            showToast(
              "info",
              `Aucun emploi du temps trouvé pour ${currentYear}-${currentGroup}`
            );
          }
        }
      })
      .catch((error) => {
        console.error("Erreur lors du chargement des données:", error);
        // Show error toast
        showToast("error", "Erreur lors du chargement des données");
        // Initialize empty
        initTimetableData();
        generateViewTimetable();
      });
  }

  // Function to update class status (cancel or reschedule)
  function updateClassStatus(classId, status, professorIdParam) {
    if (!classId || !status || !professorIdParam) {
      showToast(
        "error",
        "Données manquantes pour mettre à jour le statut du cours"
      );
      return;
    }

    const data = {
      id: classId,
      status: status,
      professor_id: professorIdParam,
    };

    fetch("api/classes/update_class_status.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data),
    })
      .then((response) => response.json())
      .then((result) => {
        if (result.success) {
          showToast("success", result.message);
          // Reload timetable data to reflect the changes
          loadTimetableData();
        } else {
          showToast(
            "error",
            result.message || "Erreur lors de la mise à jour du statut"
          );
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showToast("error", "Erreur lors de la mise à jour du statut");
      });
  }

  // Load timetable data on page load
  loadTimetableData();
});
