document.addEventListener("DOMContentLoaded", function () {
  // Modal animation functions
  function showModalWithAnimation(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    modal.classList.remove("hidden");

    // Force reflow
    void modal.offsetWidth;

    modal.classList.add("fade-in");
    modal.classList.remove("fade-out");
  }

  function closeModalWithAnimation(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    modal.classList.remove("fade-in");
    modal.classList.add("fade-out");

    // Hide after animation completes
    setTimeout(() => {
      modal.classList.add("hidden");
      modal.classList.remove("fade-out");
    }, 300);
  }

  // Toast notification handling
  function createToastElement() {
    if (document.getElementById("toast-notification")) return;

    const toast = document.createElement("div");
    toast.id = "toast-notification";
    toast.className = "toast";
    document.body.appendChild(toast);
  }

  function showToast(type, message) {
    // Create toast element if it doesn't exist
    createToastElement();

    const toast = document.getElementById("toast-notification");
    toast.textContent = message;
    toast.className = "toast";

    if (type === "success") {
      toast.classList.add("toast-success");
    } else if (type === "error") {
      toast.classList.add("toast-error");
    } else {
      toast.classList.add("bg-blue-500", "text-white");
    }

    toast.classList.add("show");

    setTimeout(() => {
      toast.classList.remove("show");
    }, 3000);
  }

  // Show the modal when clicking the clear button
  document
    .getElementById("clear-button")
    .addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      showModalWithAnimation("clear-modal");
    });

  // Hide the modal when clicking cancel
  document
    .getElementById("clear-cancel")
    .addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      closeModalWithAnimation("clear-modal");
    });

  // Submit the form when confirming
  document
    .getElementById("clear-confirm")
    .addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      const button = document.getElementById("clear-button");
      const defaultIcon = document.getElementById("clear-icon-default");
      const loadingIcon = document.getElementById("clear-icon-loading");
      const clearText = document.getElementById("clear-text");

      // Hide the modal with animation
      closeModalWithAnimation("clear-modal");

      // Add delay to match animation time
      setTimeout(() => {
        // Disable the button and show loading state
        button.disabled = true;
        button.classList.add("opacity-75");
        defaultIcon.classList.add("hidden");
        loadingIcon.classList.remove("hidden");
        clearText.textContent = "Suppression en cours...";

        // Use fetch instead of form submission
        fetch("../api/timetables/clear_timetables.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
        })
          .then((response) => response.json())
          .then((data) => {
            // Re-enable the button and restore its appearance
            button.disabled = false;
            button.classList.remove("opacity-75");
            defaultIcon.classList.remove("hidden");
            loadingIcon.classList.add("hidden");
            clearText.textContent = "Effacer Tous les Emplois du Temps";

            if (data.success) {
              showToast(
                "success",
                data.message ||
                  "Tous les emplois du temps ont été effacés avec succès !"
              );
              // Optionally refresh the page to show emptied timetable lists
              setTimeout(() => {
                window.location.reload();
              }, 1000);
            } else {
              showToast(
                "error",
                data.message || "Échec de l'effacement des emplois du temps."
              );
            }
          })
          .catch((error) => {
            // Re-enable the button and restore its appearance
            button.disabled = false;
            button.classList.remove("opacity-75");
            defaultIcon.classList.remove("hidden");
            loadingIcon.classList.add("hidden");
            clearText.textContent = "Effacer Tous les Emplois du Temps";

            console.error("Error clearing timetables:", error);
            showToast(
              "error",
              "Erreur lors de l'effacement des emplois du temps."
            );
          });
      }, 300);
    });

  // Close the modal if clicking outside of it
  document
    .getElementById("clear-modal")
    .addEventListener("click", function (e) {
      if (e.target === e.currentTarget) {
        e.stopPropagation();
        closeModalWithAnimation("clear-modal");
      }
    });

  // Prevent clicks on modal content from closing the modal
  document
    .querySelector("#clear-modal .modal-content")
    .addEventListener("click", function (e) {
      e.stopPropagation();
    });

  // Show the publish all modal when clicking the publish all button
  document
    .getElementById("publish-all-button")
    .addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      showModalWithAnimation("publish-all-modal");
    });

  // Hide the publish all modal when clicking cancel
  document
    .getElementById("publish-all-cancel")
    .addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      closeModalWithAnimation("publish-all-modal");
    });

  // Handle the publish all confirmation
  document
    .getElementById("publish-all-confirm")
    .addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      // Hide the modal with animation
      closeModalWithAnimation("publish-all-modal");

      // Send request to publish all timetables
      fetch("../api/timetables/publish_all_timetables.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            showToast(
              "success",
              data.message ||
                "Tous les emplois du temps ont été publiés avec succès !"
            );
            // Reload the page to refresh notifications
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          } else {
            showToast(
              "error",
              data.message ||
                "Échec de la publication de tous les emplois du temps."
            );
          }
        })
        .catch((error) => {
          console.error("Error publishing all timetables:", error);
          showToast(
            "error",
            "Erreur lors de la publication de tous les emplois du temps."
          );
        });
    });

  // Close the publish all modal if clicking outside of it
  document
    .getElementById("publish-all-modal")
    .addEventListener("click", function (e) {
      if (e.target === e.currentTarget) {
        e.stopPropagation();
        closeModalWithAnimation("publish-all-modal");
      }
    });

  // Prevent clicks on modal content from closing the modal
  document
    .querySelector("#publish-all-modal .modal-content")
    .addEventListener("click", function (e) {
      e.stopPropagation();
    });

  // Create toast element on page load
  createToastElement();

  // Ensure modals are properly hidden on page load
  const clearModal = document.getElementById("clear-modal");
  const publishModal = document.getElementById("publish-all-modal");
  if (clearModal) {
    clearModal.classList.add("hidden");
    clearModal.classList.remove("fade-in", "fade-out");
  }
  if (publishModal) {
    publishModal.classList.add("hidden");
    publishModal.classList.remove("fade-in", "fade-out");
  }

  // Prevent card clicks from triggering modals
  document.querySelectorAll(".card").forEach(function (card) {
    card.addEventListener("click", function (e) {
      // Allow normal navigation, don't interfere
      e.stopPropagation();
    });
  });
}); // End DOMContentLoaded
