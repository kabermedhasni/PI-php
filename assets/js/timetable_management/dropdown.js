// dropdown.js - Dropdown Management

export class DropdownManager {
  constructor() {
    this.setupEventListeners();
  }
  
  toggleDropdown(dropdownButton, dropdownMenu) {
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
      this.closeAllDropdowns();
      dropdownButton.classList.add("active");
      dropdownMenu.style.display = "block";
      dropdownMenu.style.top = "calc(100% + 4px)";
      dropdownMenu.style.bottom = "auto";

      void dropdownMenu.offsetWidth; // Force reflow
      dropdownMenu.classList.add("open");

      setTimeout(() => {
        this.ensureDropdownVisible(dropdownButton, dropdownMenu);
      }, 50);

      return true;
    }
  }
  
  ensureDropdownVisible(button, dropdown) {
    const modalContent = document.querySelector(".modal-content");
    if (!modalContent) return;

    const modalRect = modalContent.getBoundingClientRect();
    const buttonRect = button.getBoundingClientRect();
    const dropdownHeight = dropdown.offsetHeight;

    const dropdownBottom = buttonRect.bottom + dropdownHeight - modalRect.top;
    const modalHeight = modalContent.offsetHeight;

    if (dropdownBottom > modalHeight) {
      const scrollAmount = dropdownBottom - modalHeight + 20;
      modalContent.scrollTop += scrollAmount;
    }
  }
  
  closeAllDropdowns() {
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
  
  setupEventListeners() {
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
        button.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          this.toggleDropdown(button, menu);
        });
      }
    });

    // Close dropdowns when clicking outside
    document.addEventListener("click", () => {
      this.closeAllDropdowns();
    });
  }
  
  setupProfessorSearch() {
    const professorSearch = document.getElementById("professor-search");
    if (professorSearch) {
      professorSearch.addEventListener("input", function (e) {
        e.stopPropagation();
        const searchTerm = this.value.toLowerCase().trim();
        const professorItems = document.querySelectorAll("#professor-list .dropdown-item");

        professorItems.forEach((item) => {
          const professorName = item.getAttribute("data-value").toLowerCase();
          if (searchTerm === "" || professorName.includes(searchTerm)) {
            item.style.display = "block";
          } else {
            item.style.display = "none";
          }
        });
      });

      professorSearch.addEventListener("click", (e) => e.stopPropagation());
    }
    
    // Setup second professor search
    const professorSearch2 = document.getElementById("professor-search-2");
    if (professorSearch2) {
      professorSearch2.addEventListener("input", function (e) {
        e.stopPropagation();
        const searchTerm = this.value.toLowerCase().trim();
        const professorItems = document.querySelectorAll("#professor-list-2 .dropdown-item");

        professorItems.forEach((item) => {
          const professorName = item.getAttribute("data-value").toLowerCase();
          if (searchTerm === "" || professorName.includes(searchTerm)) {
            item.style.display = "block";
          } else {
            item.style.display = "none";
          }
        });
      });

      professorSearch2.addEventListener("click", (e) => e.stopPropagation());
    }
  }
}