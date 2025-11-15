// timetableRenderer.js - Timetable Rendering Logic

export class TimetableRenderer {
  constructor(config, dragHandler) {
    this.config = config;
    this.dragHandler = dragHandler;
  }

  render(onEditClick, onDeleteClick, onAddClick) {
    const tbody = document.getElementById("timetable-body");
    tbody.innerHTML = "";

    this.config.timeSlots.forEach((time) => {
      const row = document.createElement("tr");

      // Time cell
      const timeCell = document.createElement("td");
      timeCell.className = "time-cell";
      timeCell.textContent = time;
      row.appendChild(timeCell);

      // Day cells
      this.config.days.forEach((day) => {
        const cell = this.createDayCell(
          day,
          time,
          onEditClick,
          onDeleteClick,
          onAddClick
        );
        row.appendChild(cell);
      });

      tbody.appendChild(row);
    });

    if (this.dragHandler) {
      this.dragHandler.attachDragHandlers();
    }
  }

  createDayCell(day, time, onEditClick, onDeleteClick, onAddClick) {
    const cell = document.createElement("td");
    cell.className = "subject-cell";
    cell.setAttribute("data-day", day);
    cell.setAttribute("data-time", time);

    if (
      this.config.timetableData[day] &&
      this.config.timetableData[day][time]
    ) {
      const data = this.config.timetableData[day][time];
      const classBlock = this.createClassBlock(
        data,
        day,
        time,
        onEditClick,
        onDeleteClick
      );
      cell.appendChild(classBlock);
    } else {
      const emptyCell = this.createEmptyCell(day, time, onAddClick);
      cell.appendChild(emptyCell);
    }

    return cell;
  }

  createClassBlock(data, day, time, onEditClick, onDeleteClick) {
    const classBlock = document.createElement("div");
    classBlock.className = "class-block";

    // Determine color based on class_type
    const color = this.getClassTypeColor(data);
    classBlock.style.borderLeftColor = color;

    // Apply visual indicators if class is canceled or rescheduled
    if (data.is_canceled == 1) {
      classBlock.style.backgroundColor = "#FEE2E2";
    } else if (data.is_reschedule == 1) {
      classBlock.style.backgroundColor = "#DBEAFE";
    }

    // Create subject div
    const subjectDiv = this.createSubjectDiv(data, color);

    // Handle subgroup display
    if (
      (data.class_type === "TD" || data.class_type === "TP") &&
      data.is_split
    ) {
      classBlock.style.borderTop = "2px dashed " + color;

      if (data.split_type === "same_time") {
        this.addSameTimeSubgroupDisplay(classBlock, data, subjectDiv, color);
      } else if (data.split_type === "single_group") {
        this.addSingleSubgroupDisplay(classBlock, data, subjectDiv);
      }
    } else {
      // Standard display without subgroups
      classBlock.appendChild(subjectDiv);
      classBlock.appendChild(this.createProfessorDiv(data.professor));
      classBlock.appendChild(this.createRoomDiv(data.room));
    }

    // Add status indicators
    if (data.is_canceled == 1 || data.is_reschedule == 1) {
      classBlock.appendChild(this.createStatusDiv(data));
    }

    // Add action buttons
    classBlock.appendChild(
      this.createActionDiv(day, time, data, onEditClick, onDeleteClick)
    );

    return classBlock;
  }

  getClassTypeColor(data) {
    if (data.class_type) {
      switch (data.class_type) {
        case "CM":
          return "#6b7280";
        case "TD":
          return "#10b981";
        case "TP":
          return "#3b82f6";
        case "DE":
          return "#f59e0b";
        case "CO":
          return "#ef4444";
        default:
          return data.color || "#6b7280";
      }
    }
    return data.color || "#6b7280";
  }

  createSubjectDiv(data, color) {
    const subjectDiv = document.createElement("div");
    subjectDiv.className = "class-subject";
    subjectDiv.textContent = data.subject;
    subjectDiv.style.color = color;

    if (data.class_type) {
      const typeSpan = document.createElement("span");
      typeSpan.className = "class-type";
      typeSpan.textContent = `(${data.class_type})`;
      typeSpan.style.color = color;
      subjectDiv.appendChild(typeSpan);
    }

    return subjectDiv;
  }

