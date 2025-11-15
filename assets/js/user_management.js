// Toast notification
document.addEventListener("DOMContentLoaded", function () {
  const toast = document.getElementById("toast-notification");
  const toastMsgEl = document.getElementById("toast-message");
  if (toast && toastMsgEl) {
    const message = toastMsgEl.textContent.trim();
    if (message) {
      toast.style.display = "block";
      setTimeout(() => {
        toast.classList.add("show");
      }, 100);
      setTimeout(() => {
        toast.classList.remove("show");
        setTimeout(() => {
          toast.style.display = "none";
        }, 300);
      }, 3000);
    }
  }
});

// Toggle password visibility
function togglePassword() {
  const passwordInput = document.getElementById("password");
  const toggleIcon = document.getElementById("toggle-icon");

  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    toggleIcon.src = "../assets/images/eye-off.svg";
  } else {
    passwordInput.type = "password";
    toggleIcon.src = "../assets/images/eye-show.svg";
  }
}

// Toggle form fields based on role
function toggleFields() {
  const role = document.getElementById("role").value;
  const professorFields = document.getElementById("professor-fields");
  const studentFields = document.getElementById("student-fields");

  // Hide all sections first
  professorFields.classList.remove("visible");
  if (studentFields) studentFields.classList.remove("visible");

  // Show relevant section
  if (role === "professor") {
    professorFields.classList.add("visible");
  } else if (role === "student" && studentFields) {
    studentFields.classList.add("visible");
  }
}

// Custom dropdown functionality
function toggleDropdown(dropdownButton, dropdownMenu) {
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
    closeAllDropdowns();
    dropdownButton.classList.add("active");
    dropdownMenu.style.display = "block";

    void dropdownMenu.offsetWidth; // Force reflow
    dropdownMenu.classList.add("open");

    return true;
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

// Setup role dropdown
document.addEventListener("DOMContentLoaded", function () {
  const roleButton = document.getElementById("role-dropdown");
  const roleMenu = document.getElementById("role-menu");
  const roleInput = document.getElementById("role");
  const selectedRoleSpan = document.getElementById("selected-role");

  const config = window.manageUsersConfig || {};
  const groupsByYear = config.groupsByYear || {};

  const yearButton = document.getElementById("year-dropdown");
  const yearMenu = document.getElementById("year-menu");
  const yearInput = document.getElementById("student-year-id");
  const selectedYearSpan = document.getElementById("selected-year");

  const groupButton = document.getElementById("group-dropdown");
  const groupMenu = document.getElementById("group-menu");
  const groupInput = document.getElementById("student-group-id");
  const selectedGroupSpan = document.getElementById("selected-group");

  function resetGroupDropdown() {
    if (!groupButton || !groupMenu || !groupInput || !selectedGroupSpan) return;
    groupInput.value = "";
    selectedGroupSpan.textContent = "Sélectionner une année d'abord";
    groupButton.setAttribute("disabled", "disabled");
    groupButton.style.backgroundColor = "#f1f5f9";
    groupButton.style.cursor = "not-allowed";
    groupMenu.innerHTML = "";
    groupButton.addEventListener("mouseover", () => {
      groupButton.style.border = "1px solid #e2e8f0";
    });
  }

  // Toggle dropdown on button click
  roleButton.addEventListener("click", function (e) {
    e.preventDefault();
    e.stopPropagation();
    toggleDropdown(this, roleMenu);
  });

  // Handle role selection
  document.querySelectorAll("#role-menu .dropdown-item").forEach((item) => {
    item.addEventListener("click", function () {
      const value = this.getAttribute("data-value");
      const text = this.textContent;

      // Update hidden input and display
      roleInput.value = value;
      selectedRoleSpan.textContent = text;

      // Close dropdown
      roleMenu.classList.remove("open");
      roleButton.classList.remove("active");

      // Trigger field visibility toggle
      toggleFields();
    });
  });

  if (
    yearButton &&
    yearMenu &&
    yearInput &&
    selectedYearSpan &&
    groupButton &&
    groupMenu &&
    groupInput &&
    selectedGroupSpan
  ) {
    resetGroupDropdown();

    yearButton.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      toggleDropdown(this, yearMenu);
    });

    yearMenu.querySelectorAll(".dropdown-item").forEach((item) => {
      item.addEventListener("click", function () {
        const yearId = this.getAttribute("data-id");
        const yearName = this.getAttribute("data-name") || this.textContent;

        yearInput.value = yearId || "";
        selectedYearSpan.textContent = yearName;

        yearMenu.classList.remove("open");
        yearButton.classList.remove("active");

        resetGroupDropdown();

        const yearGroups = groupsByYear[yearId] || [];

        if (yearGroups.length > 0) {
          yearGroups.forEach((group) => {
            const itemEl = document.createElement("div");
            itemEl.className = "dropdown-item";
            itemEl.setAttribute("data-id", group.id);
            itemEl.textContent = group.display_name || group.name;
            itemEl.addEventListener("click", function () {
              const gId = this.getAttribute("data-id");
              const gLabel = this.textContent;
              groupInput.value = gId || "";
              selectedGroupSpan.textContent = gLabel;
              groupMenu.classList.remove("open");
              groupButton.classList.remove("active");
            });
            groupMenu.appendChild(itemEl);
          });

          groupButton.removeAttribute("disabled");
          groupButton.style.backgroundColor = "#ffffff";
          groupButton.style.cursor = "pointer";
          groupButton.addEventListener("mouseover", () => {
            groupButton.style.border = "1px solid #10b981";
          });
          groupButton.addEventListener("mouseout", () => {
            groupButton.style.border = "1px solid #e2e8f0";
          });
          selectedGroupSpan.textContent = "Sélectionner un groupe";
        } else {
          selectedGroupSpan.textContent = "Aucun groupe pour cette année";
        }
      });
    });

    groupButton.addEventListener("click", function (e) {
      if (this.hasAttribute("disabled")) return;
      e.preventDefault();
      e.stopPropagation();
      toggleDropdown(this, groupMenu);
    });
  }

  // Close dropdowns when clicking outside
  document.addEventListener("click", function (event) {
    closeAllDropdowns();
  });
});

// Enable clicking the custom checkbox container/icon to toggle the checkbox
document.addEventListener("click", function (e) {
  const wrapper = e.target.closest(".custom-checkbox");
  if (!wrapper) return;

  // If the click is on a label or on the input itself, let the native behavior handle it
  if (e.target.closest("label") || e.target.tagName.toLowerCase() === "input")
    return;

  const input = wrapper.querySelector('input[type="checkbox"]');
  if (!input) return;
  input.checked = !input.checked;
  // Trigger change event if any listeners rely on it
  const changeEvent = new Event("change", { bubbles: true });
  input.dispatchEvent(changeEvent);
});