  createProfessorDiv(professor) {
    const professorDiv = document.createElement("div");
    professorDiv.className = "class-professor";
    professorDiv.textContent = professor;
    return professorDiv;
  }

  createRoomDiv(room) {
    const roomDiv = document.createElement("div");
    roomDiv.className = "class-room";
    roomDiv.textContent = `Salle: ${room}`;
    return roomDiv;
  }

  addSameTimeSubgroupDisplay(classBlock, data, subjectDiv, color) {
    const subgroupDiv = document.createElement("div");
    subgroupDiv.className = "class-subgroup";
    subgroupDiv.textContent = `${data.subgroup1}/${data.subgroup2}`;

    if (data.subject2 && data.subject !== data.subject2) {
      subjectDiv.textContent = `${data.subject}/${data.subject2}`;
      subjectDiv.title = `${data.subject} / ${data.subject2}`;
    }

    const professorDiv = this.createProfessorDiv(
      `${data.professor}/${data.professor2}`
    );
    professorDiv.title = `${data.professor} / ${data.professor2}`;

    const roomDiv = this.createRoomDiv(`${data.room}/${data.room2}`);
    roomDiv.title = `${data.room} / ${data.room2}`;

    classBlock.appendChild(subjectDiv);
    classBlock.appendChild(subgroupDiv);
    classBlock.appendChild(professorDiv);
    classBlock.appendChild(roomDiv);
  }

  addSingleSubgroupDisplay(classBlock, data, subjectDiv) {
    const subgroupDiv = document.createElement("div");
    subgroupDiv.className = "class-subgroup";

    if (data.subgroup) {
      subgroupDiv.textContent = data.subgroup;
    } else {
      const groupMatch = String(this.config.currentGroup).match(/(\d+)/);
      const groupNumber = groupMatch ? parseInt(groupMatch[1], 10) : 1;
      const subgroupNumber = groupNumber * 2 - 1;
      subgroupDiv.textContent = data.class_type + subgroupNumber;
    }

    classBlock.appendChild(subjectDiv);
    classBlock.appendChild(subgroupDiv);
    classBlock.appendChild(this.createProfessorDiv(data.professor));
    classBlock.appendChild(this.createRoomDiv(data.room));
  }

  createStatusDiv(data) {
    const statusDiv = document.createElement("div");
    if (data.is_canceled == 1) {
      statusDiv.className = "class-status class-status-canceled";
      statusDiv.textContent = "ANNULÉ PAR LE PROFESSEUR";
    } else if (data.is_reschedule == 1) {
      statusDiv.className = "class-status class-status-reschedule";
      statusDiv.textContent = "DEMANDE DE REPORT";
    }
    return statusDiv;
  }

  createActionDiv(day, time, data, onEditClick, onDeleteClick) {
    const actionDiv = document.createElement("div");
    actionDiv.className = "class-actions";

    const editBtn = document.createElement("button");
    editBtn.className = "btn-link btn-edit";
    editBtn.textContent = "Modifier";
    editBtn.addEventListener("click", () => onEditClick(day, time));

    const deleteBtn = document.createElement("button");
    deleteBtn.className = "btn-link btn-delete";
    deleteBtn.textContent = "Supprimer";
    deleteBtn.addEventListener("click", () =>
      onDeleteClick(day, time, data.subject)
    );

    actionDiv.appendChild(editBtn);
    actionDiv.appendChild(deleteBtn);

    return actionDiv;
  }

  createEmptyCell(day, time, onAddClick) {
    const emptyCell = document.createElement("div");
    emptyCell.className = "empty-cell";
    emptyCell.innerHTML = `
      <button class="btn-icon">
        <svg xmlns="http://www.w3.org/2000/svg" class="icon-lg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
      </button>
    `;
    emptyCell.addEventListener("click", () => onAddClick(day, time));
    return emptyCell;
  }
}
